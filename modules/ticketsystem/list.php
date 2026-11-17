<?php
/**
 * ticketsystem/list: the tickets the signed-in user may read (its own, its account's, or all for support staff),
 * newest activity first. View parameters: (status)/open (the default: not resolved or closed), done or all;
 * (offset)/<n>.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'read', '/ticketsystem/list' ) ) !== null )
    return $denied;
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$set = $settings->statusSet();

$filter = isset( $Params['UserParameters']['status'] ) ? (string)$Params['UserParameters']['status'] : 'open';
if ( !in_array( $filter, array( 'open', 'done', 'all' ), true ) )
    $filter = 'open';
$offset = isset( $Params['UserParameters']['offset'] ) ? max( 0, (int)$Params['UserParameters']['offset'] ) : 0;
$limit = max( 5, $settings->limit( 'ListLimit', 25 ) );

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
    'tickets' => $rows, 'total' => $total, 'counts' => $counts, 'filter' => $filter, 'offset' => $offset, 'limit' => $limit,
    'may_create' => $access->may( 'create' ), 'is_staff' => $access->isStaff(), 'scope' => $access->scope( 'read' ),
), ticketsystemView::path() );
