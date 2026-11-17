<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Reports and the search of the help desk (doc/reports.md, doc/search.md), always within the reader's read scope
 * (ticketsystemAccess: Own, Account or All).
 *
 * Every query is a single-table SELECT with plain comparisons, IN lists and LIKE on LOWER(column), and every figure is
 * counted in PHP, so the five databases of the kernel (MySQL/MariaDB, PostgreSQL, SQLite, Oracle, MongoDB) answer
 * them the same way: no GROUP BY, no JOIN, no sub-query, no full-text syntax of one engine.
 *
 * Internal notes are searched only for staff (ticketsystem/manage); the CSV export never carries a note or the text
 * of a post, and every cell that a spreadsheet would read as a formula is defused (csvSafe()).
 */
class ticketsystemReport
{
    /** The longest report period in days; [ReportSettings] MaxDays can lower it. */
    const MAX_DAYS = 1100;
    /** At most this many ids in one IN list (Oracle refuses more than 1000). */
    const IN_CHUNK = 500;
    /** The age buckets of the backlog: label => upper bound in seconds. */
    const BACKLOG_BUCKETS = array( 'under_1d' => 86400, '1_3d' => 259200, '3_7d' => 604800, '7_30d' => 2592000, 'over_30d' => PHP_INT_MAX );

