<?php
/**
 * ticketsystem/view/<number>: one ticket with all its posts and the forms (doc/views.md). A ticket the user may not
 * read answers "not found", like a number that does not exist.
 *
 * POST with the form token, then post/redirect/get:
 * - everyone who may reply: TicketReply (TicketBody, files TicketFiles[]; staff also TicketStatus and TicketCanned, a
 *   canned reply whose macro changes the ticket after the reply), TicketResolve, TicketReopen, TicketRate
 *   (TicketRating 1-5, TicketRatingComment; a customer, on a resolved or closed ticket);
 * - staff (ticketsystem/manage): TicketInsertCanned (TicketCanned: puts its text into the reply box, nothing is sent),
 *   TicketSetStatus (TicketStatus), TicketNote (TicketNoteBody, files NoteFiles[]: an internal note),
 *   TicketClaim, TicketAssign (TicketAssignee: a user id, or "none"), TicketPriority (TicketPriorityValue),
 *   TicketQueue (TicketQueueValue), TicketEscalate.
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
$files = new ticketsystemFiles( $settings );
$back = '/ticketsystem/view/' . rawurlencode( (string)$ticket->attribute( 'number' ) );
$isStaff = $access->isStaff();
$errors = array();
$fieldErrors = array();
$draft = '';
$noteDraft = '';
$cannedChosen = 0;
$done = function ( array $r, $anchor = '' ) use ( $Module, $back ) {
    if ( $r['ok'] )
        ticketsystemView::flash( array( $r['message'] ) );
    else
        ticketsystemView::flash( array(), array( $r['message'] ) );
    return $Module->redirectTo( $back . $anchor );
};

if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' )
{
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
        $errors[] = 'The form had expired. Nothing was sent; please send it again.';
    else if ( $http->hasPostVariable( 'TicketInsertCanned' ) && $isStaff )
    {
        $draft = (string)$http->postVariable( 'TicketBody', '' );
        $c = $service->cannedFor( (int)$http->postVariable( 'TicketCanned', 0 ) );
        if ( $c )
        {
            $draft = trim( $draft . ( trim( $draft ) !== '' ? "\n\n" : '' ) . $service->cannedText( $c, $ticket ) );
            $cannedChosen = (int)$c->attribute( 'id' );
        }
    }
    else if ( $http->hasPostVariable( 'TicketReply' ) )
    {
        $draft = (string)$http->postVariable( 'TicketBody', '' );
        list( $upload, $uploadErrors ) = $files->fromUpload( 'TicketFiles' );
        if ( $uploadErrors )
        {
            $errors = $uploadErrors;
            $cannedChosen = (int)$http->postVariable( 'TicketCanned', 0 );
        }
        else
        {
            $r = $service->reply( $ticket, $draft, (string)$http->postVariable( 'TicketStatus', '' ), $upload, $isStaff ? (int)$http->postVariable( 'TicketCanned', 0 ) : 0 );
            if ( $r['ok'] )
                return $done( $r, '#ts-latest' );
            $fieldErrors = array_filter( $r['errors'], 'is_string', ARRAY_FILTER_USE_KEY );
            $errors = array_values( array_diff_key( $r['errors'], $fieldErrors ) );
        }
    }
    else if ( $http->hasPostVariable( 'TicketNote' ) )
    {
        $noteDraft = (string)$http->postVariable( 'TicketNoteBody', '' );
        list( $upload, $uploadErrors ) = $files->fromUpload( 'NoteFiles' );
        if ( $uploadErrors )
            $errors = $uploadErrors;
        else
        {
            $r = $service->addNote( $ticket, $noteDraft, $upload );
            if ( $r['ok'] )
                return $done( $r, '#ts-notes' );
            $fieldErrors = array_filter( $r['errors'], 'is_string', ARRAY_FILTER_USE_KEY );
            $errors = $fieldErrors ? array() : array( $r['message'] );
        }
    }
    else if ( $http->hasPostVariable( 'TicketRate' ) )
        return $done( $service->rate( $ticket, (int)$http->postVariable( 'TicketRating', 0 ), (string)$http->postVariable( 'TicketRatingComment', '' ) ), '#ts-rate' );
    else if ( $http->hasPostVariable( 'TicketClaim' ) )
        return $done( $service->assign( $ticket, 0 ) );
    else if ( $http->hasPostVariable( 'TicketAssign' ) )
    {
        $to = (string)$http->postVariable( 'TicketAssignee', '' );
        return $done( $service->assign( $ticket, $to === 'none' ? -1 : ( $to === 'me' ? 0 : max( 1, (int)$to ) ) ) );
    }
    else if ( $http->hasPostVariable( 'TicketPriority' ) )
        return $done( $service->changePriority( $ticket, (string)$http->postVariable( 'TicketPriorityValue', '' ) ) );
    else if ( $http->hasPostVariable( 'TicketQueue' ) )
        return $done( $service->changeQueue( $ticket, (string)$http->postVariable( 'TicketQueueValue', '' ) ) );
    else if ( $http->hasPostVariable( 'TicketEscalate' ) )
        return $done( $service->escalate( $ticket ) );
    else
    {
        $action = $http->hasPostVariable( 'TicketResolve' ) ? 'resolve'
                : ( $http->hasPostVariable( 'TicketReopen' ) ? 'reopen' : ( $http->hasPostVariable( 'TicketSetStatus' ) ? 'set' : '' ) );
        if ( $action !== '' )
            return $done( $service->changeStatus( $ticket, $action, (string)$http->postVariable( 'TicketStatus', '' ) ) );
    }
}

$names = array();
$row = ticketsystemView::deskRow( $ticket, $names, $settings );
$staffChoices = array();
if ( $isStaff )
    foreach ( $set->staffChoices as $s )
        $staffChoices[] = array( 'id' => $s, 'label' => $set->label( $s ) );
$attachments = ticketsystemFiles::byPost( $service->attachments( $ticket ) );
$canned = array();
foreach ( $service->cannedReplies( (string)$ticket->attribute( 'category' ) ) as $c )
    $canned[] = array( 'id' => (int)$c->attribute( 'id' ), 'title' => (string)$c->attribute( 'title' ), 'shared' => !(int)$c->attribute( 'owner_id' ),
                       'macro' => (string)$c->attribute( 'set_status' ) !== '' || (string)$c->attribute( 'set_priority' ) !== '' || (int)$c->attribute( 'assign_self' ) );
$opener = eZContentObject::fetch( (int)$ticket->attribute( 'opener_id' ) );

$Result = ticketsystemView::render( $user, 'tickets', 'view', array(
    'ticket' => $row,
    'posts' => $service->posts( $ticket ),
    'attachments' => $attachments,
    'attach' => $files->formInfo(),
    'may_reply' => $access->canReply( $ticket ) && !$set->isClosed( $ticket->attribute( 'status' ) ),
    'may_resolve' => $access->canReply( $ticket ) && $set->customerMayResolve( $ticket->attribute( 'status' ) ),
    'may_reopen' => $access->canReply( $ticket ) && $set->customerMayReopen( $ticket->attribute( 'status' ) ),
    'may_rate' => $service->mayRate( $ticket ),
    'rating' => array( 'score' => (int)$ticket->attribute( 'rating' ), 'comment' => (string)$ticket->attribute( 'rating_comment' ),
                       'rated' => (int)$ticket->attribute( 'rated' ), 'enabled' => $settings->enabled( 'CSATSettings', 'Enabled' ) ),
    'is_staff' => $isStaff,
    'staff_choices' => $staffChoices,
    'staff_default' => $set->role( 'StaffReply' ),
    // staff only: the internal notes, the history, the people, the queues, the canned replies, the SLA
    'notes' => $service->notes( $ticket ),
    'history' => $service->history( $ticket ),
    'staff' => $isStaff ? $service->staffMembers() : array(),
    'priorities' => $settings->priorities(),
    'categories' => $settings->categories(),
    'canned' => $canned,
    'canned_chosen' => $cannedChosen,
    'sla' => $isStaff ? $service->slaState( $ticket ) : null,
    'opener' => $isStaff && $opener ? (string)$opener->attribute( 'name' ) : '',
    'me' => $access->userId(),
    'draft' => $draft,
    'note_draft' => $noteDraft,
    'ts_errors' => $errors,
    'field_errors' => $fieldErrors,
    'following' => in_array( (int)$ticket->attribute( 'node_id' ), array_map( 'intval', (array)eZSubtreeNotificationRule::fetchNodesForUserID( $access->userId(), false ) ), true ),
), ticketsystemView::path( (string)$ticket->attribute( 'number' ) ) );
