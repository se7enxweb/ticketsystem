<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Reports and the staff search (doc/reports.md), always within the reader's read scope (ticketsystemAccess). The
 * figures are counted in PHP from single-table queries, so every database of the kernel answers them.
 *
 * Internal notes are searched only for staff (ticketsystem/manage); the CSV export never carries a note or the text
 * of a post.
 */
class ticketsystemReport
{
    /** @var ticketsystemAccess */
    protected $access;
    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ticketsystemAccess $access, ?ticketsystemSettings $settings = null )
    {
        $this->access = $access;
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    /** The tickets opened in a period within the reader's scope (at most $max). */
    public function opened( $from, $to, $max = 5000 )
    {
        $base = $this->access->listConditions();
        if ( $base === null )
            return array();
        $where = ( $base !== '1=1' ? '(' . $base . ') AND ' : '' ) . 'created >= ' . (int)$from . ' AND created < ' . (int)$to;
        return ticketsystemTicket::fetchWhere( $where, 0, $max, 'created ASC, id ASC' );
    }

    /**
     * The figures of a period (pure on the rows given).
     *
     * @param array[] $rows ticketsystemTicket::values() of the tickets opened in the period
     * @param array $done the done statuses
     * @param int $warnPercent unused here, kept for the views
     * @return array opened, resolved, active, unassigned, escalated, breached, by_status, by_category, by_priority,
     *               by_assignee, first_response (count, avg_minutes, median_minutes), sla (response_met, response_total,
     *               resolution_met, resolution_total), csat (count, avg, dist 1..5)
     */
    public static function figures( array $rows, array $done, $now )
    {
        $out = array( 'opened' => count( $rows ), 'resolved' => 0, 'active' => 0, 'unassigned' => 0, 'escalated' => 0, 'breached' => 0,
                      'by_status' => array(), 'by_category' => array(), 'by_priority' => array(), 'by_assignee' => array(),
                      'first_response' => array( 'count' => 0, 'avg_minutes' => 0, 'median_minutes' => 0 ),
                      'resolution' => array( 'count' => 0, 'avg_hours' => 0 ),
                      'sla' => array( 'response_met' => 0, 'response_total' => 0, 'resolution_met' => 0, 'resolution_total' => 0 ),
                      'csat' => array( 'count' => 0, 'avg' => 0, 'dist' => array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ) ) );
        $responses = array();
        $resolutions = array();
        $scores = array();
        foreach ( $rows as $r )
        {
            $isDone = in_array( (string)$r['status'], $done, true );
            $out[$isDone ? 'resolved' : 'active']++;
            if ( !$isDone && !(int)$r['assignee_id'] )
                $out['unassigned']++;
            if ( (int)$r['escalation'] >= 1 )
                $out['escalated']++;
            if ( (int)$r['breached'] > 0 )
                $out['breached']++;
            foreach ( array( 'by_status' => 'status', 'by_category' => 'category', 'by_priority' => 'priority', 'by_assignee' => 'assignee_id' ) as $k => $f )
            {
                $v = (string)$r[$f];
                $out[$k][$v] = ( isset( $out[$k][$v] ) ? $out[$k][$v] : 0 ) + 1;
            }
            if ( (int)$r['responded'] > 0 )
                $responses[] = max( 0, (int)$r['responded'] - (int)$r['created'] ) / 60;
            $resolvedAt = (int)$r['resolved'] ?: ( (int)$r['closed'] ?: 0 );
            if ( $isDone && $resolvedAt > 0 )
                $resolutions[] = max( 0, $resolvedAt - (int)$r['created'] ) / 3600;
            if ( (int)$r['response_due'] > 0 && ( (int)$r['responded'] > 0 || $now > (int)$r['response_due'] ) )
            {
                $out['sla']['response_total']++;
                if ( (int)$r['responded'] > 0 && (int)$r['responded'] <= (int)$r['response_due'] )
                    $out['sla']['response_met']++;
            }
            if ( (int)$r['resolve_due'] > 0 && ( $resolvedAt > 0 || $now > (int)$r['resolve_due'] ) )
            {
                $out['sla']['resolution_total']++;
                if ( $resolvedAt > 0 && $resolvedAt <= (int)$r['resolve_due'] )
                    $out['sla']['resolution_met']++;
            }
            if ( (int)$r['rating'] >= 1 && (int)$r['rating'] <= 5 )
            {
                $scores[] = (int)$r['rating'];
                $out['csat']['dist'][(int)$r['rating']]++;
            }
        }
        if ( $responses )
        {
            sort( $responses );
            $out['first_response'] = array( 'count' => count( $responses ), 'avg_minutes' => (int)round( array_sum( $responses ) / count( $responses ) ),
                                             'median_minutes' => (int)round( $responses[(int)floor( ( count( $responses ) - 1 ) / 2 )] ) );
        }
        if ( $resolutions )
            $out['resolution'] = array( 'count' => count( $resolutions ), 'avg_hours' => round( array_sum( $resolutions ) / count( $resolutions ), 1 ) );
        if ( $scores )
            $out['csat']['count'] = count( $scores );
        $out['csat']['avg'] = $scores ? round( array_sum( $scores ) / count( $scores ), 2 ) : 0;
        foreach ( array( 'by_status', 'by_category', 'by_priority', 'by_assignee' ) as $k )
            arsort( $out[$k] );
        return $out;
    }

    /** The report of the last $days days (or $from..$to). */
    public function period( $from, $to )
    {
        $rows = array();
        foreach ( $this->opened( $from, $to ) as $t )
            $rows[] = $t->values();
        return self::figures( $rows, $this->settings->statusSet()->done, time() );
    }

    /** The CSV of the tickets opened in a period: index data only, never a note, never the text of a post. */
    public function csv( $from, $to )
    {
        $set = $this->settings->statusSet();
        $cats = $this->settings->categories();
        $prios = $this->settings->priorities();
        $fh = fopen( 'php://temp', 'w+' );
        fputcsv( $fh, array( 'number', 'subject', 'account', 'status', 'queue', 'priority', 'assignee', 'created', 'first_response_minutes',
                             'resolved', 'sla_level', 'escalation', 'breached', 'rating', 'posts', 'source' ) );
        $names = array();
        foreach ( $this->opened( $from, $to ) as $t )
        {
            $r = $t->values();
            $aid = (int)$r['assignee_id'];
            if ( $aid && !isset( $names[$aid] ) )
            {
                $o = eZContentObject::fetch( $aid );
                $names[$aid] = $o ? (string)$o->attribute( 'name' ) : '#' . $aid;
            }
            fputcsv( $fh, array( $r['number'], self::csvSafe( $r['subject'] ), ticketsystemAccount::label( $r['account_key'] ), $set->label( $r['status'] ),
                                 isset( $cats[$r['category']] ) ? $cats[$r['category']] : $r['category'],
                                 isset( $prios[$r['priority']] ) ? $prios[$r['priority']] : $r['priority'], $aid ? $names[$aid] : '',
                                 date( 'c', (int)$r['created'] ), (int)$r['responded'] ? (int)round( ( (int)$r['responded'] - (int)$r['created'] ) / 60 ) : '',
                                 (int)$r['resolved'] ? date( 'c', (int)$r['resolved'] ) : '', $r['sla_level'], (int)$r['escalation'], (int)$r['breached'],
                                 (int)$r['rating'] ?: '', (int)$r['post_count'], $r['source'] ) );
        }
        rewind( $fh );
        $out = stream_get_contents( $fh );
        fclose( $fh );
        return $out;
    }

    /** A cell that a spreadsheet would read as a formula gets a leading apostrophe (CSV injection). */
    public static function csvSafe( $v )
    {
        $v = (string)$v;
        return $v !== '' && strpos( '=+-@', $v[0] ) !== false ? "'" . $v : $v;
    }

    /**
     * The staff search: the number, the subject and the text of every customer-visible post; the internal notes too
     * for staff only. Within the reader's read scope.
     *
     * @return array total, tickets (ticketsystemTicket[]), in_notes (ticket id => true where only a note matched)
     */
    public function search( $q, $offset = 0, $limit = 50 )
    {
        $q = trim( (string)$q );
        $base = $this->access->listConditions();
        if ( $base === null || $q === '' )
            return array( 'total' => 0, 'tickets' => array(), 'in_notes' => array() );
        $quote = ticketsystemDesk::quoter();
        $noteIds = array();
        if ( preg_match( ticketsystemDesk::NUMBER_PATTERN, $q ) )
            $match = 'number = ' . $quote( strtoupper( $q ) );
        else
        {
            $like = ticketsystemDesk::likePattern( $q );
            if ( $like === '' )
                return array( 'total' => 0, 'tickets' => array(), 'in_notes' => array() );
            $or = array( 'LOWER(subject) LIKE ' . $quote( $like ),
                         'id IN (SELECT ticket_id FROM ticketsystem_post WHERE LOWER(body) LIKE ' . $quote( $like ) . ')' );
            if ( $this->access->isStaff() )
            {
                $rows = eZDB::instance()->arrayQuery( 'SELECT ticket_id FROM ticketsystem_note WHERE LOWER(body) LIKE ' . $quote( $like ), array( 'limit' => 1000 ) );
                foreach ( is_array( $rows ) ? $rows : array() as $r )
                    $noteIds[(int)$r['ticket_id']] = true;
                if ( $noteIds )
                    $or[] = 'id IN (' . implode( ',', array_keys( $noteIds ) ) . ')';
            }
            $match = '(' . implode( ' OR ', $or ) . ')';
        }
        $where = ( $base !== '1=1' ? '(' . $base . ') AND ' : '' ) . $match;
        $tickets = ticketsystemTicket::fetchWhere( $where, $offset, $limit, 'last_post DESC, id DESC' );
        // staff see which tickets an internal note matched (a badge "in a note")
        $inNotes = array();
        foreach ( $tickets as $t )
            if ( isset( $noteIds[(int)$t->attribute( 'id' )] ) )
                $inNotes[(int)$t->attribute( 'id' )] = true;
        return array( 'total' => ticketsystemTicket::countWhere( $where ), 'tickets' => $tickets, 'in_notes' => $inNotes );
    }
}