    /** @var ticketsystemAccess */
    protected $access;
    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ticketsystemAccess $access, ?ticketsystemSettings $settings = null )
    {
        $this->access = $access;
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    // ================================================================================================== pure

    /** A date YYYY-MM-DD as the timestamp of its start (the server's time zone), or 0 when it is no valid date. */
    public static function parseDate( $text )
    {
        $text = trim( (string)$text );
        if ( !preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $text, $m ) || !checkdate( (int)$m[2], (int)$m[3], (int)$m[1] ) )
            return 0;
        return (int)mktime( 0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1] );
    }

    /**
     * The period of a report from the view parameters (from)/<date> and (to)/<date> (both inclusive): the last
     * $defaultDays days up to today when they are missing or wrong; swapped when the end is before the start; never
     * longer than $maxDays.
     *
     * @return array from (timestamp, inclusive), to (timestamp, exclusive: the start of the day after), from_text,
     *               to_text (YYYY-MM-DD), days
     */
    public static function range( array $params, $defaultDays, $now, $maxDays = self::MAX_DAYS )
    {
        $maxDays = max( 1, min( self::MAX_DAYS, (int)$maxDays ) );
        $defaultDays = max( 1, min( $maxDays, (int)$defaultDays ) );
        $today = self::parseDate( date( 'Y-m-d', (int)$now ) );
        $to = isset( $params['to'] ) && is_string( $params['to'] ) ? self::parseDate( $params['to'] ) : 0;
        $from = isset( $params['from'] ) && is_string( $params['from'] ) ? self::parseDate( $params['from'] ) : 0;
        if ( !$to )
            $to = $today;
        if ( !$from )
            $from = self::addDays( $to, -( $defaultDays - 1 ) );
        if ( $from > $to )
            list( $from, $to ) = array( $to, $from );
        if ( self::daysBetween( $from, $to ) + 1 > $maxDays )
            $from = self::addDays( $to, -( $maxDays - 1 ) );
        $end = self::addDays( $to, 1 );
        return array( 'from' => $from, 'to' => $end, 'from_text' => date( 'Y-m-d', $from ), 'to_text' => date( 'Y-m-d', $to ),
                      'days' => self::daysBetween( $from, $end ) );
    }

    /** A day start plus $n calendar days (daylight saving safe). */
    protected static function addDays( $dayStart, $n )
    {
        return (int)mktime( 0, 0, 0, (int)date( 'n', $dayStart ), (int)date( 'j', $dayStart ) + (int)$n, (int)date( 'Y', $dayStart ) );
    }

    protected static function daysBetween( $a, $b )
    {
        return (int)round( ( $b - $a ) / 86400 );
    }

    /** The bucket size of the volume table: the one asked for (day, week, month), else by the length of the period. */
    public static function periodFor( $days, $asked = '' )
    {
        if ( in_array( (string)$asked, array( 'day', 'week', 'month' ), true ) )
            return (string)$asked;
        return $days <= 31 ? 'day' : ( $days <= 182 ? 'week' : 'month' );
    }

    /** The bucket of a time: 2026-11-17 (day), 2026-W47 (ISO week), 2026-11 (month). */
    public static function bucketKey( $time, $period )
    {
        if ( $period === 'month' )
            return date( 'Y-m', (int)$time );
        if ( $period === 'week' )
            return date( 'o-\WW', (int)$time );
        return date( 'Y-m-d', (int)$time );
    }

    /** Every bucket of a period in order, each with zero counts. */
    public static function buckets( $from, $to, $period )
    {
        $out = array();
        for ( $t = (int)$from; $t < (int)$to; $t = self::addDays( $t, 1 ) )
        {
            $k = self::bucketKey( $t, $period );
            if ( !isset( $out[$k] ) )
                $out[$k] = array( 'key' => $k, 'opened' => 0, 'solved' => 0 );
        }
        return $out;
    }

    /**
     * The volume of a period: the tickets opened in each bucket and the tickets solved in each bucket (of the rows
     * given: the tickets opened in the period and those solved in it).
     *
     * @param array[] $opened rows (created) opened in the period
     * @param array[] $solved rows (resolved, closed) solved in the period
     */
    public static function volume( array $opened, array $solved, $from, $to, $period )
    {
        $out = self::buckets( $from, $to, $period );
        foreach ( $opened as $r )
        {
            $k = self::bucketKey( (int)$r['created'], $period );
            if ( isset( $out[$k] ) )
                $out[$k]['opened']++;
        }
        foreach ( $solved as $r )
        {
            $at = self::solvedAt( $r );
            $k = self::bucketKey( $at, $period );
            if ( $at >= $from && $at < $to && isset( $out[$k] ) )
                $out[$k]['solved']++;
        }
        return array_values( $out );
    }

    /** When a ticket was solved: its resolved time, else its closed time, else 0. */
    public static function solvedAt( array $r )
    {
        return (int)$r['resolved'] > 0 ? (int)$r['resolved'] : ( isset( $r['closed'] ) ? (int)$r['closed'] : 0 );
    }

    /**
     * The figures of the tickets opened in a period (pure on the rows given).
     *
     * @param array[] $rows ticketsystemTicket::values() of the tickets opened in the period
     * @param array $done the done statuses
     * @return array opened, resolved, active, unassigned, escalated, breached, by_status, by_category, by_priority,
     *               by_assignee, first_response (count, avg_minutes, median_minutes), resolution (count, avg_hours,
     *               median_hours), sla (response_met, response_total, resolution_met, resolution_total), csat (count, avg,
     *               dist 1..5)
     */
    public static function figures( array $rows, array $done, $now )
    {
        $out = array( 'opened' => count( $rows ), 'resolved' => 0, 'active' => 0, 'unassigned' => 0, 'escalated' => 0, 'breached' => 0,
                      'by_status' => array(), 'by_category' => array(), 'by_priority' => array(), 'by_assignee' => array(),
                      'first_response' => array( 'count' => 0, 'avg_minutes' => 0, 'median_minutes' => 0 ),
                      'resolution' => array( 'count' => 0, 'avg_hours' => 0, 'median_hours' => 0 ),
                      'sla' => array( 'response_met' => 0, 'response_total' => 0, 'resolution_met' => 0, 'resolution_total' => 0 ),
                      'csat' => array( 'count' => 0, 'avg' => 0, 'dist' => array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ), 'satisfied_pct' => null, 'response_rate' => null ) );
        $responses = array();
        $resolutions = array();
        $scores = array();
        $int = function ( array $r, $k ) { return isset( $r[$k] ) ? (int)$r[$k] : 0; };
        foreach ( $rows as $r )
        {
            $isDone = in_array( (string)$r['status'], $done, true );
            $out[$isDone ? 'resolved' : 'active']++;
            if ( !$isDone && !$int( $r, 'assignee_id' ) )
                $out['unassigned']++;
            if ( $int( $r, 'escalation' ) >= 1 )
                $out['escalated']++;
            if ( $int( $r, 'breached' ) > 0 )
                $out['breached']++;
            foreach ( array( 'by_status' => 'status', 'by_category' => 'category', 'by_priority' => 'priority', 'by_assignee' => 'assignee_id' ) as $k => $f )
            {
                $v = isset( $r[$f] ) ? (string)$r[$f] : '';
                $out[$k][$v] = ( isset( $out[$k][$v] ) ? $out[$k][$v] : 0 ) + 1;
            }
            if ( $int( $r, 'responded' ) > 0 )
                $responses[] = max( 0, $int( $r, 'responded' ) - $int( $r, 'created' ) ) / 60;
            $resolvedAt = self::solvedAt( $r );
            if ( $isDone && $resolvedAt > 0 )
                $resolutions[] = max( 0, $resolvedAt - $int( $r, 'created' ) ) / 3600;
            if ( $int( $r, 'response_due' ) > 0 && ( $int( $r, 'responded' ) > 0 || $now > $int( $r, 'response_due' ) ) )
            {
                $out['sla']['response_total']++;
                if ( $int( $r, 'responded' ) > 0 && $int( $r, 'responded' ) <= $int( $r, 'response_due' ) )
                    $out['sla']['response_met']++;
            }
            if ( $int( $r, 'resolve_due' ) > 0 && ( $resolvedAt > 0 || $now > $int( $r, 'resolve_due' ) ) )
            {
                $out['sla']['resolution_total']++;
                if ( $resolvedAt > 0 && $resolvedAt <= $int( $r, 'resolve_due' ) )
                    $out['sla']['resolution_met']++;
            }
            if ( $int( $r, 'rating' ) >= 1 && $int( $r, 'rating' ) <= 5 )
            {
                $scores[] = $int( $r, 'rating' );
                $out['csat']['dist'][$int( $r, 'rating' )]++;
            }
        }
        if ( $responses )
            $out['first_response'] = array( 'count' => count( $responses ), 'avg_minutes' => (int)round( array_sum( $responses ) / count( $responses ) ),
                                            'median_minutes' => (int)round( self::median( $responses ) ) );
        if ( $resolutions )
            $out['resolution'] = array( 'count' => count( $resolutions ), 'avg_hours' => round( array_sum( $resolutions ) / count( $resolutions ), 1 ),
                                        'median_hours' => round( self::median( $resolutions ), 1 ) );
        $out['csat']['count'] = count( $scores );
        $out['csat']['avg'] = $scores ? round( array_sum( $scores ) / count( $scores ), 2 ) : 0;
        foreach ( array( 'by_status', 'by_category', 'by_priority', 'by_assignee' ) as $k )
            arsort( $out[$k] );
        return $out;
    }

    /** The median of numbers (the lower one of the middle two). */
    public static function median( array $values )
    {
        if ( !$values )
            return 0;
        sort( $values );
        return $values[(int)floor( ( count( $values ) - 1 ) / 2 )];
    }

    /**
     * The backlog: the tickets not done now, by how long they have been open (BACKLOG_BUCKETS), by queue and by
     * priority, and the oldest one's age in seconds (pure).
     *
     * @param array[] $rows the tickets not done (created, category, priority)
     */
    public static function backlog( array $rows, $now )
    {
        $out = array( 'total' => count( $rows ), 'ages' => array_fill_keys( array_keys( self::BACKLOG_BUCKETS ), 0 ), 'by_category' => array(),
                      'by_priority' => array(), 'oldest_seconds' => 0, 'waiting_staff' => 0 );
        foreach ( $rows as $r )
        {
            $age = max( 0, (int)$now - (int)$r['created'] );
            foreach ( self::BACKLOG_BUCKETS as $k => $limit )
                if ( $age < $limit )
                {
                    $out['ages'][$k]++;
                    break;
                }
            $out['oldest_seconds'] = max( $out['oldest_seconds'], $age );
            foreach ( array( 'by_category' => 'category', 'by_priority' => 'priority' ) as $k => $f )
            {
                $v = (string)$r[$f];
                $out[$k][$v] = ( isset( $out[$k][$v] ) ? $out[$k][$v] : 0 ) + 1;
            }
            if ( isset( $r['last_post_staff'] ) && !(int)$r['last_post_staff'] )
                $out['waiting_staff']++;
        }
        arsort( $out['by_category'] );
        arsort( $out['by_priority'] );
        return $out;
    }

    /**
     * The workload of each staff member (pure): the tickets not done assigned to them now, and their replies in the
     * period with the number of tickets those went to. User id 0 is "nobody" (unassigned tickets).
     *
     * @param array[] $openRows the tickets not done (assignee_id)
     * @param array[] $replies the staff replies of the period (author_id, ticket_id)
     * @return array user id => open, replies, tickets
     */
    public static function workload( array $openRows, array $replies )
    {
        $out = array();
        $blank = array( 'open' => 0, 'replies' => 0, 'tickets' => 0 );
        foreach ( $openRows as $r )
        {
            $id = isset( $r['assignee_id'] ) ? (int)$r['assignee_id'] : 0;
            if ( !isset( $out[$id] ) )
                $out[$id] = $blank;
            $out[$id]['open']++;
        }
        $seen = array();
        foreach ( $replies as $p )
        {
            $id = (int)$p['author_id'];
            if ( !isset( $out[$id] ) )
                $out[$id] = $blank;
            $out[$id]['replies']++;
            if ( !isset( $seen[$id][(int)$p['ticket_id']] ) )
            {
                $seen[$id][(int)$p['ticket_id']] = true;
                $out[$id]['tickets']++;
            }
        }
        uksort( $out, function ( $a, $b ) use ( $out ) {
            if ( ( $a === 0 ) !== ( $b === 0 ) )
                return $a === 0 ? 1 : -1;
            return array( $out[$b]['open'], $out[$b]['replies'], $a ) <=> array( $out[$a]['open'], $out[$a]['replies'], $b );
        } );
        return $out;
    }

    /**
     * A cell a spreadsheet will not read as a formula (CSV injection, OWASP): a text that starts with = + - @, a tab
     * or a carriage return (also after leading spaces) gets a leading apostrophe. Numbers stay numbers.
     */
    public static function csvSafe( $v )
    {
        if ( is_int( $v ) || is_float( $v ) )
            return (string)$v;
        $v = (string)$v;
        return preg_match( '/^[\s\x{00A0}]*[=+\-@\t\r\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]/u', $v ) ? "'" . $v : $v;
    }

    /** One CSV line (RFC 4180, CRLF), every cell through csvSafe(); no escape character, so no fputcsv() quirk. */
    public static function csvLine( array $cells )
    {
        $out = array();
        foreach ( $cells as $c )
        {
            $c = self::csvSafe( $c );
            // control characters other than tab and line breaks have no place in a report
            $c = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $c );
            $out[] = preg_match( '/[",\r\n\t;]/', $c ) || $c !== trim( $c ) ? '"' . str_replace( '"', '""', $c ) . '"' : $c;
        }
        return implode( ',', $out ) . "\r\n";
    }

    /**
     * The filter of the search from view parameters (pure): only known names and allowed values are kept.
     *
     * @param array $spec ticketsystemDesk::listSpec()
     * @return array q, number, status, priority, category, assignee, account, customer, from, to (only the given ones)
     */
    public static function searchFilter( array $params, array $spec )
    {
        $p = $params;
        // the search names the queue "queue"; the desk's conditions call it "category"
        if ( isset( $p['queue'] ) && !isset( $p['category'] ) )
            $p['category'] = $p['queue'];
        $deskSpec = $spec;
        $deskSpec['filters'] = array_intersect_key( $spec['filters'], array_flip( array( 'q', 'status', 'priority', 'category', 'assignee', 'account', 'sla', 'reply' ) ) );
        $f = ticketsystemDesk::filter( $p, $deskSpec );
        $out = $f['filter'];
        $text = function ( $k, $max ) use ( $params ) {
            if ( !isset( $params[$k] ) || !is_string( $params[$k] ) )
                return '';
            return trim( mb_substr( preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', rawurldecode( $params[$k] ) ), 0, $max ) );
        };
        $number = strtoupper( $text( 'number', 32 ) );
        if ( $number !== '' && preg_match( '/^[A-Z0-9-]{1,32}$/D', $number ) )
            $out['number'] = $number;
        $customer = $text( 'customer', 100 );
        if ( $customer !== '' )
            $out['customer'] = $customer;
        foreach ( array( 'from', 'to' ) as $k )
            if ( isset( $params[$k] ) && is_string( $params[$k] ) && self::parseDate( $params[$k] ) )
                $out[$k] = $params[$k];
        return $out;
    }

    /**
     * The plan of a search (pure): what is searched where. The text is searched in the subject and in the
     * customer-visible posts for every reader, in the internal notes only for staff; the other filters become
     * conditions on ticketsystem_ticket.
     *
     * @param array $filter searchFilter()
     * @param bool $isStaff the reader is staff (ticketsystem/manage)
     * @param callable $quote quotes a text value for SQL
     * @return array conditions (string[] on ticketsystem_ticket), like ('' = no text), number (exact or ''),
     *               search_posts (bool), search_notes (bool), customer (text or '')
     */
    public static function searchPlan( array $filter, $isStaff, $quote, $me = 0 )
    {
        $plan = array( 'conditions' => array(), 'like' => '', 'number' => '', 'search_posts' => false, 'search_notes' => false, 'customer' => '' );
        $q = isset( $filter['q'] ) ? trim( (string)$filter['q'] ) : '';
        $rest = $filter;
        unset( $rest['q'] );
        $plan['conditions'] = ticketsystemDesk::conditions( array_intersect_key( $rest, array_flip( array( 'priority', 'category', 'assignee', 'account', 'sla', 'reply' ) ) ), $quote, $me );
        if ( $q !== '' && preg_match( ticketsystemDesk::NUMBER_PATTERN, $q ) )
            $plan['number'] = strtoupper( $q );
        else if ( $q !== '' && ( $like = ticketsystemDesk::likePattern( $q ) ) !== '' )
        {
            $plan['like'] = $like;
            $plan['search_posts'] = true;
            $plan['search_notes'] = (bool)$isStaff;
        }
        if ( isset( $filter['number'] ) && $filter['number'] !== '' )
        {
            if ( preg_match( ticketsystemDesk::NUMBER_PATTERN, $filter['number'] ) )
                $plan['conditions'][] = 'number = ' . $quote( strtoupper( $filter['number'] ) );
            else
                $plan['conditions'][] = 'number LIKE ' . $quote( '%' . strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', $filter['number'] ) ) . '%' );
        }
        if ( isset( $filter['from'] ) && ( $from = self::parseDate( $filter['from'] ) ) )
            $plan['conditions'][] = 'created >= ' . $from;
        if ( isset( $filter['to'] ) && ( $to = self::parseDate( $filter['to'] ) ) )
            $plan['conditions'][] = 'created < ' . self::addDays( $to, 1 );
        if ( isset( $filter['customer'] ) && trim( $filter['customer'] ) !== '' )
            $plan['customer'] = trim( $filter['customer'] );
        return $plan;
    }

    /** "col IN (1,2) OR col IN (3,4)" in chunks Oracle takes; '0=1' for no ids (pure). */
    public static function inList( $column, array $ids )
    {
        $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
        if ( !$ids )
            return '0=1';
        $parts = array();
        foreach ( array_chunk( $ids, self::IN_CHUNK ) as $chunk )
            $parts[] = $column . ' IN (' . implode( ',', $chunk ) . ')';
        return count( $parts ) > 1 ? '(' . implode( ' OR ', $parts ) . ')' : $parts[0];
    }

    /** The view parameters of a search filter: "/(q)/disk/(status)/all" (pure). */
    public static function searchUrl( $base, array $filter, $offset = 0 )
    {
        $out = rtrim( (string)$base, '/' );
        $names = array( 'q', 'number', 'status', 'priority', 'category', 'assignee', 'customer', 'account', 'sla', 'reply', 'from', 'to' );
        foreach ( $names as $k )
            if ( isset( $filter[$k] ) && (string)$filter[$k] !== '' )
                $out .= '/(' . ( $k === 'category' ? 'queue' : $k ) . ')/' . rawurlencode( str_replace( array( '/', '\\' ), ' ', (string)$filter[$k] ) );
        if ( (int)$offset > 0 )
            $out .= '/(offset)/' . (int)$offset;
        return $out;
    }

    // ============================================================================================== database

    /** The reader's scope (and a queue) as conditions on ticketsystem_ticket; null when the reader may read none. */
    protected function scopeParts( $queue = '' )
    {
        $base = $this->access->listConditions();
        if ( $base === null )
            return null;
        $parts = $base === '1=1' ? array() : array( '(' . $base . ')' );
        if ( $queue !== '' && ticketsystemDesk::isIdentifier( $queue ) )
            $parts[] = 'category = ' . call_user_func( ticketsystemDesk::quoter(), $queue );
        return $parts;
    }

    /** Rows (values()) of a condition, read in pages; at most $max. */
    protected static function rows( $where, $max, &$truncated = false )
    {
        $out = array();
        $page = 1000;
        for ( $offset = 0; $offset < $max; $offset += $page )
        {
            $batch = ticketsystemTicket::fetchWhere( $where, $offset, min( $page, $max - $offset ), 'id ASC' );
            foreach ( $batch as $t )
                $out[] = $t->values();
            if ( count( $batch ) < min( $page, $max - $offset ) )
                return $out;
        }
        if ( ticketsystemTicket::countWhere( $where ) > $max )
            $truncated = true;
        return $out;
    }

    protected function maxRows()
    {
        return max( 100, (int)$this->settings->value( 'ReportSettings', 'MaxRows', 20000 ) );
    }

    /** The tickets opened in a period within the reader's scope (and queue), oldest first, as ticketsystemTicket. */
    public function opened( $from, $to, $max = 5000, $queue = '' )
    {
        $parts = $this->scopeParts( $queue );
        if ( $parts === null )
            return array();
        $parts[] = 'created >= ' . (int)$from;
        $parts[] = 'created < ' . (int)$to;
        return ticketsystemTicket::fetchWhere( implode( ' AND ', $parts ), 0, $max, 'created ASC, id ASC' );
    }

    /**
     * The whole report of a period.
     *
     * @return array range, period, queue, figures, volume, backlog, workload, csat_source (fetch|index|off),
     *               sla_enabled, csat_enabled, truncated
     */
    public function report( $from, $to, $queue = '', $period = '', $now = null )
    {
        $now = $now === null ? time() : (int)$now;
        $set = $this->settings->statusSet();
        $empty = array( 'figures' => self::figures( array(), $set->done, $now ), 'volume' => array(), 'backlog' => self::backlog( array(), $now ),
                        'workload' => array(), 'truncated' => false );
        $days = max( 1, (int)round( ( $to - $from ) / 86400 ) );
        $period = self::periodFor( $days, $period );
        $out = array( 'from' => (int)$from, 'to' => (int)$to, 'period' => $period, 'queue' => (string)$queue,
                      'sla_enabled' => $this->settings->enabled( 'SLASettings', 'Enabled', 'disabled' ),
                      'csat_enabled' => $this->settings->enabled( 'CSATSettings', 'Enabled', 'disabled' ), 'csat_source' => 'index' );
        $parts = $this->scopeParts( $queue );
        if ( $parts === null )
            return $out + $empty;
        $max = $this->maxRows();
        $truncated = false;
        $scope = $parts ? implode( ' AND ', $parts ) . ' AND ' : '';
        $opened = self::rows( $scope . 'created >= ' . (int)$from . ' AND created < ' . (int)$to, $max, $truncated );
        $solved = self::rows( $scope . '((resolved >= ' . (int)$from . ' AND resolved < ' . (int)$to . ') OR (resolved = 0 AND closed >= '
                              . (int)$from . ' AND closed < ' . (int)$to . '))', $max, $truncated );
        $notDone = $set->done ? ticketsystemDesk::statusCondition( array_values( array_diff( array_keys( $set->statuses ), $set->done ) ), ticketsystemDesk::quoter() ) : '';
        $open = $notDone !== '' ? self::rows( $scope . $notDone, $max, $truncated ) : array();
        $out['figures'] = self::figures( $opened, $set->done, $now );
        $out['volume'] = self::volume( $opened, $solved, $from, $to, $period );
        $out['backlog'] = self::backlog( $open, $now );
        $out['workload'] = self::workload( $open, $this->staffReplies( $from, $to, $parts ) );
        $out['truncated'] = $truncated;
        // satisfaction from the SLA part's aggregate (fetch csat_summary: the ratings given in the period) when it is
        // there and the reader reads every ticket (it counts every ticket); else from the tickets of the reader's scope
        $out['figures']['csat']['satisfied_pct'] = null;
        $out['figures']['csat']['response_rate'] = null;
        if ( $this->access->scope( 'read' ) >= ticketsystemAccess::ALL && class_exists( 'ticketsystemFunctionCollection' )
             && method_exists( 'ticketsystemFunctionCollection', 'csatSummary' ) )
        {
            $r = ticketsystemFunctionCollection::csatSummary( (int)$from, (int)$to, (string)$queue );
            if ( is_array( $r ) && isset( $r['result'] ) && is_array( $r['result'] ) )
            {
                $c = $r['result'];
                foreach ( array( 'count', 'avg', 'dist' ) as $k )
                    if ( isset( $c[$k] ) )
                        $out['figures']['csat'][$k] = $c[$k];
                $out['figures']['csat']['satisfied_pct'] = isset( $c['csat'] ) && !empty( $c['count'] ) ? (int)$c['csat'] : null;
                $out['figures']['csat']['response_rate'] = isset( $c['response_rate'] ) && !empty( $c['asked'] ) ? (int)$c['response_rate'] : null;
                $out['csat_source'] = 'fetch';
            }
        }
        if ( !$out['csat_enabled'] && !$out['figures']['csat']['count'] )
            $out['csat_source'] = 'off';
        return $out;
    }

    /** The staff replies of a period (author_id, ticket_id) to tickets within the scope conditions. */
    protected function staffReplies( $from, $to, array $scopeParts )
    {
        $rows = eZDB::instance()->arrayQuery( 'SELECT ticket_id, author_id FROM ticketsystem_post WHERE is_staff = 1 AND created >= ' . (int)$from
                                              . ' AND created < ' . (int)$to . ' ORDER BY id ASC', array( 'limit' => $this->maxRows() ) );
        $rows = is_array( $rows ) ? $rows : array();
        if ( !$rows || !$scopeParts )
            return $rows;
        // only the replies to tickets the reader may read (and of the queue)
        $ids = array_values( array_unique( array_map( function ( $r ) { return (int)$r['ticket_id']; }, $rows ) ) );
        $ok = array();
        foreach ( array_chunk( $ids, self::IN_CHUNK ) as $chunk )
        {
            $found = eZDB::instance()->arrayQuery( 'SELECT id FROM ticketsystem_ticket WHERE ' . implode( ' AND ', $scopeParts ) . ' AND ' . self::inList( 'id', $chunk ) );
            foreach ( is_array( $found ) ? $found : array() as $f )
                $ok[(int)$f['id']] = true;
        }
        return array_values( array_filter( $rows, function ( $r ) use ( $ok ) { return isset( $ok[(int)$r['ticket_id']] ); } ) );
    }

    /** The report of a period (the figures only; kept for the callers of 1.1). */
    public function period( $from, $to, $queue = '' )
    {
        $r = $this->report( $from, $to, $queue );
        return $r['figures'];
    }

    /**
     * The CSV of the tickets opened in a period within the reader's scope (and queue): index data only, never a note,
     * never the text of a post; every cell defused (csvSafe()).
     */
    public function csv( $from, $to, $queue = '' )
    {
        $set = $this->settings->statusSet();
        $cats = $this->settings->categories();
        $prios = $this->settings->priorities();
        $out = "\xEF\xBB\xBF" . self::csvLine( array( 'number', 'subject', 'account', 'status', 'queue', 'priority', 'assignee', 'created',
                                                       'first_response_minutes', 'resolved', 'resolution_hours', 'sla_level', 'escalation',
                                                       'breached', 'rating', 'posts', 'source' ) );
        $names = array();
        $parts = $this->scopeParts( $queue );
        if ( $parts === null )
            return $out;
        $parts[] = 'created >= ' . (int)$from;
        $parts[] = 'created < ' . (int)$to;
        foreach ( self::rows( implode( ' AND ', $parts ), $this->maxRows() ) as $r )
        {
            $aid = (int)$r['assignee_id'];
            if ( $aid && !isset( $names[$aid] ) )
            {
                $o = eZContentObject::fetch( $aid );
                $names[$aid] = $o ? (string)$o->attribute( 'name' ) : '#' . $aid;
            }
            $solved = self::solvedAt( $r );
            $out .= self::csvLine( array( $r['number'], $r['subject'], ticketsystemAccount::label( $r['account_key'] ), $set->label( $r['status'] ),
                                          isset( $cats[$r['category']] ) ? $cats[$r['category']] : $r['category'],
                                          isset( $prios[$r['priority']] ) ? $prios[$r['priority']] : $r['priority'], $aid ? $names[$aid] : '',
                                          date( 'c', (int)$r['created'] ),
                                          (int)$r['responded'] ? (int)round( ( (int)$r['responded'] - (int)$r['created'] ) / 60 ) : '',
                                          $solved ? date( 'c', $solved ) : '',
                                          $solved ? round( max( 0, $solved - (int)$r['created'] ) / 3600, 1 ) : '',
                                          $r['sla_level'], (int)$r['escalation'], (int)$r['breached'], (int)$r['rating'] ?: '', (int)$r['post_count'],
                                          $r['source'] ) );
        }
        return $out;
    }

    /** The user ids whose name or e-mail address contains a text (the customer filter), at most 500. */
    protected static function usersMatching( $text )
    {
        $like = ticketsystemDesk::likePattern( $text );
        if ( $like === '' )
            return array();
        $quote = ticketsystemDesk::quoter();
        $db = eZDB::instance();
        $ids = array();
        $classIds = array();
        foreach ( array( 'user' ) as $identifier )
        {
            $c = class_exists( 'eZContentClass' ) ? eZContentClass::fetchByIdentifier( $identifier, false ) : null;
            if ( is_array( $c ) && isset( $c['id'] ) )
                $classIds[] = (int)$c['id'];
        }
        if ( $classIds )
            foreach ( (array)$db->arrayQuery( 'SELECT id FROM ezcontentobject WHERE ' . self::inList( 'contentclass_id', $classIds )
                                              . ' AND LOWER(name) LIKE ' . $quote( $like ), array( 'limit' => 500 ) ) as $r )
                $ids[] = (int)$r['id'];
        foreach ( (array)$db->arrayQuery( 'SELECT contentobject_id FROM ezuser WHERE LOWER(email) LIKE ' . $quote( $like ), array( 'limit' => 500 ) ) as $r )
            $ids[] = (int)$r['contentobject_id'];
        return array_slice( array_values( array_unique( $ids ) ), 0, 500 );
    }

    /** The ticket ids of the rows of a table whose LOWER(body) matches a pattern, at most $max. */
    protected static function textMatches( $table, $like, $max )
    {
        $rows = eZDB::instance()->arrayQuery( 'SELECT ticket_id FROM ' . $table . ' WHERE LOWER(body) LIKE ' . call_user_func( ticketsystemDesk::quoter(), $like )
                                              . ' ORDER BY id DESC', array( 'limit' => (int)$max ) );
        $out = array();
        foreach ( is_array( $rows ) ? $rows : array() as $r )
            $out[(int)$r['ticket_id']] = true;
        return $out;
    }

    /**
     * The search (doc/search.md): the text in the subject and the customer-visible posts, for staff also in the
     * internal notes; the other filters on the ticket; always within the reader's read scope.
     *
     * @param array|string $filter searchFilter() (a plain text is the q of 1.1)
     * @return array total, tickets (ticketsystemTicket[]), in_notes (ticket id => true where only a note matched)
     */
    public function search( $filter, $offset = 0, $limit = 50 )
    {
        $none = array( 'total' => 0, 'tickets' => array(), 'in_notes' => array() );
        if ( !is_array( $filter ) )
            $filter = array( 'q' => (string)$filter );
        $filter = array_filter( $filter, function ( $v ) { return trim( (string)$v ) !== ''; } );
        $base = $this->access->listConditions();
        if ( $base === null || !$filter )
            return $none;
        $quote = ticketsystemDesk::quoter();
        $isStaff = $this->access->isStaff();
        $plan = self::searchPlan( $filter, $isStaff, $quote, $this->access->userId() );
        $parts = $base === '1=1' ? array() : array( '(' . $base . ')' );
        $set = $this->settings->statusSet();
        $sc = ticketsystemDesk::statusCondition( ticketsystemDesk::statusesFor( isset( $filter['status'] ) ? $filter['status'] : 'all',
                                                                               array_keys( $set->statuses ), $set->done ), $quote );
        if ( $sc !== '' )
            $parts[] = $sc;
        foreach ( $plan['conditions'] as $c )
            $parts[] = $c;
        if ( $plan['customer'] !== '' )
        {
            $users = self::usersMatching( $plan['customer'] );
            $keys = array_map( function ( $id ) use ( $quote ) { return $quote( 'user:' . $id ); }, $users );
            $parts[] = $users ? '(' . self::inList( 'opener_id', $users ) . ' OR account_key IN (' . implode( ',', array_slice( $keys, 0, self::IN_CHUNK ) ) . '))' : '0=1';
        }
        if ( $plan['number'] !== '' )
            $parts[] = 'number = ' . $quote( $plan['number'] );
        $noteIds = array();
        if ( $plan['like'] !== '' )
        {
            $max = max( 100, (int)$this->settings->value( 'SearchSettings', 'MaxTextMatches', 2000 ) );
            $postIds = $plan['search_posts'] ? self::textMatches( 'ticketsystem_post', $plan['like'], $max ) : array();
            // internal notes: staff only, never for a customer (searchPlan() decides it, tests/ticketsystemSearchTest.php)
            if ( $plan['search_notes'] && $isStaff )
                $noteIds = self::textMatches( 'ticketsystem_note', $plan['like'], $max );
            $or = array( 'LOWER(subject) LIKE ' . $quote( $plan['like'] ) );
            $ids = array_keys( $postIds + $noteIds );
            if ( $ids )
                $or[] = self::inList( 'id', $ids );
            $parts[] = '(' . implode( ' OR ', $or ) . ')';
            // a ticket counts as found "in a note" only when neither its subject nor a post matched
            foreach ( array_keys( $postIds ) as $id )
                unset( $noteIds[$id] );
        }
        $where = $parts ? implode( ' AND ', $parts ) : '1=1';
        $tickets = ticketsystemTicket::fetchWhere( $where, max( 0, (int)$offset ), max( 1, (int)$limit ), 'last_post DESC, id DESC' );
        $inNotes = array();
        $like = $plan['like'];
        foreach ( $tickets as $t )
            if ( isset( $noteIds[(int)$t->attribute( 'id' )] ) && !self::likeMatches( $like, (string)$t->attribute( 'subject' ) ) )
                $inNotes[(int)$t->attribute( 'id' )] = true;
        return array( 'total' => ticketsystemTicket::countWhere( $where ), 'tickets' => $tickets, 'in_notes' => $inNotes );
    }

    /** Whether a text matches a %-pattern of likePattern() the way LOWER(col) LIKE does (pure). */
    public static function likeMatches( $like, $text )
    {
        if ( $like === '' )
            return false;
        $parts = array_map( function ( $p ) { return preg_quote( $p, '/' ); }, explode( '%', $like ) );
        return preg_match( '/^' . implode( '.*', $parts ) . '$/su', mb_strtolower( (string)$text ) ) === 1;
    }
}
