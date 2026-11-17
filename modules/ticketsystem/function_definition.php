<?php
/**
 * The fetch functions of the module ticketsystem (doc/reports.md, doc/search.md, doc/canned-replies.md). Each one
 * answers for the signed-in user only, within their read scope and policies, exactly as the views do:
 *
 *   fetch( 'ticketsystem', 'search', hash( 'filter', hash( 'q', 'disk', 'status', 'all' ), 'offset', 0, 'limit', 25 ) )
 *       result: total, tickets (rows of ticketsystemView::deskRow, in_note), or false without the policy
 *   fetch( 'ticketsystem', 'report', hash( 'from', '2026-11-01', 'to', '2026-11-30', 'queue', 'billing' ) )
 *       result: ticketsystemReport::report() (staff with ticketsystem/report only), or false
 *   fetch( 'ticketsystem', 'canned_replies', hash( 'queue', 'billing' ) )
 *       result: the canned replies the user may use (id, title, shared, category, set_status, set_priority, assign_self)
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

$FunctionList = array();
$FunctionList['search'] = array(
    'name' => 'search',
    'operation_types' => array( 'read' ),
    'call_method' => array( 'class' => 'ticketsystemFunctionCollection', 'method' => 'fetchSearch' ),
    'parameter_type' => 'standard',
    'parameters' => array(
        array( 'name' => 'filter', 'type' => 'array', 'required' => false, 'default' => array() ),
        array( 'name' => 'offset', 'type' => 'integer', 'required' => false, 'default' => 0 ),
        array( 'name' => 'limit', 'type' => 'integer', 'required' => false, 'default' => 25 ) ) );
$FunctionList['report'] = array(
    'name' => 'report',
    'operation_types' => array( 'read' ),
    'call_method' => array( 'class' => 'ticketsystemFunctionCollection', 'method' => 'fetchReport' ),
    'parameter_type' => 'standard',
    'parameters' => array(
        array( 'name' => 'from', 'type' => 'string', 'required' => false, 'default' => '' ),
        array( 'name' => 'to', 'type' => 'string', 'required' => false, 'default' => '' ),
        array( 'name' => 'queue', 'type' => 'string', 'required' => false, 'default' => '' ),
        array( 'name' => 'period', 'type' => 'string', 'required' => false, 'default' => '' ) ) );
$FunctionList['canned_replies'] = array(
    'name' => 'canned_replies',
    'operation_types' => array( 'read' ),
    'call_method' => array( 'class' => 'ticketsystemFunctionCollection', 'method' => 'fetchCannedReplies' ),
    'parameter_type' => 'standard',
    'parameters' => array(
        array( 'name' => 'queue', 'type' => 'string', 'required' => false, 'default' => '' ) ) );
