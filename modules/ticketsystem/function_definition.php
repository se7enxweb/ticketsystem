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
 * SLA and ratings (doc/sla.md, doc/csat.md):
 *
 *   fetch( 'ticketsystem', 'sla', hash( 'ticket_id', <id> ) )            the SLA state of a ticket (staff: both targets and
 *                                                                        the escalation log; a customer: the hint only)
 *   fetch( 'ticketsystem', 'sla_board', hash( 'state', '', 'limit', 100 ) )  staff: the active tickets with a target
 *   fetch( 'ticketsystem', 'escalations', hash( 'ticket_id', <id> ) )    staff: the escalation log of a ticket
 *   fetch( 'ticketsystem', 'rating', hash( 'ticket_id', <id> ) )         the rating of a ticket (its readers)
 *   fetch( 'ticketsystem', 'csat_summary', hash( 'from', <ts>, 'to', <ts>, 'category', '', 'assignee_id', 0 ) )
 *                                                                        staff (report, read scope All): the aggregate
 *   fetch( 'ticketsystem', 'csat_latest', hash( 'from', <ts>, 'to', <ts>, 'offset', 0, 'limit', 50 ) )
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

// SLA and ratings
$tsCall = function ( $method ) {
    return array( 'class' => 'ticketsystemFunctionCollection', 'method' => $method );
};
$tsParam = function ( $name, $type, $required, $default = null ) {
    $p = array( 'name' => $name, 'type' => $type, 'required' => $required );
    if ( !$required )
        $p['default'] = $default;
    return $p;
};
$FunctionList['sla'] = array( 'name' => 'sla', 'operation_types' => array( 'read' ), 'call_method' => $tsCall( 'sla' ),
                              'parameter_type' => 'standard', 'parameters' => array( $tsParam( 'ticket_id', 'integer', true ) ) );
$FunctionList['sla_board'] = array( 'name' => 'sla_board', 'operation_types' => array( 'read' ), 'call_method' => $tsCall( 'slaBoard' ),
                                    'parameter_type' => 'standard',
                                    'parameters' => array( $tsParam( 'state', 'string', false, '' ), $tsParam( 'limit', 'integer', false, 100 ) ) );
$FunctionList['escalations'] = array( 'name' => 'escalations', 'operation_types' => array( 'read' ), 'call_method' => $tsCall( 'escalations' ),
                                      'parameter_type' => 'standard', 'parameters' => array( $tsParam( 'ticket_id', 'integer', true ) ) );
$FunctionList['rating'] = array( 'name' => 'rating', 'operation_types' => array( 'read' ), 'call_method' => $tsCall( 'rating' ),
                                 'parameter_type' => 'standard', 'parameters' => array( $tsParam( 'ticket_id', 'integer', true ) ) );
$FunctionList['csat_summary'] = array( 'name' => 'csat_summary', 'operation_types' => array( 'read' ), 'call_method' => $tsCall( 'csatSummary' ),
                                       'parameter_type' => 'standard',
                                       'parameters' => array( $tsParam( 'from', 'integer', false, 0 ), $tsParam( 'to', 'integer', false, 0 ),
                                                              $tsParam( 'category', 'string', false, '' ), $tsParam( 'assignee_id', 'integer', false, 0 ) ) );
$FunctionList['csat_latest'] = array( 'name' => 'csat_latest', 'operation_types' => array( 'read' ), 'call_method' => $tsCall( 'csatLatest' ),
                                      'parameter_type' => 'standard',
                                      'parameters' => array( $tsParam( 'from', 'integer', false, 0 ), $tsParam( 'to', 'integer', false, 0 ),
                                                             $tsParam( 'offset', 'integer', false, 0 ), $tsParam( 'limit', 'integer', false, 50 ) ) );
