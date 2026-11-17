<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The fetch functions of the module ticketsystem (modules/ticketsystem/function_definition.php). Every one answers
 * for the signed-in user, within their read scope and policies, the same as the views; array( 'result' => false )
 * when the user may not.
 */
class ticketsystemFunctionCollection
{
    protected static function access()
    {
        return new ticketsystemAccess( eZUser::currentUser() );
    }

    protected static function allowed( ticketsystemAccess $access, $function )
    {
        return $access->isSignedIn() && $access->may( $function );
    }

    /** The search (doc/search.md): staff with ticketsystem/report; customers only with [SearchSettings] Customers=enabled. */
    public static function fetchSearch( $filter = array(), $offset = 0, $limit = 25 )
    {
        $settings = ticketsystemSettings::instance();
        $access = self::access();
        $customers = $settings->enabled( 'SearchSettings', 'Customers', 'disabled' );
        if ( !self::allowed( $access, $customers ? 'read' : 'report' ) )
            return array( 'result' => false );
        $desk = new ticketsystemDesk( $access, $settings );
        $params = array();
        foreach ( (array)$filter as $k => $v )
            if ( is_scalar( $v ) )
                $params[(string)$k] = rawurlencode( (string)$v );
        $f = ticketsystemReport::searchFilter( $params, $desk->spec() );
        if ( !$access->isStaff() )
            unset( $f['assignee'], $f['customer'], $f['account'] );
        $found = ( new ticketsystemReport( $access, $settings ) )->search( $f, max( 0, (int)$offset ), max( 1, min( 200, (int)$limit ) ) );
        $names = array();
        $rows = array();
        foreach ( $found['tickets'] as $t )
            $rows[] = ticketsystemView::deskRow( $t, $names, $settings ) + array( 'in_note' => isset( $found['in_notes'][(int)$t->attribute( 'id' )] ) );
        return array( 'result' => array( 'total' => $found['total'], 'tickets' => $rows ) );
    }

    /** The report of a period (doc/reports.md): staff with ticketsystem/report. */
    public static function fetchReport( $from = '', $to = '', $queue = '', $period = '' )
    {
        $settings = ticketsystemSettings::instance();
        $access = self::access();
        if ( !self::allowed( $access, 'report' ) )
            return array( 'result' => false );
        $range = ticketsystemReport::range( array( 'from' => (string)$from, 'to' => (string)$to ), (int)$settings->value( 'ReportSettings', 'DefaultDays', 30 ),
                                            time(), (int)$settings->value( 'ReportSettings', 'MaxDays', ticketsystemReport::MAX_DAYS ) );
        $queue = isset( $settings->categories()[(string)$queue] ) ? (string)$queue : '';
        return array( 'result' => ( new ticketsystemReport( $access, $settings ) )->report( $range['from'], $range['to'], $queue, (string)$period )
                                  + array( 'range' => $range ) );
    }

    /** The canned replies the user may use (doc/canned-replies.md): staff (ticketsystem/manage). */
    public static function fetchCannedReplies( $queue = '' )
    {
        $access = self::access();
        if ( !$access->isSignedIn() || !$access->isStaff() )
            return array( 'result' => false );
        $out = array();
        foreach ( ( new ticketsystemService( $access ) )->cannedReplies( (string)$queue ) as $c )
            $out[] = array( 'id' => (int)$c->attribute( 'id' ), 'title' => (string)$c->attribute( 'title' ), 'shared' => !(int)$c->attribute( 'owner_id' ),
                            'category' => (string)$c->attribute( 'category' ), 'set_status' => (string)$c->attribute( 'set_status' ),
                            'set_priority' => (string)$c->attribute( 'set_priority' ), 'assign_self' => (bool)$c->attribute( 'assign_self' ) );
        return array( 'result' => $out );
    }

    // ------------------------------------------------------------------------------- SLA and ratings

    protected static function readable( $ticketId, ticketsystemAccess $access )
    {
        $t = ticketsystemTicket::fetch( (int)$ticketId );
        return $t && $access->canRead( $t ) ? $t : null;
    }

    protected static function staffReports( ticketsystemAccess $access )
    {
        return ( $access->may( 'report' ) || $access->isStaff() ) && $access->scope( 'read' ) >= ticketsystemAccess::ALL;
    }

    /**
     * The SLA state of a ticket. Staff: ticketsystemService::slaState() (both targets, labels, the escalation log);
     * anyone else who may read it: the hint (the first response expected by, while staff has not answered).
     */
    public static function sla( $ticketId )
    {
        $access = self::access();
        $t = self::readable( $ticketId, $access );
        if ( !$t )
            return array( 'result' => null );
        $service = new ticketsystemService( $access );
        $s = $service->slaState( $t );
        if ( !$s || $access->isStaff() )
            return array( 'result' => $s );
        $settings = ticketsystemSettings::instance();
        if ( !$settings->enabled( 'SLASettings', 'CustomerHint', 'enabled' ) || (int)$t->attribute( 'responded' ) > 0
             || !in_array( $s['response']['state'], array( 'ok', 'warn' ), true ) )
            return array( 'result' => array( 'hint' => false ) );
        return array( 'result' => array( 'hint' => true, 'response_due' => (int)$s['response']['due'], 'level_label' => $s['level_label'] ) );
    }

