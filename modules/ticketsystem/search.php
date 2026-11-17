<?php
/**
 * ticketsystem/search: the search of the help desk (doc/search.md), within the reader's read scope.
 *
 * Who: staff with ticketsystem/report; customers (ticketsystem/read) only when [SearchSettings] Customers=enabled, and
 * then only their own scope and never the internal notes.
 * What: the text in the subjects and the text of every customer-visible post; for staff also in the internal notes
 * (marked "in an internal note"). Filters: number, status, priority, queue, assignee, customer (name or e-mail of the
 * opener), account, created from / to.
 * How: the form posts here (SearchRun, with the form token) and is redirected to view parameters
 * (q)/<text>/(status)/<status>/(queue)/<queue>/(from)/<YYYY-MM-DD>/...: never GET parameters.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$settings = ticketsystemSettings::instance();
$customersMay = $settings->enabled( 'SearchSettings', 'Customers', 'disabled' );
if ( ( $denied = ticketsystemView::guard( $Module, $customersMay ? 'read' : 'report', '/ticketsystem/search' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$isStaff = $access->isStaff();
$desk = new ticketsystemDesk( $access, $settings );
$spec = $desk->spec();

if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' )
{
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
    {
        ticketsystemView::flash( array(), array( 'The form had expired. Please search again.' ) );
        return $Module->redirectTo( '/ticketsystem/search' );
    }
    if ( $http->hasPostVariable( 'SearchReset' ) )
        return $Module->redirectTo( '/ticketsystem/search' );
    $in = array();
    foreach ( array( 'q' => 'SearchText', 'number' => 'SearchNumber', 'status' => 'SearchStatus', 'priority' => 'SearchPriority',
                     'queue' => 'SearchQueue', 'assignee' => 'SearchAssignee', 'customer' => 'SearchCustomer', 'from' => 'SearchFrom',
                     'to' => 'SearchTo' ) as $k => $field )
    {
        $v = $http->postVariable( $field, '' );
        if ( is_string( $v ) && trim( $v ) !== '' )
            $in[$k] = rawurlencode( trim( $v ) );
    }
    $filter = ticketsystemReport::searchFilter( $in, $spec );
    if ( !$isStaff )
        unset( $filter['assignee'], $filter['customer'], $filter['account'] );
    return $Module->redirectTo( ticketsystemReport::searchUrl( '/ticketsystem/search', $filter ) );
}

$params = isset( $Params['UserParameters'] ) ? (array)$Params['UserParameters'] : array();
$filter = ticketsystemReport::searchFilter( $params, $spec );
if ( !$isStaff )
    unset( $filter['assignee'], $filter['customer'], $filter['account'] );
$offset = isset( $params['offset'] ) && ctype_digit( (string)$params['offset'] ) ? (int)$params['offset'] : 0;
$limit = max( 10, $settings->limit( 'SearchLimit', 50 ) );
$searched = (bool)$filter;
$found = $searched ? ( new ticketsystemReport( $access, $settings ) )->search( $filter, $offset, $limit )
                   : array( 'total' => 0, 'tickets' => array(), 'in_notes' => array() );
$names = array();
$rows = array();
foreach ( $found['tickets'] as $t )
    $rows[] = ticketsystemView::deskRow( $t, $names, $settings ) + array( 'in_note' => isset( $found['in_notes'][(int)$t->attribute( 'id' )] ) );

$statusChoices = array();
foreach ( $spec['filters']['status']['values'] as $k => $label )
    $statusChoices[] = array( 'id' => (string)$k, 'label' => (string)$label );
$staff = $isStaff ? ( new ticketsystemService( $access, $settings ) )->staffMembers() : array();
$base = ticketsystemReport::searchUrl( '/ticketsystem/search', $filter );
$Result = ticketsystemView::render( $user, 'search', 'search', array(
    'q' => isset( $filter['q'] ) ? $filter['q'] : '',
    'filter' => $filter + array( 'q' => '', 'number' => '', 'status' => '', 'priority' => '', 'category' => '', 'assignee' => '', 'customer' => '',
                                 'account' => '', 'from' => '', 'to' => '' ),
    'searched' => $searched,
    'tickets' => $rows, 'total' => $found['total'], 'offset' => $offset, 'limit' => $limit,
    'is_staff' => $isStaff, 'searches_notes' => $isStaff,
    'status_choices' => $statusChoices, 'priorities' => $settings->priorities(), 'categories' => $settings->categories(), 'staff' => $staff,
    'pager_prev' => $offset > 0 ? ticketsystemReport::searchUrl( '/ticketsystem/search', $filter, max( 0, $offset - $limit ) ) : '',
    'pager_next' => $offset + $limit < $found['total'] ? ticketsystemReport::searchUrl( '/ticketsystem/search', $filter, $offset + $limit ) : '',
    'base_url' => $base,
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Search' ) ) );
