<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * SLA timers and escalation (doc/sla.md): a first response target and a resolution target per SLA level and priority
 * ([SLASettings], [SLA_<level>], optionally per queue), counted on a clock (ticketsystemBusinessClock): business
 * hours and holidays from [SLASettings], or around the clock. The clock is paused while the ticket waits on the
 * customer ([SLASettings] PausedStatuses[]) and while it is resolved: the due times move on by the working time spent
 * paused.
 *
 * A target is a number of minutes on the default clock ([SLASettings] Clock; CalendarPriorities[] always run around
 * the clock) or a text such as "8 business hours", "2 business days" (business clock) or "30 minutes", "4 hours"
 * (around the clock), the form the Katalyst SLA levels use.
 *
 * The level of a ticket: the Katalyst customer's active SLA entitlement when Katalyst is present
 * ([SLASettings] KatalystEntitlements), else [SLASettings] DefaultLevel. A Katalyst level without its own
 * [SLA_<level>] block takes its first response target from the level's text in katalyst.ini and its resolution
 * target from the default level.
 *
 * Escalation runs in steps ([SLASettings] EscalationSteps[] = <percent>:<action>): at a share of a target's working
 * time the cronjob notifies the assignee, then the queue leads or the support group, raises the priority and marks
 * the ticket escalated (ticketsystemEscalation). steps() says which steps are due (pure); each runs once per ticket
 * and target. The index row keeps escalation (0, 1 = warned, 2 = breached), escalated (when) and breached (flags).
 *
 * The methods without the database are pure.
 */
class ticketsystemSla
{
    const NONE = 0;
    const WARNED = 1;
    const BREACHED = 2;

    /** breached flags */
    const BREACH_RESPONSE = 1;
    const BREACH_RESOLUTION = 2;

    /** The actions of an escalation step. */
    const ACTIONS = array( 'assignee', 'leads', 'group', 'raise', 'mark' );
    /** Actions that run once per ticket (the others once per target and step). */
    const TICKET_ACTIONS = array( 'raise', 'mark' );

    /** The severities of the Katalyst SLA levels per priority. */
    const SEVERITY = array( 'urgent' => 'critical', 'high' => 'major', 'medium' => 'minor', 'low' => 'request' );

    /** @var ticketsystemSettings */
    protected $settings;
    /** @var ticketsystemBusinessClock[] */
    protected $clocks = array();
    /** @var array|null Katalyst levels (tests set them) */
    public static $katalystLevels = null;

    public function __construct( ?ticketsystemSettings $settings = null )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    public function enabled()
    {
        return $this->settings->value( 'SLASettings', 'Enabled', 'enabled' ) === 'enabled';
    }

    public function warnPercent()
    {
        return min( 99, max( 1, (int)$this->settings->value( 'SLASettings', 'WarnPercent', 80 ) ) );
    }

    /** @return string[] */
    public function pausedStatuses()
    {
        return array_values( array_map( 'strval', (array)$this->settings->value( 'SLASettings', 'PausedStatuses', array( 'waiting_customer' ) ) ) );
    }

    public function defaultLevel()
    {
        return (string)$this->settings->value( 'SLASettings', 'DefaultLevel', 'standard' );
    }

    /** level => label of the levels with their own block. */
    public function levels()
    {
        $out = array();
        foreach ( (array)$this->settings->value( 'SLASettings', 'Levels', array( 'standard' ) ) as $l )
            $out[(string)$l] = (string)$this->settings->value( 'SLA_' . $l, 'Label', (string)$l );
        return $out;
    }

    /** The label of a level: its block's, the Katalyst level's, or the identifier. */
    public function levelLabel( $level )
    {
        $levels = $this->levels();
        if ( isset( $levels[$level] ) )
            return $levels[$level];
        $k = self::katalystLevels();
        return isset( $k[$level]['label'] ) && $k[$level]['label'] !== '' ? (string)$k[$level]['label'] : strtoupper( (string)$level );
    }

    // -------------------------------------------------------------------------------------------- clocks

    /** Whether the default clock of numeric targets is business hours. */
    public function businessDefault()
    {
        return (string)$this->settings->value( 'SLASettings', 'Clock', 'calendar' ) === 'business';
    }

    /** The business clock of [SLASettings] (business = true) or the calendar. */
    public function clock( $business = true )
    {
        $key = $business ? 'business' : 'calendar';
        if ( !isset( $this->clocks[$key] ) )
            $this->clocks[$key] = $business
                ? new ticketsystemBusinessClock( false, (array)$this->settings->value( 'SLASettings', 'BusinessDays', array( 1, 2, 3, 4, 5 ) ),
                                                 (string)$this->settings->value( 'SLASettings', 'BusinessHours', '09:00-17:00' ),
                                                 (array)$this->settings->value( 'SLASettings', 'Holidays', array() ),
                                                 (string)$this->settings->value( 'SLASettings', 'TimeZone', '' ) )
                : ticketsystemBusinessClock::calendar();
        return $this->clocks[$key];
    }

