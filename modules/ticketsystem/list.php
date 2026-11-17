<?php
/**
 * ticketsystem/list: the tickets the signed-in user may read, newest activity first (doc/views.md).
 *
 * - Customers: view parameters (status)/open (the default: not resolved or closed), done or all; (offset)/<n>.
 * - Staff (ticketsystem/manage): the staff desk (ticketsystemDesk): the status figures, the filter bar (search, status,
 *   priority, queue, last post, assigned to, SLA, account, subscription), sorting and paging, all as view parameters.
 *   The filter form posts here and is redirected to the address with its view parameters (never GET parameters).
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'read', '/ticketsystem/list' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$set = $settings->statusSet();
$params = isset( $Params['UserParameters'] ) ? (array)$Params['UserParameters'] : array();
$limit = max( 5, $settings->limit( 'ListLimit', 25 ) );

if ( $access->isStaff() )
{
    $desk = new ticketsystemDesk( $access, $settings );
    $spec = $desk->spec();
    // the filter form: POST, then the address with its view parameters
    if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && $http->hasPostVariable( 'TicketFilter' ) )
    {
        if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
            return $Module->redirectTo( '/ticketsystem/list' );
        $in = $http->hasPostVariable( 'TicketReset' ) ? array() : (array)$http->postVariable( 'Filter', array() );
        $parsed = ticketsystemDesk::filter( array_map( function ( $v ) { return is_array( $v ) ? '' : (string)$v; }, $in ), $spec );
        return $Module->redirectTo( ticketsystemDesk::url( '/ticketsystem/list', $parsed['filter'] ) );
    }
    $p = ticketsystemDesk::filter( $params, $spec );
    // the customer's (status)/open is the desk's default
    if ( isset( $params['status'] ) && $params['status'] === 'open' )
        unset( $p['filter']['status'] );
    $page = $desk->page( $p['filter'], $p['sort'], $p['order'], $p['offset'], $limit );
    $names = array();
    $rows = array();
    foreach ( $page['tickets'] as $t )
        $rows[] = ticketsystemView::deskRow( $t, $names, $settings );
    $figures = ticketsystemDesk::figures( $set->rows(), $desk->statusCounts( $p['filter'] ) );
    $base = $p['filter'];
    unset( $base['status'] );
    $figures['urls'] = array( 'active' => ticketsystemDesk::url( 'ticketsystem/list', $base ),
                              'done' => ticketsystemDesk::url( 'ticketsystem/list', $base + array( 'status' => 'done' ) ),
                              'all' => ticketsystemDesk::url( 'ticketsystem/list', $base + array( 'status' => 'all' ) ) );
    foreach ( $figures['statuses'] as $i => $f )
        $figures['statuses'][$i]['url'] = ticketsystemDesk::url( 'ticketsystem/list', $base + array( 'status' => $f['id'] ) );
    $sortUrls = array();
    foreach ( array_keys( $spec['sorts'] ) as $k )
        $sortUrls[$k] = ticketsystemDesk::url( 'ticketsystem/list', $p['filter'], $k, $p['sort'] === $k && $p['order'] === 'desc' ? 'asc' : 'desc' );
    $staff = array();
    foreach ( ( new ticketsystemService( $access, $settings ) )->staffMembers() as $id => $name )
        $staff[$id] = $name;
    $chips = array();
    foreach ( $p['filter'] as $k => $v )
    {
        $rest = $p['filter'];
        unset( $rest[$k] );
        $label = $v;
        if ( isset( $spec['filters'][$k]['values'][$v] ) )
            $label = $spec['filters'][$k]['values'][$v];
        else if ( $k === 'assignee' )
            $label = $v === 'me' ? 'Me' : ( $v === 'none' ? 'Nobody' : ( isset( $staff[(int)$v] ) ? $staff[(int)$v] : '#' . $v ) );
        else if ( $k === 'account' )
            $label = ticketsystemAccount::label( str_replace( '-', ':', $v ) );
        $chips[] = array( 'name' => $spec['filters'][$k]['label'], 'value' => $label, 'url' => ticketsystemDesk::url( 'ticketsystem/list', $rest, $p['sort'], $p['order'], 0, $spec ) );
    }
    $Result = ticketsystemView::render( $user, 'tickets', 'list', array(
        'desk' => true, 'tab' => isset( $p['filter']['status'] ) && in_array( $p['filter']['status'], array( 'done', 'all' ), true ) ? $p['filter']['status'] : 'open',
        'tickets' => $rows, 'total' => $page['total'], 'filter' => $p['filter'], 'sort' => $p['sort'], 'order' => $p['order'],
        'offset' => $p['offset'], 'limit' => $limit, 'figures' => $figures, 'sort_urls' => $sortUrls, 'chips' => $chips,
        'status_options' => $spec['filters']['status']['values'], 'priorities' => $settings->priorities(), 'categories' => $settings->categories(),
        'reply_options' => $spec['filters']['reply']['values'], 'sla_options' => $spec['filters']['sla']['values'], 'staff' => $staff,
        'pager_prev' => $p['offset'] > 0 ? ticketsystemDesk::url( 'ticketsystem/list', $p['filter'], $p['sort'], $p['order'], max( 0, $p['offset'] - $limit ), $spec ) : '',
        'pager_next' => $p['offset'] + $limit < $page['total'] ? ticketsystemDesk::url( 'ticketsystem/list', $p['filter'], $p['sort'], $p['order'], $p['offset'] + $limit, $spec ) : '',
        'counts' => array( 'open' => $figures['active'], 'done' => $figures['done'], 'all' => $figures['all'] ),
        'may_create' => $access->may( 'create' ), 'is_staff' => true, 'scope' => $access->scope( 'read' ),
    ), ticketsystemView::path() );
    return;
}

$filter = isset( $params['status'] ) ? (string)$params['status'] : 'open';
if ( !in_array( $filter, array( 'open', 'done', 'all' ), true ) )
    $filter = 'open';
$offset = isset( $params['offset'] ) ? max( 0, (int)$params['offset'] ) : 0;

$statuses = $filter === 'open' ? $set->active() : ( $filter === 'done' ? $set->done : array() );
$where = $access->listConditions( $statuses );
$rows = array();
$total = 0;
$counts = array( 'open' => 0, 'done' => 0, 'all' => 0 );
if ( $where !== null )
{
    foreach ( ticketsystemTicket::fetchWhere( $where, $offset, $limit ) as $t )
        $rows[] = ticketsystemView::row( $t, $settings );
    $total = ticketsystemTicket::countWhere( $where );
    $counts['open'] = ticketsystemTicket::countWhere( $access->listConditions( $set->active() ) );
    $counts['done'] = ticketsystemTicket::countWhere( $access->listConditions( $set->done ) );
    $counts['all'] = ticketsystemTicket::countWhere( $access->listConditions() );
}

$Result = ticketsystemView::render( $user, 'tickets', 'list', array(
    'desk' => false, 'tab' => $filter, 'tickets' => $rows, 'total' => $total, 'counts' => $counts, 'filter' => $filter, 'offset' => $offset, 'limit' => $limit,
    'may_create' => $access->may( 'create' ), 'is_staff' => false, 'scope' => $access->scope( 'read' ),
), ticketsystemView::path() );
