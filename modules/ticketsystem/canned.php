<?php
/**
 * ticketsystem/canned: the canned replies and macros of the staff (doc/canned.md), policy ticketsystem/manage. The list
 * (shared ones and one's own), (edit)/<id> or (edit)/new for the form. POST with the form token, then post/redirect/get:
 * CannedSave (CannedID, CannedTitle, CannedBody, CannedCategory, CannedStatus, CannedPriority, CannedAssignSelf,
 * CannedShared), CannedDelete (CannedID).
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'manage', '/ticketsystem/canned' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$service = new ticketsystemService( $access, $settings );
$params = isset( $Params['UserParameters'] ) ? (array)$Params['UserParameters'] : array();
$edit = isset( $params['edit'] ) ? (string)$params['edit'] : '';
$errors = array();
$fieldErrors = array();
$values = array( 'id' => 0, 'title' => '', 'body' => '', 'category' => '', 'set_status' => '', 'set_priority' => '', 'assign_self' => 0, 'shared' => 1 );

if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' )
{
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
        $errors[] = 'The form had expired. Nothing was saved; please send it again.';
    else if ( $http->hasPostVariable( 'CannedDelete' ) )
    {
        $r = $service->deleteCanned( (int)$http->postVariable( 'CannedID', 0 ) );
        ticketsystemView::flash( $r['ok'] ? array( $r['message'] ) : array(), $r['ok'] ? array() : array( $r['message'] ) );
        return $Module->redirectTo( '/ticketsystem/canned' );
    }
    else if ( $http->hasPostVariable( 'CannedSave' ) )
    {
        $values = array( 'id' => (int)$http->postVariable( 'CannedID', 0 ), 'title' => (string)$http->postVariable( 'CannedTitle', '' ),
                         'body' => (string)$http->postVariable( 'CannedBody', '' ), 'category' => (string)$http->postVariable( 'CannedCategory', '' ),
                         'set_status' => (string)$http->postVariable( 'CannedStatus', '' ), 'set_priority' => (string)$http->postVariable( 'CannedPriority', '' ),
                         'assign_self' => $http->hasPostVariable( 'CannedAssignSelf' ) ? 1 : 0, 'shared' => $http->hasPostVariable( 'CannedShared' ) ? 1 : 0 );
        $r = $service->saveCanned( $values['id'], $values );
        if ( $r['ok'] )
        {
            ticketsystemView::flash( array( $r['message'] ) );
            return $Module->redirectTo( '/ticketsystem/canned' );
        }
        $fieldErrors = array_filter( $r['errors'], 'is_string', ARRAY_FILTER_USE_KEY );
        $errors[] = $r['message'];
        $edit = $values['id'] ? (string)$values['id'] : 'new';
    }
}
if ( ctype_digit( $edit ) && !$http->hasPostVariable( 'CannedSave' ) )
{
    $c = $service->cannedFor( (int)$edit );
    if ( !$c )
        return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
    $values = array( 'id' => (int)$c->attribute( 'id' ), 'title' => (string)$c->attribute( 'title' ), 'body' => (string)$c->attribute( 'body' ),
                     'category' => (string)$c->attribute( 'category' ), 'set_status' => (string)$c->attribute( 'set_status' ),
                     'set_priority' => (string)$c->attribute( 'set_priority' ), 'assign_self' => (int)$c->attribute( 'assign_self' ),
                     'shared' => (int)$c->attribute( 'owner_id' ) === 0 ? 1 : 0 );
}
$set = $settings->statusSet();
$cats = $settings->categories();
$prios = $settings->priorities();
$rows = array();
foreach ( $service->cannedReplies() as $c )
    $rows[] = array( 'id' => (int)$c->attribute( 'id' ), 'title' => (string)$c->attribute( 'title' ), 'shared' => !(int)$c->attribute( 'owner_id' ),
                     'category' => isset( $cats[$c->attribute( 'category' )] ) ? $cats[$c->attribute( 'category' )] : '',
                     'status' => (string)$c->attribute( 'set_status' ) !== '' ? $set->label( $c->attribute( 'set_status' ) ) : '',
                     'priority' => isset( $prios[$c->attribute( 'set_priority' )] ) ? $prios[$c->attribute( 'set_priority' )] : '',
                     'assign_self' => (bool)$c->attribute( 'assign_self' ), 'uses' => (int)$c->attribute( 'use_count' ),
                     'modified' => (int)$c->attribute( 'modified' ) );
$statusChoices = array();
foreach ( $set->staffChoices as $s )
    $statusChoices[$s] = $set->label( $s );
$Result = ticketsystemView::render( $user, 'canned', 'canned', array(
    'canned' => $rows, 'edit' => $edit === 'new' || ctype_digit( $edit ), 'values' => $values, 'ts_errors' => $errors, 'field_errors' => $fieldErrors,
    'categories' => $cats, 'priorities' => $prios, 'status_choices' => $statusChoices, 'is_staff' => true,
    'placeholders' => array( 'number', 'subject', 'customer', 'agent', 'status', 'queue', 'priority', 'link' ),
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Canned replies' ) ) );
