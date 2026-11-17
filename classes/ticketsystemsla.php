<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * SLA timers and escalation (doc/sla.md): a first response target and a resolution target per SLA level and priority
 * ([SLASettings], [SLA_<level>]), counted in calendar minutes from the opening of a ticket, paused while the ticket
 * waits on the customer ([SLASettings] PausedStatuses[]: the due times move on by the time spent paused). At
 * [SLASettings] WarnPercent (80 %) of a target the ticket is escalated (level 1) and at 100 % it is breached
 * (level 2); the cronjob part "ticketsystem" checks it and alerts the assignee and the support staff. Staff can
 * escalate by hand.
 *
 * The level of a ticket: the Katalyst customer's active SLA entitlement when Katalyst is present
 * ([SLASettings] KatalystEntitlements), else [SLASettings] DefaultLevel. A Katalyst level without its own
 * [SLA_<level>] block takes its first response target from the entitlement's text ("8 business hours"; business
 * time is counted as calendar time) and its resolution target from the default level.
 *
 * The methods without the database are pure; the index row carries sla_level, response_due, resolve_due, responded,
 * paused_since, escalation, escalated and breached.
 */
class ticketsystemSla
{
    const NONE = 0;
    const WARNED = 1;
    const BREACHED = 2;

    /** breached flags */
    const BREACH_RESPONSE = 1;
    const BREACH_RESOLUTION = 2;

    /** @var ticketsystemSettings */
    protected $settings;

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

    /**
     * Minutes of a target (pure apart from the settings).
     *
     * @param string $kind FirstResponse or Resolution
     * @param string $fallbackText a Katalyst entitlement's response text for this priority ('' = none)
     * @return int minutes, 0 = no target
     */
    public function minutes( $level, $priority, $kind, $fallbackText = '' )
    {
        $block = 'SLA_' . $level;
        if ( $this->settings->hasBlock( $block ) )
        {
            $list = (array)$this->settings->value( $block, $kind, array() );
            if ( isset( $list[$priority] ) )
                return max( 0, (int)$list[$priority] );
        }
        if ( $kind === 'FirstResponse' && $fallbackText !== '' )
        {
            $m = self::parseDuration( $fallbackText );
            if ( $m > 0 )
                return $m;
        }
        $default = 'SLA_' . $this->defaultLevel();
        $list = (array)$this->settings->value( $default, $kind, array() );
        return isset( $list[$priority] ) ? max( 0, (int)$list[$priority] ) : 0;
    }

    /**
     * Minutes of a duration text such as "8 business hours", "2 business days", "4 hours", "30 minutes", "1 day"
     * (pure). Business time is counted as calendar time (a business day as 24 hours). 0 when it reads no duration.
     */
    public static function parseDuration( $text )
    {
        if ( !preg_match( '/(\d+(?:[.,]\d+)?)\s*(?:business\s+|working\s+)?(minute|min|hour|hr|h|day|d|week|wk)s?\b/i', (string)$text, $m ) )
            return 0;
        $n = (float)str_replace( ',', '.', $m[1] );
        $unit = strtolower( $m[2] );
        $f = array( 'minute' => 1, 'min' => 1, 'hour' => 60, 'hr' => 60, 'h' => 60, 'day' => 1440, 'd' => 1440, 'week' => 10080, 'wk' => 10080 );
        return (int)round( $n * $f[$unit] );
    }

    /**
     * The due times of a new ticket (pure): array( response_due, resolve_due ), 0 = no target.
     */
    public function dues( $created, $level, $priority, $fallbackText = '' )
    {
        $r = $this->minutes( $level, $priority, 'FirstResponse', $fallbackText );
        $s = $this->minutes( $level, $priority, 'Resolution' );
        return array( $r > 0 ? (int)$created + $r * 60 : 0, $s > 0 ? (int)$created + $s * 60 : 0 );
    }

