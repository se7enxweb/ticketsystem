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
 * 1.2:
 * - readers: TicketFollow, TicketUnfollow (e-mail copies of every post);
 * - who may reply: TicketCcAdd (TicketCcAddress), TicketCcRemove (TicketCcID);
 * - staff: TicketWatchAdd (TicketWatcher: a staff user id), TicketWatchRemove (TicketWatcherID), TicketMerge
 *   (TicketMergeInto: the number of the ticket that stays), TicketSplit (TicketSplitPosts[]: reply object ids,
 *   TicketSplitSubject), TicketTimeRemove (TicketTimeID); with TicketReply and TicketNote: TicketTimeSpent (45, 1:30,
 *   1h 30m), TicketTimeBillable (1/0, TicketTimeBillableSent marks the checkbox as sent), TicketTimeRemark.
 * A merged ticket redirects to the ticket it was merged into.
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
// 1.2: a merged ticket's number leads to the ticket it was merged into (when the reader may read that one)
if ( (int)$ticket->attribute( 'merged_into' ) > 0 && !( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) )
{
    $into = ticketsystemTicket::fetch( (int)$ticket->attribute( 'merged_into' ) );
    for ( $hops = 0; $into && (int)$into->attribute( 'merged_into' ) > 0 && $hops < 5; $hops++ )
        $into = ticketsystemTicket::fetch( (int)$into->attribute( 'merged_into' ) );
    if ( $into && $access->canRead( $into ) )
    {
        ticketsystemView::flash( array( ezpI18n::tr( 'extension/ticketsystem', 'Ticket %1 was merged into this ticket.', null, array( '%1' => (string)$ticket->attribute( 'number' ) ) ) ) );
        return $Module->redirectTo( '/ticketsystem/view/' . rawurlencode( (string)$into->attribute( 'number' ) ) );
    }
}