    /** The clock of one target of an index row. */
    public function clockFor( array $row, $kind )
    {
        $spec = $this->spec( self::rowValue( $row, 'sla_level', $this->defaultLevel() ), self::rowValue( $row, 'priority', '' ), $kind,
                             self::rowValue( $row, 'category', '' ) );
        return $this->clock( $spec[1] );
    }

    protected static function rowValue( array $row, $key, $default )
    {
        return isset( $row[$key] ) && (string)$row[$key] !== '' ? (string)$row[$key] : $default;
    }

    // ------------------------------------------------------------------------------------------- targets

    /**
     * A target as minutes and clock (pure apart from the settings): the level's queue value
     * ([SLA_<level>] <kind>[<category>:<priority>]), the level's value, for a first response the Katalyst level's
     * text, then the same of the default level.
     *
     * @param string $kind FirstResponse or Resolution
     * @param string $category the queue ('' = none)
     * @param string $fallbackText a response text that wins over the Katalyst level's ('' = none)
     * @return array minutes (0 = no target), business (bool)
     */
    public function spec( $level, $priority, $kind, $category = '', $fallbackText = '' )
    {
        $level = (string)$level;
        $priority = (string)$priority;
        $category = (string)$category;
        $value = $this->blockValue( $level, $priority, $kind, $category );
        if ( $value === null && $kind === 'FirstResponse' )
        {
            $text = $fallbackText !== '' ? $fallbackText : $this->katalystText( $level, $priority );
            if ( $text !== '' )
            {
                $t = $this->parseTarget( $text, $priority );
                if ( $t[0] > 0 )
                    return $t;
            }
        }
        if ( $value === null && $level !== $this->defaultLevel() )
            $value = $this->blockValue( $this->defaultLevel(), $priority, $kind, $category );
        return $value === null ? array( 0, false ) : $this->parseTarget( $value, $priority );
    }

    /** The raw value of a target in a level's block, or null. */
    protected function blockValue( $level, $priority, $kind, $category )
    {
        $block = 'SLA_' . $level;
        if ( !$this->settings->hasBlock( $block ) )
            return null;
        $list = (array)$this->settings->value( $block, $kind, array() );
        if ( $category !== '' && isset( $list[$category . ':' . $priority] ) && (string)$list[$category . ':' . $priority] !== '' )
            return (string)$list[$category . ':' . $priority];
        return isset( $list[$priority] ) && (string)$list[$priority] !== '' ? (string)$list[$priority] : null;
    }

    /**
     * A target value as minutes and clock (pure apart from the settings): a number is minutes on the default clock
     * (around the clock for CalendarPriorities[]); a text names its unit and whether it counts business time.
     *
     * @return array minutes, business
     */
    public function parseTarget( $value, $priority = '' )
    {
        $value = trim( (string)$value );
        if ( preg_match( '/^\d+$/', $value ) )
        {
            $business = $this->businessDefault()
                     && !in_array( (string)$priority, array_map( 'strval', (array)$this->settings->value( 'SLASettings', 'CalendarPriorities', array() ) ), true );
            return array( (int)$value, $business );
        }
        $p = self::parseText( $value, $this->clock( true )->dayMinutes() );
        return $p ?: array( 0, false );
    }

    /**
     * A duration text (pure): "8 business hours", "2 business days", "1 working day" count business time (a business
     * day is $businessDayMinutes); "30 minutes", "4 hours", "1 day", "1 week" count around the clock.
     *
     * @return array|null minutes, business
     */
    public static function parseText( $text, $businessDayMinutes = 480 )
    {
        if ( !preg_match( '/(\d+(?:[.,]\d+)?)\s*(business\s+|working\s+)?(minute|min|hour|hr|h|day|d|week|wk)s?\b/i', (string)$text, $m ) )
            return null;
        $n = (float)str_replace( ',', '.', $m[1] );
        $business = trim( (string)$m[2] ) !== '';
        $unit = strtolower( $m[3] );
        $day = $business ? max( 1, (int)$businessDayMinutes ) : 1440;
        $f = array( 'minute' => 1, 'min' => 1, 'hour' => 60, 'hr' => 60, 'h' => 60, 'day' => $day, 'd' => $day,
                    'week' => $business ? $day * 5 : 10080, 'wk' => $business ? $day * 5 : 10080 );
        return array( (int)round( $n * $f[$unit] ), $business );
    }

