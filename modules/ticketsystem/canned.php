<?php
/**
 * ticketsystem/canned: the canned replies and macros of the staff (doc/canned-replies.md).
 *
 * Policy ticketsystem/canned (limitation CannedScope: Personal = one's own, Shared = the team's) to create, change and
 * delete them; every staff member (ticketsystem/manage) sees and uses the shared ones and their own. The list, with
 * (queue)/<category> to show one queue's; (edit)/new or (edit)/<id> for the form.
 *
 * POST with the form token, then post/redirect/get: CannedSave (CannedID, CannedTitle, CannedBody, CannedCategory,
 * CannedStatus, CannedPriority, CannedAssignSelf, CannedShared), CannedDelete (CannedID), CannedFilter (CannedQueue).
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'canned', '/ticketsystem/canned' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
if ( !$access->isStaff() )
    return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
$settings = ticketsystemSettings::instance();
$service = new ticketsystemService( $access, $settings );
$scopes = $access->cannedScopes();
$params = isset( $Params['UserParameters'] ) ? (array)$Params['UserParameters'] : array();
$edit = isset( $params['edit'] ) ? (string)$params['edit'] : '';
$cats = $settings->categories();
$queue = isset( $params['queue'] ) && is_string( $params['queue'] ) && isset( $cats[$params['queue']] ) ? $params['queue'] : '';
$errors = array();
$fieldErrors = array();
$values = array( 'id' => 0, 'title' => '', 'body' => '', 'category' => $queue, 'set_status' => '', 'set_priority' => '', 'assign_self' => 0,
                 'shared' => $scopes['personal'] ? 0 : 1 );

if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' )
{
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
        $errors[] = 'The form had expired. Nothing was saved; please send it again.';
    else if ( $http->hasPostVariable( 'CannedFilter' ) )
    {
        $q = (string)$http->postVariable( 'CannedQueue', '' );
        return $Module->redirectTo( '/ticketsystem/canned' . ( isset( $cats[$q] ) ? '/(queue)/' . $q : '' ) );
    }
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
                         'assign_self' => $http->hasPostVariable( 'CannedAssignSelf' ) ? 1 : 0,
                         // without the choice (only one scope allowed) the one allowed scope
                         'shared' => $scopes['personal'] && $scopes['shared'] ? ( $http->hasPostVariable( 'CannedShared' ) ? 1 : 0 ) : ( $scopes['shared'] ? 1 : 0 ) );
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
    if ( !$c || !ticketsystemService::mayKeepCanned( $scopes, $access->userId(), (int)$c->attribute( 'owner_id' ) ) )
        return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
    $values = array( 'id' => (int)$c->attribute( 'id' ), 'title' => (string)$c->attribute( 'title' ), 'body' => (string)$c->attribute( 'body' ),
                     'category' => (string)$c->attribute( 'category' ), 'set_status' => (string)$c->attribute( 'set_status' ),
                     'set_priority' => (string)$c->attribute( 'set_priority' ), 'assign_self' => (int)$c->attribute( 'assign_self' ),
                     'shared' => (int)$c->attribute( 'owner_id' ) === 0 ? 1 : 0 );
}
$set = $settings->statusSet();
$prios = $settings->priorities();
$rows = array();
foreach ( $service->cannedReplies( $queue ) as $c )
{
    if ( $queue !== '' && (string)$c->attribute( 'category' ) !== $queue )
        continue;
    $rows[] = array( 'id' => (int)$c->attribute( 'id' ), 'title' => (string)$c->attribute( 'title' ), 'shared' => !(int)$c->attribute( 'owner_id' ),
                     'category' => isset( $cats[$c->attribute( 'category' )] ) ? $cats[$c->attribute( 'category' )] : '',
                     'status' => (string)$c->attribute( 'set_status' ) !== '' ? $set->label( $c->attribute( 'set_status' ) ) : '',
                     'priority' => isset( $prios[$c->attribute( 'set_priority' )] ) ? $prios[$c->attribute( 'set_priority' )] : '',
                     'assign_self' => (bool)$c->attribute( 'assign_self' ), 'uses' => (int)$c->attribute( 'use_count' ),
                     'modified' => (int)$c->attribute( 'modified' ),
                     'may_edit' => ticketsystemService::mayKeepCanned( $scopes, $access->userId(), (int)$c->attribute( 'owner_id' ) ) );
}
$statusChoices = array();
foreach ( $set->staffChoices as $s )
    $statusChoices[$s] = $set->label( $s );
$placeholders = array();
foreach ( ticketsystemService::CANNED_PLACEHOLDERS as $name => $what )
    $placeholders[] = array( 'name' => $name, 'what' => $what );
$Result = ticketsystemView::render( $user, 'canned', 'canned', array(
    'canned' => $rows, 'edit' => $edit === 'new' || ctype_digit( $edit ), 'values' => $values, 'ts_errors' => $errors, 'field_errors' => $fieldErrors,
    'queue' => $queue, 'categories' => $cats, 'priorities' => $prios, 'status_choices' => $statusChoices, 'is_staff' => true,
    'may_personal' => $scopes['personal'], 'may_shared' => $scopes['shared'],
    'macros' => $settings->enabled( 'CannedSettings', 'Macros', 'enabled' ),
    'placeholders' => array_keys( ticketsystemService::CANNED_PLACEHOLDERS ), 'placeholder_help' => $placeholders,
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Canned replies' ) ) );