$service = new ticketsystemService( $access, $settings );
$files = new ticketsystemFiles( $settings );
$watch = new ticketsystemWatch( $access, $settings );
$time = new ticketsystemTime( $settings );
$merge = new ticketsystemMerge( $access, $settings );
$timeDraft = array( 'spent' => '', 'billable' => null, 'remark' => '' );
// the time typed with a reply or a note (staff): array( error, minutes, billable or null for the default )
$timeInput = function () use ( $http, $time, &$timeDraft, $isStaff ) {
    if ( !$isStaff || !$time->enabled() )
        return array( '', 0, null );
    $timeDraft = array( 'spent' => (string)$http->postVariable( 'TicketTimeSpent', '' ),
                        'billable' => $http->hasPostVariable( 'TicketTimeBillableSent' ) ? (bool)$http->postVariable( 'TicketTimeBillable', 0 ) : null,
                        'remark' => (string)$http->postVariable( 'TicketTimeRemark', '' ) );
    list( $err, $min ) = $time->check( $timeDraft['spent'] );
    return array( $err, $min, $timeDraft['billable'] );
};
$back = '/ticketsystem/view/' . rawurlencode( (string)$ticket->attribute( 'number' ) );
$isStaff = $access->isStaff();
$errors = array();
$fieldErrors = array();
$draft = '';
$noteDraft = '';
$cannedChosen = 0;
$statusChosen = '';
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
            // a macro's status is chosen in the form, so the reply sets it (the reader can still change it)
            $statusChosen = (string)$c->attribute( 'set_status' );
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
        else if ( ( $tIn = $timeInput() ) && $tIn[0] !== '' )
            $fieldErrors = array( 'time' => $tIn[0] );
        else
        {
            $r = $service->reply( $ticket, $draft, (string)$http->postVariable( 'TicketStatus', '' ), $upload, $isStaff ? (int)$http->postVariable( 'TicketCanned', 0 ) : 0 );
            if ( $r['ok'] && $tIn[1] > 0 )
                $time->record( $ticket, $access->userId(), $tIn[1], $tIn[2], (int)$r['post']->attribute( 'id' ), 0, $timeDraft['remark'] );
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
        else if ( ( $tIn = $timeInput() ) && $tIn[0] !== '' )
            $fieldErrors = array( 'note_time' => $tIn[0] );
        else
        {
            $r = $service->addNote( $ticket, $noteDraft, $upload );
            if ( $r['ok'] && $tIn[1] > 0 )
                $time->record( $ticket, $access->userId(), $tIn[1], $tIn[2], 0, (int)$r['note']->attribute( 'id' ), $timeDraft['remark'] );
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
    // 1.2: following, watchers, CC
    else if ( $http->hasPostVariable( 'TicketFollow' ) )
        return $done( $watch->follow( $ticket ), '#ts-watch' );
    else if ( $http->hasPostVariable( 'TicketUnfollow' ) )
        return $done( $watch->unfollow( $ticket ), '#ts-watch' );
    else if ( $http->hasPostVariable( 'TicketWatchAdd' ) )
        return $done( $watch->addWatcher( $ticket, (int)$http->postVariable( 'TicketWatcher', 0 ) ), '#ts-watch' );
    else if ( $http->hasPostVariable( 'TicketWatchRemove' ) )
        return $done( $watch->removeWatcher( $ticket, (int)$http->postVariable( 'TicketWatcherID', 0 ) ), '#ts-watch' );
    else if ( $http->hasPostVariable( 'TicketCcAdd' ) )
        return $done( $watch->addCc( $ticket, (string)$http->postVariable( 'TicketCcAddress', '' ) ), '#ts-watch' );
    else if ( $http->hasPostVariable( 'TicketCcRemove' ) )
        return $done( $watch->removeCc( $ticket, (int)$http->postVariable( 'TicketCcID', 0 ) ), '#ts-watch' );
    // 1.2: merge, split (staff)
    else if ( $http->hasPostVariable( 'TicketMerge' ) && $isStaff )
    {
        $into = ticketsystemTicket::fetchByNumber( (string)$http->postVariable( 'TicketMergeInto', '' ) );
        if ( !$into || !$access->canRead( $into ) )
            return $done( array( 'ok' => false, 'message' => 'There is no ticket with this number.' ), '#ts-merge' );
        $r = $merge->merge( $ticket, $into );
        if ( !$r['ok'] )
            return $done( $r, '#ts-merge' );
        ticketsystemView::flash( array( $r['message'] ) );
        return $Module->redirectTo( '/ticketsystem/view/' . rawurlencode( (string)$into->attribute( 'number' ) ) );
    }
    else if ( $http->hasPostVariable( 'TicketSplit' ) && $isStaff )
    {
        $r = $merge->split( $ticket, (array)$http->postVariable( 'TicketSplitPosts', array() ), (string)$http->postVariable( 'TicketSplitSubject', '' ) );
        if ( !$r['ok'] )
            return $done( array( 'ok' => false, 'message' => $r['errors'] ? implode( ' ', array_values( $r['errors'] ) ) : $r['message'] ), '#ts-merge' );
        ticketsystemView::flash( array( $r['message'] ) );
        return $Module->redirectTo( '/ticketsystem/view/' . rawurlencode( (string)$r['ticket']->attribute( 'number' ) ) );
    }
    else if ( $http->hasPostVariable( 'TicketTimeRemove' ) && $isStaff )
    {
        $e = ticketsystemTimeEntry::fetchByID( (int)$http->postVariable( 'TicketTimeID', 0 ) );
        $ok = $e && $time->remove( $e, $ticket, $access->userId() );
        return $done( array( 'ok' => $ok, 'message' => $ok ? 'The time entry was removed.' : 'This time entry cannot be removed (it was billed, or it is not of this ticket).' ), '#ts-time' );
    }
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
$timeDraft['checked'] = $timeDraft['billable'] === null ? ( $isStaff && $time->enabled() && $time->defaultBillable( $ticket ) ) : (bool)$timeDraft['billable'];
$staffChoices = array();
if ( $isStaff )
    foreach ( $set->staffChoices as $s )
        $staffChoices[] = array( 'id' => $s, 'label' => $set->label( $s ) );
$attachments = ticketsystemFiles::byPost( $service->attachments( $ticket ) );
// staff: the canned replies of the ticket's queue, each with its text filled in (the script inserts it at the cursor;
// without the script the "Insert the text" button does it on the server)
$canned = $isStaff ? $service->cannedChoices( $ticket ) : array();
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
    'staff_default' => $statusChosen !== '' && in_array( $statusChosen, $set->staffChoices, true ) ? $statusChosen : $set->role( 'StaffReply' ),
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
    // 1.2: watchers (staff), CC (readers), time (staff), merge and split (staff)
    'watchers' => $watch->watchers( $ticket ),
    'cc' => $watch->ccList( $ticket ),
    'may_cc' => $watch->mayManageCc( $ticket ),
    'max_cc' => $watch->maxCc(),
    'time_enabled' => $isStaff && $time->enabled(),
    'time_entries' => $isStaff ? $time->entries( $ticket ) : array(),
    'time_total' => ticketsystemTime::label( (int)$ticket->attribute( 'time_minutes' ) ),
    'time_billable' => ticketsystemTime::label( (int)$ticket->attribute( 'billable_minutes' ) ),
    'time_default_billable' => $isStaff && $time->enabled() ? $time->defaultBillable( $ticket ) : false,
    'time_covered' => $isStaff && $time->enabled() ? $time->coveredBy( (string)$ticket->attribute( 'account_key' ), (int)$ticket->attribute( 'subscription_id' ), (string)$ticket->attribute( 'sla_level' ) ) : '',
    'time_draft' => $timeDraft,
    'splittable' => $merge->splittable( $ticket ),
    'merged_into' => (int)$ticket->attribute( 'merged_into' ),
), ticketsystemView::path( (string)$ticket->attribute( 'number' ) ) );