    /**
     * Minutes of a target (pure apart from the settings); spec() without the clock.
     *
     * @param string $kind FirstResponse or Resolution
     * @param string $fallbackText a Katalyst entitlement's response text for this priority ('' = none)
     * @return int minutes, 0 = no target
     */
    public function minutes( $level, $priority, $kind, $fallbackText = '', $category = '' )
    {
        return $this->spec( $level, $priority, $kind, $category, $fallbackText )[0];
    }

    /**
     * Minutes of a duration text, around the clock (a business day as 24 hours; pure). 0 when it reads no duration.
     * ticketsystemSla::parseText() tells business time apart.
     */
    public static function parseDuration( $text )
    {
        $p = self::parseText( $text, 1440 );
        return $p ? $p[0] : 0;
    }

    /**
     * The due times of a new ticket (pure apart from the settings): array( response_due, resolve_due ), 0 = no
     * target.
     */
    public function dues( $created, $level, $priority, $fallbackText = '', $category = '' )
    {
        $out = array();
        foreach ( array( 'FirstResponse', 'Resolution' ) as $kind )
        {
            list( $minutes, $business ) = $this->spec( $level, $priority, $kind, $category, $kind === 'FirstResponse' ? $fallbackText : '' );
            $out[] = $minutes > 0 ? $this->clock( $business )->add( (int)$created, $minutes * 60 ) : 0;
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------- pausing

    /**
     * The SLA fields after a status change (pure apart from the settings): pausing (waiting on the customer, or
     * done) starts the pause, going on moves the open due times on by the working time spent paused.
     *
     * @param array $row response_due, resolve_due, responded, paused_since (and sla_level, priority, category)
     * @return array the changed fields only
     */
    public function onStatus( array $row, $oldStatus, $newStatus, $now, $isDone = false )
    {
        $is = in_array( (string)$newStatus, $this->pausedStatuses(), true ) || $isDone;
        $out = array();
        if ( $is && !(int)$row['paused_since'] )
            $out['paused_since'] = (int)$now;
        else if ( !$is && (int)$row['paused_since'] > 0 )
        {
            foreach ( array( 'response_due' => 'FirstResponse', 'resolve_due' => 'Resolution' ) as $field => $kind )
            {
                if ( (int)$row[$field] <= 0 || ( $field === 'response_due' && (int)$row['responded'] ) )
                    continue;
                $clock = $this->clockFor( $row, $kind );
                $out[$field] = $clock->add( (int)$row[$field], $clock->between( (int)$row['paused_since'], (int)$now ) );
            }
            $out['paused_since'] = 0;
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------- states

    /**
     * The state of one target (pure): none (no target), met, paused, ok, warn (past WarnPercent: due soon),
     * breached.
     *
     * @param ticketsystemBusinessClock|null $clock null = around the clock
     * @return array state, due, left (working seconds; negative when late), pct (share of the working time used)
     */
    public function target( $start, $due, $doneAt, $pausedSince, $now, ?ticketsystemBusinessClock $clock = null )
    {
        $clock = $clock ?: ticketsystemBusinessClock::calendar();
        $due = (int)$due;
        if ( $due <= 0 )
            return array( 'state' => 'none', 'due' => 0, 'left' => 0, 'pct' => 0 );
        if ( (int)$doneAt > 0 )
            return array( 'state' => (int)$doneAt <= $due ? 'met' : 'breached', 'due' => $due,
                          'left' => self::left( $clock, $due, (int)$doneAt ), 'pct' => self::pct( $clock, $start, $due, (int)$doneAt ) );
        $at = (int)$pausedSince > 0 ? (int)$pausedSince : (int)$now;
        $pct = self::pct( $clock, $start, $due, $at );
        $state = $at > $due ? 'breached' : ( $pct >= $this->warnPercent() ? 'warn' : 'ok' );
        if ( (int)$pausedSince > 0 && $state !== 'breached' )
            $state = 'paused';
        return array( 'state' => $state, 'due' => $due, 'left' => self::left( $clock, $due, $at ), 'pct' => $pct );
    }

    /** Working seconds from $at to $due, negative when $at is later. */
    protected static function left( ticketsystemBusinessClock $clock, $due, $at )
    {
        return $at <= $due ? $clock->between( $at, $due ) : -$clock->between( $due, $at );
    }

    protected static function pct( ticketsystemBusinessClock $clock, $start, $due, $at )
    {
        if ( (int)$at > (int)$due )
            return max( 100, (int)floor( ( $clock->between( $start, $at ) ) * 100 / max( 1, $clock->between( $start, $due ) ) ) );
        return max( 0, (int)floor( $clock->between( $start, $at ) * 100 / max( 1, $clock->between( $start, $due ) ) ) );
    }

    /**
     * The state of both targets of an index row (pure apart from the settings).
     *
     * @param array $row created, response_due, resolve_due, responded, resolved, paused_since, escalation (and
     *                   sla_level, priority, category for the clocks)
     * @return array response, resolution (target()), worst, escalation, tone
     */
    public function state( array $row, $now )
    {
        $response = $this->target( (int)$row['created'], (int)$row['response_due'], (int)$row['responded'], (int)$row['paused_since'], $now,
                                   $this->clockFor( $row, 'FirstResponse' ) );
        $resolution = $this->target( (int)$row['created'], (int)$row['resolve_due'], (int)$row['resolved'], (int)$row['paused_since'], $now,
                                     $this->clockFor( $row, 'Resolution' ) );
        $worst = 'none';
        foreach ( array( 'met', 'ok', 'paused', 'warn', 'breached' ) as $s )
            if ( $response['state'] === $s || $resolution['state'] === $s )
                $worst = $s;
        return array( 'response' => $response, 'resolution' => $resolution, 'worst' => $worst,
                      'escalation' => isset( $row['escalation'] ) ? (int)$row['escalation'] : 0,
                      'tone' => $worst === 'breached' ? 'bad' : ( $worst === 'warn' ? 'warn' : ( $worst === 'met' || $worst === 'ok' ? 'ok' : 'info' ) ) );
    }

    /** The label of a state for people: on track, due soon, breached ... */
    public static function stateLabel( $state )
    {
        $l = array( 'none' => 'No target', 'met' => 'Met', 'ok' => 'On track', 'paused' => 'Paused', 'warn' => 'Due soon', 'breached' => 'Breached' );
        return isset( $l[$state] ) ? $l[$state] : (string)$state;
    }

    /**
     * The escalation level and breach flags a ticket should have now (pure apart from the settings).
     *
     * @return array escalation (0, 1, 2), breached (flags), raise (true when the level went up)
     */
    public function check( array $row, $now )
    {
        $s = $this->state( $row, $now );
        $level = (int)$row['escalation'];
        $flags = (int)$row['breached'];
        if ( $s['response']['state'] === 'breached' )
            $flags |= self::BREACH_RESPONSE;
        if ( $s['resolution']['state'] === 'breached' )
            $flags |= self::BREACH_RESOLUTION;
        $want = $level;
        $open = array( $s['response']['state'], $s['resolution']['state'] );
        if ( in_array( 'breached', $open, true ) )
            $want = max( $want, self::BREACHED );
        else if ( in_array( 'warn', $open, true ) )
            $want = max( $want, self::WARNED );
        return array( 'escalation' => $want, 'breached' => $flags, 'raise' => $want > $level );
    }

    // ---------------------------------------------------------------------------------------- escalation

    /**
     * The escalation steps of [SLASettings] EscalationSteps[] (<percent>:<action>), in order of percent (pure apart
     * from the settings). Unknown actions are left out.
     *
     * @return array[] percent, action
     */
    public function escalationSteps()
    {
        $raw = (array)$this->settings->value( 'SLASettings', 'EscalationSteps',
                                              array( $this->warnPercent() . ':assignee', '100:leads', '100:raise', '100:mark' ) );
        $out = array();
        foreach ( $raw as $i => $s )
        {
            if ( !preg_match( '/^\s*(\d{1,4})\s*:\s*([a-z]+)\s*$/', (string)$s, $m ) || !in_array( $m[2], self::ACTIONS, true ) )
                continue;
            $out[] = array( 'percent' => max( 1, (int)$m[1] ), 'action' => $m[2], 'order' => $i );
        }
        usort( $out, function ( $a, $b ) { return $a['percent'] === $b['percent'] ? $a['order'] - $b['order'] : $a['percent'] - $b['percent']; } );
        return array_map( function ( $s ) { return array( 'percent' => $s['percent'], 'action' => $s['action'] ); }, $out );
    }

    /** The key of a step in the escalation log: <target>:<percent>:<action>, or the action alone for a ticket action. */
    public static function stepKey( $target, $percent, $action )
    {
        return in_array( $action, self::TICKET_ACTIONS, true ) ? (string)$action : $target . ':' . (int)$percent . ':' . $action;
    }

    /**
     * The escalation steps due for an index row now (pure apart from the settings): every step whose percent a
     * running target (not met, not paused before it) has reached and whose key is not in $done. A done ticket and a
     * met target have no steps.
     *
     * @param array $row the index row (state())
     * @param string[] $done the step keys already run
     * @return array[] key, target (response, resolution), percent, action, level (1 below 100 %, else 2), pct
     */
    public function steps( array $row, $now, array $done = array() )
    {
        $s = $this->state( $row, $now );
        $out = array();
        $seen = array_flip( $done );
        foreach ( array( 'response' => $s['response'], 'resolution' => $s['resolution'] ) as $target => $t )
        {
            $doneAt = $target === 'response' ? (int)$row['responded'] : (int)$row['resolved'];
            if ( $doneAt > 0 || !in_array( $t['state'], array( 'ok', 'warn', 'breached', 'paused' ), true ) )
                continue;
            foreach ( $this->escalationSteps() as $step )
            {
                if ( $t['pct'] < $step['percent'] )
                    continue;
                $key = self::stepKey( $target, $step['percent'], $step['action'] );
                if ( isset( $seen[$key] ) )
                    continue;
                $seen[$key] = true;
                $out[] = array( 'key' => $key, 'target' => $target, 'percent' => $step['percent'], 'action' => $step['action'],
                                'level' => $step['percent'] >= 100 ? self::BREACHED : self::WARNED, 'pct' => $t['pct'] );
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------ Katalyst

    /** The Katalyst SLA levels (level => label, response by severity), or none without Katalyst. */
    public static function katalystLevels()
    {
        if ( self::$katalystLevels !== null )
            return self::$katalystLevels;
        self::$katalystLevels = array();
        if ( class_exists( 'expKatalystEntitlementService' ) )
        {
            try
            {
                self::$katalystLevels = (array)( new expKatalystEntitlementService( 'web', 0 ) )->levels();
            }
            catch ( Throwable $e )
            {
                self::$katalystLevels = array();
            }
        }
        return self::$katalystLevels;
    }

    /** The first response text of a Katalyst level for a priority ('' = none, or Katalyst entitlements off). */
    public function katalystText( $level, $priority )
    {
        if ( $this->settings->value( 'SLASettings', 'KatalystEntitlements', 'enabled' ) !== 'enabled' )
            return '';
        $levels = self::katalystLevels();
        $sev = isset( self::SEVERITY[$priority] ) ? self::SEVERITY[$priority] : 'minor';
        return isset( $levels[$level]['response'][$sev] ) ? (string)$levels[$level]['response'][$sev] : '';
    }

    /**
     * The SLA level of a ticket's account: the best active SLA entitlement of its Katalyst customer (the one with
     * the shortest first response for the priority), else the default level.
     *
     * @return array level, fallback (the entitlement's response text for the priority, '' when none)
     */
    public function levelFor( $accountKey, $subscriptionId, $priority )
    {
        $default = array( 'level' => $this->defaultLevel(), 'fallback' => '' );
        if ( $this->settings->value( 'SLASettings', 'KatalystEntitlements', 'enabled' ) !== 'enabled'
             || !preg_match( '/^katalyst:(\d+)$/', (string)$accountKey, $m ) || !ticketsystemAccount::katalystAvailable()
             || !class_exists( 'expKatalystEntitlement' ) || !class_exists( 'expKatalystEntitlementService' ) )
            return $default;
        try
        {
            $best = null;
            foreach ( (array)eZPersistentObject::fetchObjectList( expKatalystEntitlement::definition(), null,
                          array( 'customer_id' => (int)$m[1], 'kind' => 'sla', 'status' => 'active' ), null, null, false ) as $e )
            {
                if ( (int)$e['subscription_id'] > 0 && (int)$subscriptionId > 0 && (int)$e['subscription_id'] !== (int)$subscriptionId )
                    continue;
                if ( (string)$e['ends'] !== '' && strcmp( (string)$e['ends'], date( 'Y-m-d' ) ) < 0 )
                    continue;
                $l = (string)$e['level'];
                $text = $this->katalystText( $l, (string)$priority );
                list( $mins, $business ) = $this->spec( $l, (string)$priority, 'FirstResponse', '', $text );
                // compare in working minutes of the business clock: a business hour is longer than a calendar one
                $cost = $business ? $mins * 1440 / max( 1, $this->clock( true )->dayMinutes() ) : $mins;
                if ( $mins > 0 && ( $best === null || $cost < $best[0] ) )
                    $best = array( $cost, $l, $text );
            }
            return $best ? array( 'level' => $best[1], 'fallback' => $best[2] ) : $default;
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'SLA level of ' . $accountKey . ': ' . $e->getMessage(), __METHOD__ );
            return $default;
        }
    }
}