    /**
     * The SLA fields after a status change (pure): pausing starts the clock's pause, resuming moves the open due
     * times on by the pause.
     *
     * @param array $row response_due, resolve_due, responded, paused_since, resolved
     * @return array the changed fields only
     */
    public function onStatus( array $row, $oldStatus, $newStatus, $now, $isDone = false )
    {
        $paused = $this->pausedStatuses();
        $was = in_array( (string)$oldStatus, $paused, true );
        $is = in_array( (string)$newStatus, $paused, true ) || $isDone;
        $out = array();
        if ( !$was && $is && !(int)$row['paused_since'] )
            $out['paused_since'] = (int)$now;
        else if ( !$is && (int)$row['paused_since'] > 0 )
        {
            $shift = max( 0, (int)$now - (int)$row['paused_since'] );
            if ( (int)$row['response_due'] > 0 && !(int)$row['responded'] )
                $out['response_due'] = (int)$row['response_due'] + $shift;
            if ( (int)$row['resolve_due'] > 0 )
                $out['resolve_due'] = (int)$row['resolve_due'] + $shift;
            $out['paused_since'] = 0;
        }
        return $out;
    }

    /**
     * The state of one target (pure): none (no target), met, paused, ok, warn (past WarnPercent), breached.
     *
     * @return array state, due, left (seconds, negative when late), pct (0..), label
     */
    public function target( $start, $due, $doneAt, $pausedSince, $now )
    {
        $due = (int)$due;
        if ( $due <= 0 )
            return array( 'state' => 'none', 'due' => 0, 'left' => 0, 'pct' => 0 );
        $at = (int)$pausedSince > 0 ? (int)$pausedSince : (int)$now;
        if ( (int)$doneAt > 0 )
            return array( 'state' => (int)$doneAt <= $due ? 'met' : 'breached', 'due' => $due, 'left' => $due - (int)$doneAt,
                          'pct' => self::pct( $start, $due, (int)$doneAt ) );
        $pct = self::pct( $start, $due, $at );
        $state = $pct >= 100 ? 'breached' : ( $pct >= $this->warnPercent() ? 'warn' : 'ok' );
        if ( (int)$pausedSince > 0 && $state !== 'breached' )
            $state = 'paused';
        return array( 'state' => $state, 'due' => $due, 'left' => $due - $at, 'pct' => $pct );
    }

    protected static function pct( $start, $due, $at )
    {
        $span = max( 1, (int)$due - (int)$start );
        return max( 0, (int)floor( ( (int)$at - (int)$start ) * 100 / $span ) );
    }

    /**
     * The state of both targets of an index row (pure).
     *
     * @param array $row created, response_due, resolve_due, responded, resolved, paused_since, escalation, breached
     */
    public function state( array $row, $now )
    {
        $response = $this->target( (int)$row['created'], (int)$row['response_due'], (int)$row['responded'], (int)$row['paused_since'], $now );
        $resolution = $this->target( (int)$row['created'], (int)$row['resolve_due'], (int)$row['resolved'], (int)$row['paused_since'], $now );
        $worst = 'none';
        foreach ( array( 'met', 'ok', 'paused', 'warn', 'breached' ) as $s )
            if ( $response['state'] === $s || $resolution['state'] === $s )
                $worst = $s;
        return array( 'response' => $response, 'resolution' => $resolution, 'worst' => $worst,
                      'escalation' => (int)$row['escalation'], 'tone' => $worst === 'breached' ? 'bad' : ( $worst === 'warn' ? 'warn' : ( $worst === 'met' || $worst === 'ok' ? 'ok' : 'info' ) ) );
    }

    /**
     * What the cronjob does for a ticket (pure): the escalation level it should have now, and the new breach flags.
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
            $levels = ( new expKatalystEntitlementService( 'web', 0 ) )->levels();
            $key = array( 'urgent' => 'critical', 'high' => 'major', 'medium' => 'minor', 'low' => 'request' );
            $sev = isset( $key[$priority] ) ? $key[$priority] : 'minor';
            $best = null;
            foreach ( (array)eZPersistentObject::fetchObjectList( expKatalystEntitlement::definition(), null,
                          array( 'customer_id' => (int)$m[1], 'kind' => 'sla', 'status' => 'active' ), null, null, false ) as $e )
            {
                if ( (int)$e['subscription_id'] > 0 && (int)$subscriptionId > 0 && (int)$e['subscription_id'] !== (int)$subscriptionId )
                    continue;
                if ( (string)$e['ends'] !== '' && strcmp( (string)$e['ends'], date( 'Y-m-d' ) ) < 0 )
                    continue;
                $l = (string)$e['level'];
                $text = isset( $levels[$l]['response'][$sev] ) ? (string)$levels[$l]['response'][$sev] : '';
                $mins = $this->minutes( $l, (string)$priority, 'FirstResponse', $text );
                if ( $mins > 0 && ( $best === null || $mins < $best[0] ) )
                    $best = array( $mins, $l, $text );
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
