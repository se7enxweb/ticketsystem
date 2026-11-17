<?php
/**
 * ticketsystem/view/<number>: one ticket with all its posts and the reply form. A ticket the user may not read
 * answers "not found", like a number that does not exist.
 *
 * POST with the form token, then post/redirect/get: TicketReply (TicketBody; staff also TicketStatus), TicketResolve
 * (the customer marks it solved), TicketReopen (a solved ticket again), TicketSetStatus (staff: TicketStatus).
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'read', '/ticketsystem/view/' . ( isset( $Params['Number'] ) ? rawurlencode( (string)$Params['Number'] ) : '' ) ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$set = $settings->statusSet();

$number = isset( $Params['Number'] ) ? (string)$Params['Number'] : '';
$ticket = ctype_digit( $number ) ? ticketsystemTicket::fetch( (int)$number ) : ticketsystemTicket::fetchByNumber( $number );
if ( !$ticket || !$access->canRead( $ticket ) )
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
if ( ctype_digit( $number ) )
    return $Module->redirectTo( '/ticketsystem/view/' . rawurlencode( (string)$ticket->attribute( 'number' ) ) );

$service = new ticketsystemService( $access, $settings );
$back = '/ticketsystem/view/' . rawurlencode( (string)$ticket->attribute( 'number' ) );
$errors = array();
$fieldErrors = array();
$draft = '';

if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' )
{
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
        $errors[] = 'The form had expired. Nothing was sent; please send it again.';
    else if ( $http->hasPostVariable( 'TicketReply' ) )
    {
        $draft = (string)$http->postVariable( 'TicketBody', '' );
        $r = $service->reply( $ticket, $draft, (string)$http->postVariable( 'TicketStatus', '' ) );
        if ( $r['ok'] )
        {
            ticketsystemView::flash( array( $r['message'] ) );
            return $Module->redirectTo( $back . '#ts-latest' );
        }
        $fieldErrors = array_filter( $r['errors'], 'is_string', ARRAY_FILTER_USE_KEY );
        $errors = array_values( array_diff_key( $r['errors'], $fieldErrors ) );
    }
    else
    {
        $action = $http->hasPostVariable( 'TicketResolve' ) ? 'resolve'
                : ( $http->hasPostVariable( 'TicketReopen' ) ? 'reopen' : ( $http->hasPostVariable( 'TicketSetStatus' ) ? 'set' : '' ) );
        if ( $action !== '' )
        {
            $r = $service->changeStatus( $ticket, $action, (string)$http->postVariable( 'TicketStatus', '' ) );
            if ( $r['ok'] )
                ticketsystemView::flash( array( $r['message'] ) );
            else
                ticketsystemView::flash( array(), array( $r['message'] ) );
            return $Module->redirectTo( $back );
        }
    }
}

$row = ticketsystemView::row( $ticket, $settings );
$isStaff = $access->isStaff();
$staffChoices = array();
if ( $isStaff )
    foreach ( $set->staffChoices as $s )
        $staffChoices[] = array( 'id' => $s, 'label' => $set->label( $s ) );

$Result = ticketsystemView::render( $user, 'tickets', 'view', array(
    'ticket' => $row,
    'posts' => $service->posts( $ticket ),
    'may_reply' => $access->canReply( $ticket ) && !$set->isClosed( $ticket->attribute( 'status' ) ),
    'may_resolve' => $access->canReply( $ticket ) && $set->customerMayResolve( $ticket->attribute( 'status' ) ),
    'may_reopen' => $access->canReply( $ticket ) && $set->customerMayReopen( $ticket->attribute( 'status' ) ),
    'is_staff' => $isStaff,
    'staff_choices' => $staffChoices,
    'staff_default' => $set->role( 'StaffReply' ),
    'draft' => $draft,
    'ts_errors' => $errors,
    'field_errors' => $fieldErrors,
    'following' => in_array( (int)$ticket->attribute( 'node_id' ), array_map( 'intval', (array)eZSubtreeNotificationRule::fetchNodesForUserID( $access->userId(), false ) ), true ),
), ticketsystemView::path( (string)$ticket->attribute( 'number' ) ) );
