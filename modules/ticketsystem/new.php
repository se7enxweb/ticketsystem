<?php
/**
 * ticketsystem/new: opens a ticket. POST TicketCreate with the form token, TicketSubject, TicketBody, TicketCategory,
 * TicketPriority, files TicketFiles[] ([AttachmentSettings]) and, with Katalyst, TicketSubscription (one of the
 * account's own sites; anything else is ignored).
 * Post/redirect/get to the new ticket.
 *
 * 1.2, staff (ticketsystem/manage): a ticket for a customer (doc/staff-opened-tickets.md). The view parameters
 * (user)/<id> or (customer)/<Katalyst customer id> preselect the customer (the Katalyst customer page links here);
 * the form sends TicketForUser (a user id or login) or TicketForCustomer, and TicketNotify (1: the opening post is
 * mailed to the customer).
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
$isStaff = $access->isStaff();
// staff: the customer the ticket is for (view parameters preselect; the form posts them back)
$for = array( 'user' => isset( $Params['UserParameters']['user'] ) ? (string)$Params['UserParameters']['user'] : '',
              'customer' => isset( $Params['UserParameters']['customer'] ) && ctype_digit( (string)$Params['UserParameters']['customer'] ) ? (int)$Params['UserParameters']['customer'] : 0,
              'notify' => true, 'label' => '' );
if ( $isStaff && $http->hasPostVariable( 'TicketCreate' ) )
{
    $for['user'] = trim( (string)$http->postVariable( 'TicketForUser', '' ) );
    $for['customer'] = (int)$http->postVariable( 'TicketForCustomer', 0 );
    $for['notify'] = (bool)$http->postVariable( 'TicketNotify', 0 );
}
$forCustomer = $isStaff && ( $for['user'] !== '' || $for['customer'] > 0 );
if ( $forCustomer )
{
    list( $forUser, $forKey, $forWhy ) = ( new ticketsystemService( $access, $settings ) )->customerFor( $for['user'], $for['customer'] );
    $for['label'] = $forUser ? (string)( $forUser->contentObject() ? $forUser->contentObject()->attribute( 'name' ) : '' ) . ' (' . ticketsystemAccount::label( $forKey ) . ')' : '';
    if ( !$forUser )
        $fieldErrors['customer'] = $forWhy;
}

// the account's sites, to say which one the ticket is about (Katalyst only)
$sites = array();
$customer = $forCustomer ? ( isset( $forKey ) && preg_match( '/^katalyst:(\d+)$/', $forKey, $fm ) && class_exists( 'expKatalystCustomer' ) ? expKatalystCustomer::fetch( (int)$fm[1] ) : null )
                        : $access->account()->customerFor( $access->userId() );
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

// (subscription)/<id>: "Get help with this service" preselects the site, when it is one of the account's own
$wanted = isset( $Params['UserParameters']['subscription'] ) ? (int)$Params['UserParameters']['subscription'] : 0;
foreach ( $sites as $s )
    if ( $wanted > 0 && $s['id'] === $wanted )
        $values['subscription_id'] = $wanted;

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
        $service = new ticketsystemService( $access, $settings );
        $r = $uploadErrors ? array( 'ok' => false, 'message' => 'Please check the files.', 'errors' => $uploadErrors )
                           : ( $forCustomer ? $service->openFor( $values + array( 'files' => $upload, 'user' => $for['user'], 'customer' => $for['customer'], 'notify' => $for['notify'] ) )
                                            : $service->open( $values + array( 'files' => $upload ) ) );
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
    'is_staff' => $isStaff, 'for' => $for, 'for_customer' => $forCustomer,
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'New ticket' ) ) );
