<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Runs the SLA escalation (doc/sla.md): the cronjob part "ticketsystem" calls run(), staff escalate one ticket by
 * hand with manual().
 *
 * For every active ticket with a target, ticketsystemSla::steps() names the steps that are due; each step is first
 * written to the escalation log (ticketsystem_escalation, unique per ticket and step key) and then done, so it runs
 * once even when the cronjob runs twice. The actions:
 *
 * - assignee: mail the assignee (nobody assigned: the queue leads);
 * - leads: mail the queue's leads ([SLASettings] QueueLeads[<category>], users or user groups by object id), else the
 *   support group ([AssignmentSettings] StaffGroup), plus [SLASettings] AlertRecipients;
 * - group: mail the whole support group, plus AlertRecipients;
 * - raise: raise the priority one step (the SLA targets stay those of the opening);
 * - mark: mark the ticket escalated (the lists show it).
 *
 * Mails go to staff only (ticketsystemSlaMailer::staff()). Each step writes a history event and its mail counts to
 * the log; the ticket keeps the highest level (1 below 100 % of a target, 2 at a breach) and the breach flags.
 */
class ticketsystemEscalation
{
    /** @var ticketsystemSettings */
    protected $settings;
    /** @var ticketsystemSla */
    protected $sla;
    /** @var ticketsystemService */
    protected $service;
    /** @var ticketsystemSlaMailer */
    protected $mailer;

