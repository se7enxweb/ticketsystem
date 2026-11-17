<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The staff's ticket list (the administration's tab TicketSystem, doc/views.md "The staff desk"): the filters, the sort
 * keys, the SQL conditions of a filter on ticketsystem_ticket, the status figures and the summary of the dashboard
 * card. Katalyst's ticket manager uses the same rules (expKatalystTicketDesk).
 *
 * Filters are view parameters, never GET parameters: filter() reads them from the view's UserParameters and keeps only
 * the values the spec allows; url() writes them back. The static methods are pure, the instance methods read the
 * database within the reader's own read scope (ticketsystemAccess::listConditions()).
 */
class ticketsystemDesk
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DONE = 'done';
    const STATUS_ALL = 'all';

    /** A ticket number as ticketsystem writes it (%prefix-%year-%seq). */
    const NUMBER_PATTERN = '/^[A-Za-z0-9]{1,16}-[0-9]{4}-[0-9]{1,10}$/D';

    /** @var ticketsystemAccess */
    protected $access;
    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ticketsystemAccess $access, ?ticketsystemSettings $settings = null )
    {
        $this->access = $access;
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    // ------------------------------------------------------------------------------------------------- pure

    /** Whether a settings key can stand in SQL and an address as it is. */
    public static function isIdentifier( $id )
    {
        return is_string( $id ) && preg_match( '/^[a-z0-9_]{1,40}$/D', $id ) === 1;
    }

    protected static function identifiers( array $list )
    {
        $out = array();
        foreach ( $list as $id => $label )
            if ( self::isIdentifier( (string)$id ) )
                $out[(string)$id] = (string)$label;
        return $out;
    }

    /**
     * The spec of the list: every filter with its allowed values, every sort key with the columns it sorts by.
     */
    public static function listSpec( array $statuses, array $priorities, array $categories )
    {
        $statusValues = array( self::STATUS_ACTIVE => 'Not resolved', self::STATUS_DONE => 'Resolved or closed', self::STATUS_ALL => 'Any status' );
        foreach ( $statuses as $id => $label )
            if ( self::isIdentifier( (string)$id ) && !isset( $statusValues[$id] ) )
                $statusValues[(string)$id] = (string)$label;
        return array(
            'filters' => array(
                'q' => array( 'label' => 'Search', 'type' => 'text', 'max' => 100 ),
                'status' => array( 'label' => 'Status', 'type' => 'enum', 'values' => $statusValues ),
                'priority' => array( 'label' => 'Priority', 'type' => 'enum', 'values' => self::identifiers( $priorities ) ),
                'category' => array( 'label' => 'Queue', 'type' => 'enum', 'values' => self::identifiers( $categories ) ),
                'reply' => array( 'label' => 'Last post', 'type' => 'enum', 'values' => array( 'staff' => 'Needs a staff reply', 'customer' => 'Answered by staff' ) ),
                'assignee' => array( 'label' => 'Assigned to', 'type' => 'assignee' ),
                'sla' => array( 'label' => 'SLA', 'type' => 'enum', 'values' => array( 'escalated' => 'Escalated', 'breached' => 'Breached' ) ),
                'account' => array( 'label' => 'Account', 'type' => 'account' ),
                'subscription' => array( 'label' => 'Subscription', 'type' => 'id' ),
            ),
            'sorts' => array( 'number' => array( 'year', 'seq' ), 'subject' => array( 'subject' ), 'status' => array( 'status' ),
                              'priority' => array( 'priority_rank' ), 'category' => array( 'category' ), 'posts' => array( 'post_count' ),
                              'last_post' => array( 'last_post' ), 'created' => array( 'created' ), 'due' => array( 'resolve_due' ),
                              'assignee' => array( 'assignee_id' ) ),
            'default_sort' => 'last_post', 'default_order' => 'desc',
        );
    }

    /**
     * The validated filter, sort and order of a view's UserParameters (pure): unknown names and values are dropped.
     *
     * @return array filter (name => value), sort, order, offset
     */
    public static function filter( array $params, array $spec )
    {
        $f = array();
        foreach ( $spec['filters'] as $name => $def )
        {
            if ( !isset( $params[$name] ) || is_array( $params[$name] ) )
                continue;
            $v = trim( rawurldecode( (string)$params[$name] ) );
            if ( $v === '' )
                continue;
            switch ( $def['type'] )
            {
                case 'text': $f[$name] = mb_substr( $v, 0, (int)$def['max'] ); break;
                case 'enum': if ( isset( $def['values'][$v] ) ) $f[$name] = $v; break;
                case 'id': if ( ctype_digit( $v ) && (int)$v > 0 ) $f[$name] = (string)(int)$v; break;
                case 'assignee': if ( $v === 'me' || $v === 'none' || ( ctype_digit( $v ) && (int)$v > 0 ) ) $f[$name] = $v; break;
                case 'account': if ( preg_match( '/^(user|katalyst)-[1-9][0-9]{0,9}$/D', $v ) ) $f[$name] = $v; break;
            }
        }
        $sort = isset( $params['sort'] ) && is_string( $params['sort'] ) && isset( $spec['sorts'][$params['sort']] ) ? $params['sort'] : $spec['default_sort'];
        $order = isset( $params['order'] ) && in_array( $params['order'], array( 'asc', 'desc' ), true ) ? $params['order'] : $spec['default_order'];
        $offset = isset( $params['offset'] ) && ctype_digit( (string)$params['offset'] ) ? (int)$params['offset'] : 0;
        return array( 'filter' => $f, 'sort' => $sort, 'order' => $order, 'offset' => $offset );
    }

    /** The view parameters of a filter, sort and offset: "/(status)/all/(sort)/number" (pure). */
    public static function url( $base, array $filter, $sort = '', $order = '', $offset = 0, ?array $spec = null )
    {
        $out = rtrim( (string)$base, '/' );
        foreach ( $filter as $k => $v )
            if ( (string)$v !== '' )
                $out .= '/(' . $k . ')/' . rawurlencode( (string)$v );
        if ( $sort !== '' && ( !$spec || $sort !== $spec['default_sort'] || $order !== $spec['default_order'] ) )
            $out .= '/(sort)/' . $sort . '/(order)/' . ( $order === 'asc' ? 'asc' : 'desc' );
        if ( (int)$offset > 0 )
            $out .= '/(offset)/' . (int)$offset;
        return $out;
    }

    /** The statuses a status filter value stands for; an empty list means every status (pure). */
    public static function statusesFor( $value, array $all, array $done )
    {
        $value = (string)$value;
        if ( $value === self::STATUS_ALL )
            return array();
        if ( $value === self::STATUS_DONE )
            return array_values( $done );
        if ( $value !== '' && $value !== self::STATUS_ACTIVE && in_array( $value, $all, true ) )
            return array( $value );
        return array_values( array_diff( $all, $done ) );
    }

    /** The %-pattern of a search text in lower case, with % and _ taken literally ('' for nothing to search). */
    public static function likePattern( $text )
    {
        $t = mb_strtolower( trim( preg_replace( '/[%_\\\\]+/u', ' ', (string)$text ) ) );
        $t = preg_replace( '/\s+/u', '%', $t );
        return $t === '' ? '' : '%' . $t . '%';
    }

    /**
     * The SQL conditions of a filter (without the reader's scope and the status). Every value is cast, checked against
     * the spec or quoted by $quote. Only plain comparisons, IN and LIKE on columns of ticketsystem_ticket, so every
     * database of the kernel runs them (doc/databases.md).
     *
     * @param int $me the reader's user id (assignee "me")
     * @return string[]
     */
    public static function conditions( array $filter, $quote, $me = 0 )
    {
        $parts = array();
        foreach ( array( 'priority', 'category' ) as $k )
            if ( isset( $filter[$k] ) && self::isIdentifier( $filter[$k] ) )
                $parts[] = "$k = " . $quote( $filter[$k] );
        if ( isset( $filter['reply'] ) )
            $parts[] = $filter['reply'] === 'staff' ? 'last_post_staff = 0' : 'last_post_staff = 1';
        if ( isset( $filter['assignee'] ) )
        {
            if ( $filter['assignee'] === 'none' )
                $parts[] = 'assignee_id = 0';
            else
                $parts[] = 'assignee_id = ' . ( $filter['assignee'] === 'me' ? (int)$me : (int)$filter['assignee'] );
        }
        if ( isset( $filter['sla'] ) )
            $parts[] = $filter['sla'] === 'breached' ? 'escalation >= 2' : 'escalation >= 1';
        if ( isset( $filter['account'] ) && preg_match( '/^(user|katalyst)-([1-9][0-9]{0,9})$/D', $filter['account'], $m ) )
            $parts[] = 'account_key = ' . $quote( $m[1] . ':' . (int)$m[2] );
        if ( isset( $filter['subscription'] ) && (int)$filter['subscription'] > 0 )
            $parts[] = 'subscription_id = ' . (int)$filter['subscription'];
        if ( isset( $filter['q'] ) && trim( (string)$filter['q'] ) !== '' )
        {
            $q = trim( (string)$filter['q'] );
            if ( preg_match( self::NUMBER_PATTERN, $q ) )
                $parts[] = 'number = ' . $quote( strtoupper( $q ) );
            else if ( ( $like = self::likePattern( $q ) ) !== '' )
                $parts[] = 'LOWER(subject) LIKE ' . $quote( $like );
        }
        return $parts;
    }

    public static function statusCondition( array $statuses, $quote )
    {
        return $statuses ? 'status IN (' . implode( ', ', array_map( $quote, array_map( 'strval', $statuses ) ) ) . ')' : '';
    }

    /**
     * The address of a filter as the left menu of the tab TicketSystem writes its links (pure): (status) first, then the
     * other filters of the menu, so the admin4 menu can mark the list shown. Filters the menu has no link for are left out.
     */
    public static function menuUri( array $filter )
    {
        // the order and the keys of the left menu's links (menu.ini [Leftmenu_ticketsystem]); no status is "active"
        $uri = 'ticketsystem/list/(status)/' . rawurlencode( isset( $filter['status'] ) ? (string)$filter['status'] : self::STATUS_ACTIVE );
        foreach ( array( 'reply', 'assignee', 'priority', 'sla' ) as $k )
            if ( isset( $filter[$k] ) && (string)$filter[$k] !== '' )
                $uri = ( $uri === 'ticketsystem/list/(status)/active' ? 'ticketsystem/list' : $uri ) . '/(' . $k . ')/' . rawurlencode( (string)$filter[$k] );
        return $uri;
    }

    /**
     * The name of a filter as the left menu names it (pure; the breadcrumb "TicketSystem > <name>"): exactly one of
     * the menu's lists, else "Tickets".
     */
    public static function listName( array $filter )
    {
        $lists = array(
            'Not resolved' => array(), 'Needs a staff reply' => array( 'reply' => 'staff' ),
            'Assigned to me' => array( 'assignee' => 'me' ), 'Unassigned' => array( 'assignee' => 'none' ),
            'Urgent' => array( 'priority' => 'urgent' ), 'Escalated' => array( 'sla' => 'escalated' ),
            'Resolved or closed' => array( 'status' => 'done' ), 'All tickets' => array( 'status' => 'all' ),
        );
        $filter = array_map( 'strval', $filter );
        // (status)/active is the default: the left menu's "Not resolved" names it, so it is marked there alone
        if ( isset( $filter['status'] ) && $filter['status'] === self::STATUS_ACTIVE )
            unset( $filter['status'] );
        ksort( $filter );
        foreach ( $lists as $name => $f )
        {
            ksort( $f );
            if ( $f === $filter )
                return $name;
        }
        return 'Tickets';
    }

    /** The badge class of a tone (ok, warn, bad, info, muted). */
    public static function badgeClass( $tone )
    {
        return in_array( (string)$tone, array( 'ok', 'warn', 'bad', 'info' ), true ) ? 'is-' . $tone : '';
    }

    /**
     * The status figures: one per status of the set with its count, then the totals (pure).
     *
     * @param array $set rows of ticketsystemStatusSet::rows() (id, label, tone, done)
     * @param array $counts status => number
     */
    public static function figures( array $set, array $counts )
    {
        $out = array( 'statuses' => array(), 'active' => 0, 'done' => 0, 'all' => 0 );
        foreach ( $counts as $n )
            $out['all'] += (int)$n;
        foreach ( $set as $s )
        {
            $n = isset( $counts[$s['id']] ) ? (int)$counts[$s['id']] : 0;
            $out['statuses'][] = array( 'id' => (string)$s['id'], 'label' => (string)$s['label'], 'tone' => (string)$s['tone'],
                                        'badge' => self::badgeClass( $s['tone'] ), 'done' => (bool)$s['done'], 'count' => $n );
            $out[$s['done'] ? 'done' : 'active'] += $n;
        }
        return $out;
    }

    /** "3 h", "2 d": how long ago a time was (pure). */
    public static function age( $time, $now )
    {
        $s = max( 0, (int)$now - (int)$time );
        if ( $s < 3600 )
            return max( 1, (int)floor( $s / 60 ) ) . ' min';
        if ( $s < 172800 )
            return (int)floor( $s / 3600 ) . ' h';
        return (int)floor( $s / 86400 ) . ' d';
    }

    /** "in 3 h", "2 d late" (pure). */
    public static function left( $seconds )
    {
        $late = (int)$seconds < 0;
        $a = self::age( 0, abs( (int)$seconds ) );
        return $late ? $a . ' late' : 'in ' . $a;
    }

    // ------------------------------------------------------------------------------------------- database

    public function spec()
    {
        return self::listSpec( $this->settings->statusSet()->statuses, $this->settings->priorities(), $this->settings->categories() );
    }

    public static function quoter()
    {
        $db = eZDB::instance();
        return function ( $v ) use ( $db ) { return "'" . $db->escapeString( (string)$v ) . "'"; };
    }

    /** The WHERE clause of a filter within the reader's scope; null when the reader may read no ticket. */
    public function where( array $filter, $withStatus = true )
    {
        $base = $this->access->listConditions();
        if ( $base === null )
            return null;
        $quote = self::quoter();
        $parts = $base === '1=1' ? array() : array( '(' . $base . ')' );
        foreach ( self::conditions( $filter, $quote, $this->access->userId() ) as $c )
            $parts[] = $c;
        if ( $withStatus )
        {
            $set = $this->settings->statusSet();
            $sc = self::statusCondition( self::statusesFor( isset( $filter['status'] ) ? $filter['status'] : '', array_keys( $set->statuses ), $set->done ), $quote );
            if ( $sc !== '' )
                $parts[] = $sc;
        }
        return $parts ? implode( ' AND ', $parts ) : '1=1';
    }

    /**
     * One page of the list.
     *
     * @return array total, tickets (ticketsystemTicket[])
     */
    public function page( array $filter, $sort, $order, $offset, $limit )
    {
        $where = $this->where( $filter );
        if ( $where === null )
            return array( 'total' => 0, 'tickets' => array() );
        $spec = $this->spec();
        $cols = isset( $spec['sorts'][$sort] ) ? $spec['sorts'][$sort] : $spec['sorts'][$spec['default_sort']];
        $dir = $order === 'asc' ? 'ASC' : 'DESC';
        $by = array();
        foreach ( $cols as $c )
            $by[] = "$c $dir";
        $by[] = "id $dir";
        return array( 'total' => ticketsystemTicket::countWhere( $where ),
                      'tickets' => ticketsystemTicket::fetchWhere( $where, $offset, $limit, implode( ', ', $by ) ) );
    }

    /** status => number of tickets of a filter, every status. */
    public function statusCounts( array $filter )
    {
        $where = $this->where( $filter, false );
        if ( $where === null )
            return array();
        $out = array();
        foreach ( array_keys( $this->settings->statusSet()->statuses ) as $s )
            $out[$s] = ticketsystemTicket::countWhere( $where . ' AND status = ' . call_user_func( self::quoter(), $s ) );
        return $out;
    }

    /**
     * The figures of a dashboard card: not resolved, needs a staff reply (with the longest wait), unassigned, the
     * highest priority, escalated. Null when the reader may read no ticket.
     */
    public function summary()
    {
        $active = $this->where( array() );
        if ( $active === null )
            return null;
        $waiting = $this->where( array( 'reply' => 'staff' ) );
        $prios = array_keys( self::identifiers( $this->settings->priorities() ) );
        $top = $prios ? (string)end( $prios ) : '';
        $oldest = ticketsystemTicket::fetchWhere( $waiting, 0, 1, 'last_post ASC, id ASC' );
        return array(
            'active' => ticketsystemTicket::countWhere( $active ),
            'needs_reply' => ticketsystemTicket::countWhere( $waiting ),
            'unassigned' => ticketsystemTicket::countWhere( $this->where( array( 'assignee' => 'none' ) ) ),
            'mine' => ticketsystemTicket::countWhere( $this->where( array( 'assignee' => 'me' ) ) ),
            'escalated' => ticketsystemTicket::countWhere( $this->where( array( 'sla' => 'escalated' ) ) ),
            'urgent_filter' => $top, 'urgent_label' => $top !== '' ? (string)$this->settings->priorities()[$top] : '',
            'urgent' => $top !== '' ? ticketsystemTicket::countWhere( $this->where( array( 'priority' => $top ) ) ) : 0,
            'oldest_waiting' => $oldest ? self::age( (int)$oldest[0]->attribute( 'last_post' ), time() ) : '',
        );
    }

    /** The ticket of a number (or index id) within the reader's scope, or null. */
    public function ticket( $number )
    {
        $number = trim( (string)$number );
        $t = ctype_digit( $number ) ? ticketsystemTicket::fetch( (int)$number )
           : ( preg_match( self::NUMBER_PATTERN, $number ) ? ticketsystemTicket::fetchByNumber( $number ) : null );
        return $t && $this->access->canRead( $t ) ? $t : null;
    }
}
