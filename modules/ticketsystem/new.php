<?php
/**
 * ticketsystem/new: opens a ticket. POST TicketCreate with the form token, TicketSubject, TicketBody, TicketCategory,
 * TicketPriority, files TicketFiles[] ([AttachmentSettings]) and, with Katalyst, TicketSubscription (one of the
 * account's own sites; anything else is ignored).
 * Post/redirect/get to the new ticket.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'create', '/ticketsystem/new' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();

$values = array( 'subject' => '', 'body' => '', 'category' => $settings->defaultCategory(), 'priority' => $settings->defaultPriority(),
                 'subscription_id' => 0 );
$errors = array();
$fieldErrors = array();

// the account's sites, to say which one the ticket is about (Katalyst only)
$sites = array();
$customer = $access->account()->customerFor( $access->userId() );
if ( $customer && class_exists( 'expKatalystCustomerService' ) )
{
    try
    {
        $service = new expKatalystCustomerService( null, null, expKatalystAccessService::customerScope( $customer ) );
        foreach ( (array)$service->siteList( $customer ) as $s )
            $sites[] = array( 'id' => (int)$s['id'], 'label' => (string)( $s['domain'] !== '' ? $s['domain'] : '#' . $s['id'] ) );
    }
    catch ( Throwable $e )
    {
        $sites = array();
    }
}

if ( $http->hasPostVariable( 'TicketCreate' ) )
{
    $values = array(
        'subject' => (string)$http->postVariable( 'TicketSubject', '' ),
        'body' => (string)$http->postVariable( 'TicketBody', '' ),
        'category' => (string)$http->postVariable( 'TicketCategory', '' ),
        'priority' => (string)$http->postVariable( 'TicketPriority', '' ),
        'subscription_id' => (int)$http->postVariable( 'TicketSubscription', 0 ),
    );
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
        $errors[] = 'The form had expired. Nothing was sent; please send it again.';
    else
    {
        list( $upload, $uploadErrors ) = ( new ticketsystemFiles( $settings ) )->fromUpload( 'TicketFiles' );
        $r = $uploadErrors ? array( 'ok' => false, 'message' => 'Please check the files.', 'errors' => $uploadErrors )
                           : ( new ticketsystemService( $access, $settings ) )->open( $values + array( 'files' => $upload ) );
        if ( $r['ok'] )
        {
            ticketsystemView::flash( array( $r['message'] ) );
            return $Module->redirectTo( '/ticketsystem/view/' . rawurlencode( (string)$r['ticket']->attribute( 'number' ) ) );
        }
        $fieldErrors = array_filter( $r['errors'], 'is_string', ARRAY_FILTER_USE_KEY );
        $errors = array_values( array_diff_key( $r['errors'], $fieldErrors ) );
        if ( $fieldErrors )
            $errors[] = $r['message'];
    }
}

$Result = ticketsystemView::render( $user, 'tickets', 'new', array(
    'values' => $values, 'ts_errors' => $errors, 'field_errors' => $fieldErrors,
    'categories' => $settings->categories(), 'priorities' => $settings->priorities(), 'sites' => $sites,
    'attach' => ( new ticketsystemFiles( $settings ) )->formInfo(),
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'New ticket' ) ) );