    public static function slaBoard( $state = '', $limit = 100 )
    {
        $access = self::access();
        if ( !$access->isStaff() )
            return array( 'result' => null );
        return array( 'result' => ticketsystemEscalation::board( $access, null, (string)$state, (int)$limit ) );
    }

    public static function escalations( $ticketId )
    {
        $access = self::access();
        $t = $access->isStaff() ? self::readable( $ticketId, $access ) : null;
        return array( 'result' => $t ? ticketsystemEscalation::logFor( (int)$t->attribute( 'id' ) ) : null );
    }

    public static function rating( $ticketId )
    {
        $access = self::access();
        $t = self::readable( $ticketId, $access );
        return array( 'result' => $t ? ( new ticketsystemCsat() )->forTicket( $t ) : null );
    }

    public static function csatSummary( $from = 0, $to = 0, $category = '', $assigneeId = 0 )
    {
        $access = self::access();
        if ( !self::staffReports( $access ) )
            return array( 'result' => null );
        return array( 'result' => ( new ticketsystemCsat() )->summary( array( 'from' => (int)$from, 'to' => (int)$to,
                                                                              'category' => (string)$category, 'assignee_id' => (int)$assigneeId ) ) );
    }

    public static function csatLatest( $from = 0, $to = 0, $offset = 0, $limit = 50 )
    {
        $access = self::access();
        if ( !self::staffReports( $access ) )
            return array( 'result' => null );
        return array( 'result' => ( new ticketsystemCsat() )->latest( array( 'from' => (int)$from, 'to' => (int)$to ), (int)$offset, min( 200, max( 1, (int)$limit ) ) ) );
    }

    // ------------------------------------------------------------------------------- the customer's summary

    /**
     * The support card of a customer's home (doc/views.md): the tickets the signed-in user reads in ticketsystem/list,
     * within their read scope (ticketsystemAccess::listConditions()). False without ticketsystem/read.
     *
     * result: counts (open, waiting, to_rate, done, all), open (rows of ticketsystemView::row() plus waiting,
     * subscription_id; those waiting on the customer first, then newest activity), to_rate (resolved tickets the
     * customer may still rate), may_create, rating (the ratings are on).
     *
     * @param int $limit rows of each list (1-50)
     * @param int $subscriptionId only the tickets about this site (0: all)
     */
    public static function customerSummary( $limit = 5, $subscriptionId = 0 )
    {
        $access = self::access();
        if ( !self::allowed( $access, 'read' ) )
            return array( 'result' => false );
        $settings = ticketsystemSettings::instance();
        $set = $settings->statusSet();
        $limit = max( 1, min( 50, (int)$limit ) );
        $rating = $settings->enabled( 'CSATSettings', 'Enabled' );
        $out = array( 'counts' => array( 'open' => 0, 'waiting' => 0, 'to_rate' => 0, 'done' => 0, 'all' => 0 ),
                      'open' => array(), 'to_rate' => array(), 'may_create' => $access->may( 'create' ), 'rating' => $rating );
        $extra = (int)$subscriptionId > 0 ? ' AND subscription_id = ' . (int)$subscriptionId : '';
        $open = $access->listConditions( $set->active() );
        if ( $open === null )
            return array( 'result' => $out );
        $waitingStatus = $set->role( 'StaffReply' );
        $waiting = $access->listConditions( array( $waitingStatus ) ) . $extra;
        $row = function ( ticketsystemTicket $t ) use ( $settings, $waitingStatus ) {
            $r = ticketsystemView::row( $t, $settings );
            return $r + array( 'waiting' => $r['status'] === $waitingStatus || ( $r['last_post_staff'] && !$r['done'] ),
                               'subscription_id' => (int)$t->attribute( 'subscription_id' ), 'rating' => (int)$t->attribute( 'rating' ) );
        };
        $seen = array();
        foreach ( ticketsystemTicket::fetchWhere( $waiting, 0, $limit ) as $t )
        {
            $seen[(int)$t->attribute( 'id' )] = true;
            $out['open'][] = $row( $t );
        }
        if ( count( $out['open'] ) < $limit )
            foreach ( ticketsystemTicket::fetchWhere( $open . $extra, 0, $limit ) as $t )
                if ( !isset( $seen[(int)$t->attribute( 'id' )] ) && count( $out['open'] ) < $limit )
                    $out['open'][] = $row( $t );
        $out['counts']['open'] = ticketsystemTicket::countWhere( $open . $extra );
        $out['counts']['waiting'] = ticketsystemTicket::countWhere( $waiting );
        $out['counts']['done'] = ticketsystemTicket::countWhere( $access->listConditions( $set->done ) . $extra );
        $out['counts']['all'] = ticketsystemTicket::countWhere( $access->listConditions() . $extra );
        if ( $rating )
        {
            // the customer rates a ticket they opened or reply on (ticketsystemService::mayRate), once per ticket here
            $unrated = $access->listConditions( $set->done ) . $extra . ' AND rated = 0 AND opener_id = ' . (int)$access->userId();
            $service = new ticketsystemService( $access, $settings );
            foreach ( ticketsystemTicket::fetchWhere( $unrated, 0, $limit ) as $t )
                if ( $service->mayRate( $t ) )
                    $out['to_rate'][] = $row( $t );
            $out['counts']['to_rate'] = ticketsystemTicket::countWhere( $unrated );
        }
        return array( 'result' => $out );
    }
}
