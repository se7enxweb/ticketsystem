<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Time tracking (doc/time-tracking.md, [TimeSettings]): staff record the minutes they spent with a reply or an internal
 * note, billable or not. When the ticket's account has an active SLA or warranty (the Katalyst entitlements, when
 * Katalyst is present) the time is "covered" and not billable by default; staff can still mark it billable (work
 * outside what the SLA or warranty covers). The ticket keeps the sums (time_minutes, billable_minutes). The report and
 * its CSV list the entries within the reader's read scope. Billing itself is not done here: an integration (Katalyst's
 * katalyst:tickets:bill-time) reads unbilled() and marks what it billed with markBilled().
 *
 * Customers never see time entries.
 */
class ticketsystemTime
{
    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ?ticketsystemSettings $settings = null )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    public function enabled()
    {
        return $this->settings->enabled( 'TimeSettings', 'Enabled', 'enabled' );
    }

    public function maxMinutes()
    {
        return max( 1, (int)$this->settings->value( 'TimeSettings', 'MaxMinutes', 1440 ) );
    }

    /** Minutes are rounded up to this step (1 = exact). */
    public function increment()
    {
        return max( 1, (int)$this->settings->value( 'TimeSettings', 'Increment', 1 ) );
    }

    /**
     * Minutes from what staff typed (pure): "45", "45m", "1:30", "1h30", "1h 30m", "1.5h", "2 h". 0 for nothing, -1 for
     * anything else.
     */
    public static function parseMinutes( $text )
    {
        $t = strtolower( trim( (string)$text ) );
        if ( $t === '' || $t === '0' )
            return 0;
        $t = str_replace( ',', '.', preg_replace( '/\s+/', '', $t ) );
        if ( preg_match( '/^\d{1,5}$/D', $t ) )
            return (int)$t;
        if ( preg_match( '/^(\d{1,3}):([0-5]\d)$/D', $t, $m ) )
            return (int)$m[1] * 60 + (int)$m[2];
        if ( preg_match( '/^(\d{1,5})(m|min|mins|minutes?)$/D', $t, $m ) )
            return (int)$m[1];
        if ( preg_match( '/^(\d{1,3}(?:\.\d{1,2})?)(h|hr|hrs|hours?)$/D', $t, $m ) )
            return (int)round( (float)$m[1] * 60 );
        if ( preg_match( '/^(\d{1,3})(h|hr|hrs|hours?)(\d{1,2})(m|min|mins|minutes?)?$/D', $t, $m ) && (int)$m[3] < 60 )
            return (int)$m[1] * 60 + (int)$m[3];
        return -1;
    }

    /** Rounds minutes up to a step (pure). */
    public static function roundUp( $minutes, $step )
    {
        $step = max( 1, (int)$step );
        return (int)( ceil( max( 0, (int)$minutes ) / $step ) * $step );
    }

    /** "1 h 30 min", "45 min", "2 h" (pure). */
    public static function label( $minutes )
    {
        $minutes = max( 0, (int)$minutes );
        $h = intdiv( $minutes, 60 );
        $m = $minutes % 60;
        if ( $h && $m )
            return sprintf( '%d h %d min', $h, $m );
        return $h ? sprintf( '%d h', $h ) : sprintf( '%d min', $m );
    }

    /**
     * The SLA or warranty level that covers an account now ('' when none): an active Katalyst entitlement of the
     * customer (for the ticket's subscription, or one for the whole account). Without Katalyst: [TimeSettings]
     * CoveredLevels[] against the ticket's SLA level.
     */
    public function coveredBy( $accountKey, $subscriptionId = 0, $slaLevel = '' )
    {
        $levels = (array)$this->settings->value( 'TimeSettings', 'CoveredLevels', array() );
        if ( (string)$slaLevel !== '' && in_array( (string)$slaLevel, $levels, true ) )
            return (string)$slaLevel;
        if ( !preg_match( '/^katalyst:(\d+)$/', (string)$accountKey, $m ) || !ticketsystemAccount::katalystAvailable()
             || !class_exists( 'expKatalystEntitlement' ) || !$this->settings->enabled( 'TimeSettings', 'KatalystEntitlements', 'enabled' ) )
            return '';
        try
        {
            foreach ( (array)eZPersistentObject::fetchObjectList( expKatalystEntitlement::definition(), null,
                          array( 'customer_id' => (int)$m[1], 'status' => 'active' ), null, null, false ) as $e )
            {
                if ( !in_array( (string)$e['kind'], array( 'sla', 'warranty' ), true ) )
                    continue;
                if ( (int)$e['subscription_id'] > 0 && (int)$e['subscription_id'] !== (int)$subscriptionId )
                    continue;
                if ( (string)$e['ends'] !== '' && strcmp( (string)$e['ends'], date( 'Y-m-d' ) ) < 0 )
                    continue;
                return (string)$e['kind'] . ':' . (string)$e['level'];
            }
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'coverage of ' . $accountKey . ': ' . $e->getMessage(), __METHOD__ );
        }
        return '';
    }

    /** Whether new time on a ticket is billable unless staff say otherwise: not covered, and [TimeSettings] DefaultBillable. */
    public function defaultBillable( ticketsystemTicket $t )
    {
        $mode = (string)$this->settings->value( 'TimeSettings', 'DefaultBillable', 'uncovered' );
        if ( $mode === 'never' )
            return false;
        if ( $mode === 'always' )
            return true;
        return $this->coveredBy( (string)$t->attribute( 'account_key' ), (int)$t->attribute( 'subscription_id' ), (string)$t->attribute( 'sla_level' ) ) === '';
    }

    /**
     * Checks what staff typed (pure apart from the settings): array( error ('' = fine), minutes ). 0 minutes = nothing to
     * record.
     */
    public function check( $text )
    {
        $min = self::parseMinutes( $text );
        if ( $min < 0 )
            return array( 'Please give the time as minutes (45), hours and minutes (1:30, 1h 30m) or hours (1.5h).', 0 );
        if ( $min > $this->maxMinutes() )
            return array( sprintf( 'At most %s can be recorded at once.', self::label( $this->maxMinutes() ) ), 0 );
        return array( '', $min ? self::roundUp( $min, $this->increment() ) : 0 );
    }

    /**
     * Records time on a ticket (staff only; the caller checked the reader). $billable null = the default of the ticket.
     *
     * @return ticketsystemTimeEntry|null null for 0 minutes
     */
    public function record( ticketsystemTicket $t, $userId, $minutes, $billable = null, $postId = 0, $noteId = 0, $remark = '', $workDate = null )
    {
        $minutes = (int)$minutes;
        if ( $minutes <= 0 )
            return null;
        $covered = $this->coveredBy( (string)$t->attribute( 'account_key' ), (int)$t->attribute( 'subscription_id' ), (string)$t->attribute( 'sla_level' ) );
        if ( $billable === null )
            $billable = $this->defaultBillable( $t );
        $e = ticketsystemTimeEntry::make( array(
            'ticket_id' => (int)$t->attribute( 'id' ), 'post_id' => (int)$postId, 'note_id' => (int)$noteId, 'user_id' => (int)$userId,
            'minutes' => $minutes, 'billable' => $billable ? 1 : 0, 'covered' => $covered,
            'work_date' => $workDate === null ? time() : (int)$workDate, 'remark' => mb_substr( trim( (string)$remark ), 0, 255 ),
            'account_key' => (string)$t->attribute( 'account_key' ), 'created' => time(), 'billed' => 0, 'billing_ref' => '' ) );
        $e->store();
        self::resum( $t );
        ticketsystemService::event( $t, (int)$userId, 'time', '', $minutes . ( $billable ? ' billable' : '' ) );
        return $e;
    }

    /** Removes an entry that was not billed yet; true when removed. */
    public function remove( ticketsystemTimeEntry $e, ticketsystemTicket $t, $actorId )
    {
        if ( (int)$e->attribute( 'billed' ) > 0 || (int)$e->attribute( 'ticket_id' ) !== (int)$t->attribute( 'id' ) )
            return false;
        $minutes = (int)$e->attribute( 'minutes' );
        $e->remove();
        self::resum( $t );
        ticketsystemService::event( $t, (int)$actorId, 'untime', (string)$minutes, '' );
        return true;
    }

    /** Sets the ticket's sums from its entries (and stores the ticket). */
    public static function resum( ticketsystemTicket $t )
    {
        $all = 0;
        $bill = 0;
        foreach ( ticketsystemTimeEntry::fetchBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ), null, 0, 5000 ) as $e )
        {
            $all += (int)$e->attribute( 'minutes' );
            if ( (int)$e->attribute( 'billable' ) )
                $bill += (int)$e->attribute( 'minutes' );
        }
        $t->setAttribute( 'time_minutes', $all );
        $t->setAttribute( 'billable_minutes', $bill );
        $t->store();
    }

    /** The entries of a ticket for staff templates, oldest first. */
    public function entries( ticketsystemTicket $t )
    {
        $names = array();
        $out = array();
        foreach ( ticketsystemTimeEntry::fetchBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ), array( 'work_date' => 'asc', 'id' => 'asc' ), 0, 1000 ) as $e )
            $out[] = $this->row( $e, $names );
        return $out;
    }

    protected function row( ticketsystemTimeEntry $e, array &$names, array $tickets = array() )
    {
        $uid = (int)$e->attribute( 'user_id' );
        if ( !isset( $names[$uid] ) )
        {
            $o = eZContentObject::fetch( $uid );
            $names[$uid] = $o ? (string)$o->attribute( 'name' ) : '#' . $uid;
        }
        $tid = (int)$e->attribute( 'ticket_id' );
        return array( 'id' => (int)$e->attribute( 'id' ), 'ticket_id' => $tid, 'number' => isset( $tickets[$tid] ) ? $tickets[$tid] : '',
                      'user_id' => $uid, 'user' => $names[$uid], 'minutes' => (int)$e->attribute( 'minutes' ),
                      'label' => self::label( (int)$e->attribute( 'minutes' ) ), 'billable' => (bool)$e->attribute( 'billable' ),
                      'covered' => (string)$e->attribute( 'covered' ), 'work_date' => (int)$e->attribute( 'work_date' ),
                      'remark' => (string)$e->attribute( 'remark' ), 'post_id' => (int)$e->attribute( 'post_id' ),
                      'note_id' => (int)$e->attribute( 'note_id' ), 'account_key' => (string)$e->attribute( 'account_key' ),
                      'account_label' => ticketsystemAccount::label( (string)$e->attribute( 'account_key' ) ),
                      'billed' => (int)$e->attribute( 'billed' ), 'billing_ref' => (string)$e->attribute( 'billing_ref' ) );
    }

    /**
     * The filter of the report from view parameters (pure): from, to (YYYY-MM-DD; default: this month), account (an
     * account key), user (an id), billable (all|yes|no), billed (all|yes|no).
     */
    public static function filter( array $p, $now = null )
    {
        $now = $now === null ? time() : (int)$now;
        $date = function ( $v ) { return is_string( $v ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $v ) && strtotime( $v . ' 00:00:00' ) ? $v : ''; };
        $f = array( 'from' => $date( isset( $p['from'] ) ? $p['from'] : '' ), 'to' => $date( isset( $p['to'] ) ? $p['to'] : '' ),
                    'account' => isset( $p['account'] ) && preg_match( '/^[a-z]{1,16}:\d{1,12}$/D', (string)$p['account'] ) ? (string)$p['account'] : '',
                    'user' => isset( $p['user'] ) && ctype_digit( (string)$p['user'] ) ? (int)$p['user'] : 0,
                    'billable' => isset( $p['billable'] ) && in_array( $p['billable'], array( 'yes', 'no' ), true ) ? $p['billable'] : 'all',
                    'billed' => isset( $p['billed'] ) && in_array( $p['billed'], array( 'yes', 'no' ), true ) ? $p['billed'] : 'all' );
        if ( $f['from'] === '' )
            $f['from'] = date( 'Y-m-01', $now );
        if ( $f['to'] === '' )
            $f['to'] = date( 'Y-m-d', $now );
        if ( strcmp( $f['from'], $f['to'] ) > 0 )
            list( $f['from'], $f['to'] ) = array( $f['to'], $f['from'] );
        return $f;
    }

    /**
     * The SQL condition of a filter (every value cast or escaped). $ticketIds: the tickets the reader may read (null =
     * every ticket); a plain IN list, no subquery, so the MongoDB driver's SQL layer reads it as well.
     */
    public static function where( array $f, ?array $ticketIds = null )
    {
        $db = eZDB::instance();
        $parts = array( 'work_date >= ' . (int)strtotime( $f['from'] . ' 00:00:00' ), 'work_date < ' . (int)( strtotime( $f['to'] . ' 00:00:00' ) + 86400 ) );
        if ( $f['account'] !== '' )
            $parts[] = "account_key = '" . $db->escapeString( $f['account'] ) . "'";
        if ( $f['user'] )
            $parts[] = 'user_id = ' . (int)$f['user'];
        if ( $f['billable'] !== 'all' )
            $parts[] = 'billable = ' . ( $f['billable'] === 'yes' ? 1 : 0 );
        if ( $f['billed'] !== 'all' )
            $parts[] = $f['billed'] === 'yes' ? 'billed > 0' : 'billed = 0';
        if ( $ticketIds !== null )
            $parts[] = ticketsystemReport::inList( 'ticket_id', $ticketIds );
        return implode( ' AND ', $parts );
    }

    /** The ids of the tickets a read scope reaches (null for every ticket). */
    public static function ticketIds( $scope )
    {
        $scope = trim( (string)$scope );
        if ( $scope === '' || $scope === '1=1' )
            return null;
        $ids = array();
        foreach ( (array)eZDB::instance()->arrayQuery( 'SELECT id FROM ticketsystem_ticket WHERE ' . $scope ) as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $ids[] = (int)$row['id'];
        }
        return $ids;
    }

    /**
     * The report: the entries of a filter within the reader's scope, newest first, and the sums (all, billable,
     * covered, billed; per account and per staff member).
     *
     * @return array rows, total, sums
     */
    public function report( ticketsystemAccess $access, array $f, $limit = 2000 )
    {
        $out = array( 'rows' => array(), 'total' => 0, 'sums' => array( 'minutes' => 0, 'billable' => 0, 'covered' => 0, 'billed' => 0,
                                                                         'accounts' => array(), 'users' => array() ) );
        $scope = $access->listConditions();
        if ( $scope === null )
            return $out;
        $db = eZDB::instance();
        $rows = $db->arrayQuery( 'SELECT * FROM ticketsystem_time WHERE ' . self::where( $f, self::ticketIds( $scope ) ) . ' ORDER BY work_date DESC, id DESC',
                                 array( 'offset' => 0, 'limit' => max( 1, (int)$limit ) ) );
        $entries = eZPersistentObject::handleRows( is_array( $rows ) ? $rows : array(), 'ticketsystemTimeEntry', true );
        $tickets = array();
        foreach ( $entries as $e )
            $tickets[(int)$e->attribute( 'ticket_id' )] = '';
        foreach ( array_keys( $tickets ) as $id )
        {
            $t = ticketsystemTicket::fetch( $id );
            $tickets[$id] = $t ? (string)$t->attribute( 'number' ) : '#' . $id;
        }
        $names = array();
        foreach ( $entries as $e )
        {
            $r = $this->row( $e, $names, $tickets );
            $out['rows'][] = $r;
            $s =& $out['sums'];
            $s['minutes'] += $r['minutes'];
            if ( $r['billable'] )
                $s['billable'] += $r['minutes'];
            if ( $r['covered'] !== '' )
                $s['covered'] += $r['minutes'];
            if ( $r['billed'] )
                $s['billed'] += $r['minutes'];
            foreach ( array( 'accounts' => $r['account_label'], 'users' => $r['user'] ) as $k => $label )
            {
                if ( !isset( $s[$k][$label] ) )
                    $s[$k][$label] = array( 'label' => $label, 'minutes' => 0, 'billable' => 0 );
                $s[$k][$label]['minutes'] += $r['minutes'];
                if ( $r['billable'] )
                    $s[$k][$label]['billable'] += $r['minutes'];
            }
            unset( $s );
        }
        $out['total'] = count( $out['rows'] );
        foreach ( array( 'accounts', 'users' ) as $k )
        {
            $out['sums'][$k] = array_values( $out['sums'][$k] );
            foreach ( $out['sums'][$k] as &$x )
            {
                $x['label_time'] = self::label( $x['minutes'] );
                $x['label_billable'] = self::label( $x['billable'] );
            }
            unset( $x );
        }
        foreach ( array( 'minutes', 'billable', 'covered', 'billed' ) as $k )
            $out['sums'][$k . '_label'] = self::label( $out['sums'][$k] );
        return $out;
    }

    /** The CSV of report rows (pure): one line per entry; never a post's text, never a name of a customer. */
    public static function csv( array $rows )
    {
        $h = fopen( 'php://temp', 'w+' );
        fputcsv( $h, array( 'date', 'ticket', 'account', 'staff', 'minutes', 'hours', 'billable', 'covered_by', 'billed', 'billing_ref', 'remark' ), ',', '"', '' );
        foreach ( $rows as $r )
        {
            $remark = (string)$r['remark'];
            // a spreadsheet must not read a remark as a formula
            if ( $remark !== '' && strpos( '=+-@', $remark[0] ) !== false )
                $remark = "'" . $remark;
            fputcsv( $h, array( date( 'Y-m-d', (int)$r['work_date'] ), $r['number'], $r['account_key'], $r['user'], (int)$r['minutes'],
                                sprintf( '%.2f', (int)$r['minutes'] / 60 ), $r['billable'] ? 'yes' : 'no', $r['covered'],
                                $r['billed'] ? date( 'Y-m-d', (int)$r['billed'] ) : '', $r['billing_ref'], $remark ), ',', '"', '' );
        }
        rewind( $h );
        $csv = stream_get_contents( $h );
        fclose( $h );
        return $csv;
    }

    /**
     * Billable time not billed yet (for a billing integration), oldest first; optionally of one account and up to a day.
     *
     * @return ticketsystemTimeEntry[]
     */
    public static function unbilled( $accountKey = '', $until = 0, $limit = 5000 )
    {
        $db = eZDB::instance();
        $where = 'billable = 1 AND billed = 0';
        if ( (string)$accountKey !== '' )
            $where .= " AND account_key = '" . $db->escapeString( (string)$accountKey ) . "'";
        if ( (int)$until > 0 )
            $where .= ' AND work_date < ' . (int)$until;
        $rows = $db->arrayQuery( 'SELECT * FROM ticketsystem_time WHERE ' . $where . ' ORDER BY work_date ASC, id ASC',
                                 array( 'offset' => 0, 'limit' => max( 1, (int)$limit ) ) );
        return eZPersistentObject::handleRows( is_array( $rows ) ? $rows : array(), 'ticketsystemTimeEntry', true );
    }

    /** Marks entries billed (now) with a reference; entries billed before are left as they are. Returns how many. */
    public static function markBilled( array $ids, $reference, $now = null )
    {
        $n = 0;
        foreach ( array_unique( array_map( 'intval', $ids ) ) as $id )
        {
            $e = ticketsystemTimeEntry::fetchByID( $id );
            if ( !$e || (int)$e->attribute( 'billed' ) > 0 || !(int)$e->attribute( 'billable' ) )
                continue;
            $e->setAttribute( 'billed', $now === null ? time() : (int)$now );
            $e->setAttribute( 'billing_ref', mb_substr( (string)$reference, 0, 64 ) );
            $e->store();
            $n++;
        }
        return $n;
    }
}