    public function __construct( ?ticketsystemSettings $settings = null, ?ticketsystemService $service = null, ?ticketsystemSlaMailer $mailer = null )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
        $this->sla = new ticketsystemSla( $this->settings );
        if ( !$service )
        {
            // the cronjob acts as the administrator (a user who may read every ticket); events name no actor (0)
            $admin = eZUser::fetch( (int)eZINI::instance()->variable( 'UserSettings', 'UserCreatorID' ) ?: 14 );
            $service = new ticketsystemService( new ticketsystemAccess( $admin instanceof eZUser ? $admin : eZUser::currentUser() ), $this->settings );
        }
        $this->service = $service;
        $this->mailer = $mailer ?: new ticketsystemSlaMailer( $this->settings );
    }

    /**
     * The SLA check of the cronjob: every active ticket with a target.
     *
     * @return array checked, steps, mails, warned (tickets that reached level 1), breached (level 2), locked (another
     *               run holds the lock)
     * @param int[] $onlyIds only these tickets (tests); empty = every active ticket with a target
     */
    public function run( $now = null, $dryRun = false, array $onlyIds = array() )
    {
        $out = array( 'checked' => 0, 'steps' => 0, 'mails' => 0, 'warned' => 0, 'breached' => 0, 'locked' => false, 'due' => array() );
        if ( !$this->sla->enabled() )
            return $out;
        $lock = $dryRun ? null : self::lock();
        if ( $lock === false )
        {
            $out['locked'] = true;
            return $out;
        }
        try
        {
            $now = $now === null ? time() : (int)$now;
            $set = $this->settings->statusSet();
            $where = ticketsystemDesk::statusCondition( $set->active(), ticketsystemDesk::quoter() );
            $where = ( $where !== '' ? $where . ' AND ' : '' ) . '( response_due > 0 OR resolve_due > 0 )';
            if ( $onlyIds )
                $where .= ' AND id IN (' . implode( ', ', array_map( 'intval', $onlyIds ) ) . ')';
            for ( $offset = 0; ; $offset += 200 )
            {
                $batch = ticketsystemTicket::fetchWhere( $where, $offset, 200, 'id ASC' );
                foreach ( $batch as $t )
                {
                    $out['checked']++;
                    $r = $this->runTicket( $t, $now, $dryRun );
                    $out['steps'] += count( $r['steps'] );
                    $out['mails'] += $r['mails'];
                    if ( $r['raised'] )
                        $out[$r['level'] >= ticketsystemSla::BREACHED ? 'breached' : 'warned']++;
                    foreach ( $r['steps'] as $s )
                        $out['due'][] = (string)$t->attribute( 'number' ) . ' ' . $s['key'];
                }
                if ( count( $batch ) < 200 )
                    break;
            }
        }
        finally
        {
            if ( $lock )
            {
                flock( $lock, LOCK_UN );
                fclose( $lock );
            }
        }
        return $out;
    }

    /** One run at a time (a lock file in the cache directory); a handle, or false when another run holds it. */
    protected static function lock()
    {
        $dir = class_exists( 'eZSys' ) ? eZSys::cacheDirectory() : sys_get_temp_dir();
        if ( !is_dir( $dir ) )
            $dir = sys_get_temp_dir();
        $h = @fopen( rtrim( $dir, '/' ) . '/ticketsystem-sla.lock', 'c' );
        if ( !$h )
            return null;
        if ( !flock( $h, LOCK_EX | LOCK_NB ) )
        {
            fclose( $h );
            return false;
        }
        return $h;
    }

    /**
     * The due steps of one ticket, done in order.
     *
     * @return array steps (done or, dry, due), mails, level, raised
     */
    public function runTicket( ticketsystemTicket $t, $now, $dryRun = false )
    {
        $row = $t->values();
        $steps = $this->sla->steps( $row, $now, ticketsystemEscalationLog::keysFor( (int)$t->attribute( 'id' ) ) );
        $check = $this->sla->check( $row, $now );
        $out = array( 'steps' => array(), 'mails' => 0, 'level' => (int)$row['escalation'], 'raised' => false );
        if ( $dryRun )
        {
            $out['steps'] = $steps;
            return $out;
        }
        if ( $check['breached'] !== (int)$row['breached'] )
        {
            $t->setAttribute( 'breached', $check['breached'] );
            $t->store();
        }
        foreach ( $steps as $step )
        {
            $done = $this->perform( $t, $step, 0, $now );
            if ( $done === null )
                continue;
            $out['steps'][] = $step;
            $out['mails'] += $done['sent'] + $done['captured'];
        }
        $level = max( (int)$row['escalation'], $check['escalation'] );
        foreach ( $out['steps'] as $s )
            $level = max( $level, (int)$s['level'] );
        if ( $level > (int)$t->attribute( 'escalation' ) )
        {
            $t->setAttribute( 'escalation', $level );
            $t->store();
            $out['raised'] = true;
        }
        $out['level'] = $level;
        return $out;
    }

    /**
     * Escalates a ticket by hand (staff): one level up, the queue leads alerted and the ticket marked escalated.
     *
     * @return array ok, message, errors
     */
    public function manual( ticketsystemTicket $t, $actorId )
    {
        $old = (int)$t->attribute( 'escalation' );
        $level = min( ticketsystemSla::BREACHED, $old + 1 );
        if ( $level === $old )
            return array( 'ok' => true, 'message' => 'The ticket is escalated to the highest level already.', 'errors' => array() );
        $now = time();
        $key = 'manual:' . $level;
        $done = $this->perform( $t, array( 'key' => $key, 'target' => 'manual', 'percent' => 0, 'action' => 'leads', 'level' => $level ), $actorId, $now );
        if ( $done === null )
            return array( 'ok' => true, 'message' => 'The ticket was escalated already.', 'errors' => array() );
        $t->setAttribute( 'escalation', $level );
        $t->setAttribute( 'escalated', $now );
        $t->store();
        return array( 'ok' => true, 'message' => sprintf( 'The ticket was escalated; %d staff member(s) were alerted.', $done['sent'] + $done['captured'] ),
                      'errors' => array() );
    }

    /**
     * Runs one step: writes it to the log first (nothing when it ran already), then acts and records the counts.
     *
     * @return array|null recipients, sent, captured, failed; null when the step ran before
     */
    protected function perform( ticketsystemTicket $t, array $step, $actorId, $now )
    {
        $tid = (int)$t->attribute( 'id' );
        if ( ticketsystemEscalationLog::countBy( array( 'ticket_id' => $tid, 'step_key' => (string)$step['key'] ) ) > 0 )
            return null;
        $log = ticketsystemEscalationLog::make( array( 'ticket_id' => $tid, 'step_key' => (string)$step['key'], 'action' => (string)$step['action'],
                                                       'target' => (string)$step['target'], 'percent' => (int)$step['percent'],
                                                       'level' => (int)$step['level'], 'actor_id' => (int)$actorId, 'created' => (int)$now ) );
        $log->store();
        $counts = array( 'recipients' => 0, 'sent' => 0, 'captured' => 0, 'failed' => 0 );
        switch ( $step['action'] )
        {
            case 'assignee':
            case 'leads':
            case 'group':
                list( $ids, $addresses ) = $this->recipients( $t, $step['action'] );
                $counts = $this->mailer->staff( $t, $ids, $addresses, $this->subject( $t, $step ), $this->lines( $t, $step, $now ), $this->rows( $t ) );
                break;
            case 'raise':
                $this->raisePriority( $t );
                break;
            case 'mark':
                $t->setAttribute( 'escalated', (int)$now );
                $t->store();
                break;
        }
        $log->setAttribute( 'recipients', (int)$counts['recipients'] );
        $log->setAttribute( 'sent', (int)$counts['sent'] + (int)$counts['captured'] );
        $log->setAttribute( 'failed', (int)$counts['failed'] );
        $log->store();
        ticketsystemService::event( $t, (int)$actorId, 'escalate', (string)(int)$t->attribute( 'escalation' ), $step['level'] . ':' . $step['key'] );
        return $counts;
    }

    /**
     * Who an alert goes to: user ids and addresses.
     *
     * @return array( int[] user ids, string[] addresses )
     */
    public function recipients( ticketsystemTicket $t, $action )
    {
        $addresses = array_values( array_filter( array_map( 'trim', (array)$this->settings->value( 'SLASettings', 'AlertRecipients', array() ) ) ) );
        if ( $action === 'assignee' && (int)$t->attribute( 'assignee_id' ) > 0 )
            return array( array( (int)$t->attribute( 'assignee_id' ) ), array() );
        if ( $action !== 'group' )
        {
            $leads = $this->queueLeads( (string)$t->attribute( 'category' ) );
            if ( $leads )
                return array( $leads, $addresses );
        }
        return array( array_keys( $this->service->staffMembers() ), $addresses );
    }

    /** The user ids of a queue's leads: [SLASettings] QueueLeads[<category>] = object ids of users or user groups. */
    public function queueLeads( $category )
    {
        $map = (array)$this->settings->value( 'SLASettings', 'QueueLeads', array() );
        if ( !isset( $map[$category] ) )
            return array();
        $out = array();
        foreach ( preg_split( '/[\s,;]+/', (string)$map[$category], -1, PREG_SPLIT_NO_EMPTY ) as $id )
        {
            $o = eZContentObject::fetch( (int)$id );
            if ( !$o )
                continue;
            if ( eZUser::fetch( (int)$id ) instanceof eZUser )
            {
                $out[] = (int)$id;
                continue;
            }
            $node = $o->attribute( 'main_node' );
            if ( $node )
                foreach ( (array)eZContentObjectTreeNode::subTreeByNodeID( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'user' ),
                                                                                   'Limitation' => array(), 'IgnoreVisibility' => true ),
                                                                            (int)$node->attribute( 'node_id' ) ) as $n )
                    $out[] = (int)$n->attribute( 'contentobject_id' );
        }
        return array_values( array_unique( $out ) );
    }

    /** Raises the priority one step (the targets stay); nothing at the highest priority. */
    protected function raisePriority( ticketsystemTicket $t )
    {
        $prios = $this->settings->priorities();
        $keys = array_map( 'strval', array_keys( $prios ) );
        $i = array_search( (string)$t->attribute( 'priority' ), $keys, true );
        if ( $i === false || $i + 1 >= count( $keys ) )
            return false;
        $old = $keys[$i];
        $new = $keys[$i + 1];
        // the clocks of the targets keep the priority of the opening: sla_level is set, so state() reads the new
        // priority only for the clock, which stays the same unless the new priority runs around the clock
        $t->setAttribute( 'priority', $new );
        $t->setAttribute( 'priority_rank', $i + 2 );
        $t->setAttribute( 'modified', time() );
        $t->store();
        ticketsystemService::event( $t, 0, 'priority', $old, $new );
        $this->service->syncSelection( $t, 'priority', $prios[$new] );
        return true;
    }

    // -------------------------------------------------------------------------------------------- mails

    protected function targetLabel( $target )
    {
        return $target === 'response' ? 'first response' : ( $target === 'resolution' ? 'resolution' : 'SLA' );
    }

    protected function subject( ticketsystemTicket $t, array $step )
    {
        $n = (string)$t->attribute( 'number' );
        if ( $step['target'] === 'manual' )
            return sprintf( '[%s] Escalated by staff: %s', $n, (string)$t->attribute( 'subject' ) );
        $what = $step['percent'] >= 100 ? sprintf( 'SLA %s missed', $this->targetLabel( $step['target'] ) )
                                        : sprintf( 'SLA %s due soon', $this->targetLabel( $step['target'] ) );
        return sprintf( '[%s] %s: %s', $n, $what, (string)$t->attribute( 'subject' ) );
    }

    protected function lines( ticketsystemTicket $t, array $step, $now )
    {
        $n = (string)$t->attribute( 'number' );
        if ( $step['target'] === 'manual' )
            return array( sprintf( 'Ticket %s was escalated by staff to level %d.', $n, (int)$step['level'] ),
                          'Please look at it now.' );
        $s = $this->sla->state( $t->values(), $now );
        $target = $step['target'] === 'response' ? $s['response'] : $s['resolution'];
        $clock = $this->sla->clockFor( $t->values(), $step['target'] === 'response' ? 'FirstResponse' : 'Resolution' );
        $due = $target['due'] ? ( new DateTimeImmutable( '@' . (int)$target['due'] ) )->setTimezone( new DateTimeZone( $clock->timezone() ) )->format( 'D j M Y, H:i T' ) : '';
        $first = $step['percent'] >= 100
            ? sprintf( 'Ticket %s missed its %s target.', $n, $this->targetLabel( $step['target'] ) )
            : sprintf( 'Ticket %s has used %d %% of its %s time.', $n, (int)$target['pct'], $this->targetLabel( $step['target'] ) );
        $lines = array( $first );
        if ( $due !== '' )
            $lines[] = sprintf( 'Due: %s (%s).', $due, ticketsystemDesk::left( (int)$target['left'] ) . ( $clock->isCalendar() ? '' : ' in business hours' ) );
        $lines[] = sprintf( 'Escalation step: %s.', $this->actionLabel( $step['action'] ) );
        return $lines;
    }

    protected function actionLabel( $action )
    {
        $l = array( 'assignee' => 'the assignee is alerted', 'leads' => 'the queue leads are alerted', 'group' => 'the support group is alerted' );
        return isset( $l[$action] ) ? $l[$action] : $action;
    }

    protected function rows( ticketsystemTicket $t )
    {
        $prios = $this->settings->priorities();
        $cats = $this->settings->categories();
        $aid = (int)$t->attribute( 'assignee_id' );
        $assignee = $aid ? eZContentObject::fetch( $aid ) : null;
        return array(
            array( 'label' => 'SLA level', 'value' => $this->sla->levelLabel( (string)$t->attribute( 'sla_level' ) ) ),
            array( 'label' => 'Priority', 'value' => isset( $prios[$t->attribute( 'priority' )] ) ? $prios[$t->attribute( 'priority' )] : (string)$t->attribute( 'priority' ) ),
            array( 'label' => 'Queue', 'value' => isset( $cats[$t->attribute( 'category' )] ) ? $cats[$t->attribute( 'category' )] : (string)$t->attribute( 'category' ) ),
            array( 'label' => 'Assigned to', 'value' => $assignee ? (string)$assignee->attribute( 'name' ) : 'nobody' ),
        );
    }

    // ------------------------------------------------------------------------------------------- reads

    /**
     * The escalation log of a ticket, oldest first, for staff: action, target, percent, level, actor, sent, created.
     *
     * @return array[]
     */
    public static function logFor( $ticketId )
    {
        $out = array();
        foreach ( ticketsystemEscalationLog::fetchBy( array( 'ticket_id' => (int)$ticketId ), array( 'created' => 'asc', 'id' => 'asc' ) ) as $row )
            $out[] = $row->values();
        return $out;
    }

    /** The latest steps of every ticket, newest first (the staff page). */
    public static function recent( $limit = 50 )
    {
        $out = array();
        foreach ( ticketsystemEscalationLog::fetchBy( array(), array( 'created' => 'desc', 'id' => 'desc' ), 0, max( 1, (int)$limit ) ) as $row )
            $out[] = $row->values();
        return $out;
    }

    /**
     * The SLA board of staff: the active tickets with a target the reader may read, the most urgent first, and the
     * counts per state (on track, due soon, breached, paused).
     *
     * @param string $state '' (all), warn, breached, paused, ok
     * @return array counts (state => n), tickets (number, subject, priority, category, state, label, tone, due, left,
     *               text, target, escalation, escalated, assignee_id, level), total
     */
    public static function board( ticketsystemAccess $access, ?ticketsystemSettings $settings = null, $state = '', $limit = 100, $now = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        $sla = new ticketsystemSla( $settings );
        $now = $now === null ? time() : (int)$now;
        $out = array( 'counts' => array( 'ok' => 0, 'warn' => 0, 'breached' => 0, 'paused' => 0 ), 'tickets' => array(), 'total' => 0,
                      'enabled' => $sla->enabled() );
        $where = $access->listConditions( $settings->statusSet()->active() );
        if ( $where === null || !$sla->enabled() )
            return $out;
        $where = ( $where === '1=1' ? '' : $where . ' AND ' ) . '( response_due > 0 OR resolve_due > 0 )';
        $prios = $settings->priorities();
        $cats = $settings->categories();
        $rows = array();
        for ( $offset = 0; $offset < 2000; $offset += 500 )
        {
            $batch = ticketsystemTicket::fetchWhere( $where, $offset, 500, 'id ASC' );
            foreach ( $batch as $t )
            {
                $v = $t->values();
                $s = $sla->state( $v, $now );
                // the target that matters now: the first response until it is met, then the resolution
                $key = in_array( $s['response']['state'], array( 'none', 'met' ), true ) ? 'resolution' : 'response';
                $target = $s[$key];
                $st = $target['state'] === 'none' || $target['state'] === 'met' ? $s['worst'] : $target['state'];
                if ( isset( $out['counts'][$st] ) )
                    $out['counts'][$st]++;
                if ( $state !== '' && $st !== $state )
                    continue;
                $rows[] = array( 'id' => (int)$v['id'], 'number' => $v['number'], 'subject' => $v['subject'],
                                 'priority' => $v['priority'], 'priority_label' => isset( $prios[$v['priority']] ) ? $prios[$v['priority']] : $v['priority'],
                                 'category' => $v['category'], 'category_label' => isset( $cats[$v['category']] ) ? $cats[$v['category']] : $v['category'],
                                 'state' => $st, 'label' => ticketsystemSla::stateLabel( $st ),
                                 'tone' => $st === 'breached' ? 'bad' : ( $st === 'warn' ? 'warn' : ( $st === 'ok' ? 'ok' : 'info' ) ),
                                 'target' => $key, 'target_label' => $key === 'response' ? 'First response' : 'Resolution',
                                 'due' => (int)$target['due'], 'left' => (int)$target['left'], 'pct' => (int)$target['pct'],
                                 'text' => ticketsystemDesk::left( (int)$target['left'] ), 'escalation' => (int)$v['escalation'],
                                 'escalated' => (int)$v['escalated'], 'assignee_id' => (int)$v['assignee_id'],
                                 'level' => $v['sla_level'], 'level_label' => $sla->levelLabel( $v['sla_level'] ) );
            }
            if ( count( $batch ) < 500 )
                break;
        }
        usort( $rows, function ( $a, $b ) { return $a['left'] === $b['left'] ? $a['id'] - $b['id'] : ( $a['left'] < $b['left'] ? -1 : 1 ); } );
        $out['total'] = count( $rows );
        $out['tickets'] = array_slice( $rows, 0, max( 1, (int)$limit ) );
        return $out;
    }
}
