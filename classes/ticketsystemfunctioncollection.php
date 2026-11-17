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
}
