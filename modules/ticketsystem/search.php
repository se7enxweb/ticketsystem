<?php
/**
 * ticketsystem/search: the staff search (doc/reports.md), policy ticketsystem/report, within the read scope. A ticket
 * number, words of the subject or of any post; staff also find the internal notes (marked). The form posts here and
 * is redirected to (q)/<words>: view parameters, never GET parameters.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'report', '/ticketsystem/search' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' )
{
    $q = ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) ? trim( mb_substr( (string)$http->postVariable( 'SearchText', '' ), 0, 100 ) ) : '';
    return $Module->redirectTo( '/ticketsystem/search' . ( $q !== '' ? '/(q)/' . rawurlencode( $q ) : '' ) );
}
$params = isset( $Params['UserParameters'] ) ? (array)$Params['UserParameters'] : array();
$q = isset( $params['q'] ) && is_string( $params['q'] ) ? trim( mb_substr( rawurldecode( $params['q'] ), 0, 100 ) ) : '';
$offset = isset( $params['offset'] ) ? max( 0, (int)$params['offset'] ) : 0;
$limit = max( 10, $settings->limit( 'SearchLimit', 50 ) );
$found = ( new ticketsystemReport( $access, $settings ) )->search( $q, $offset, $limit );
$names = array();
$rows = array();
foreach ( $found['tickets'] as $t )
    $rows[] = ticketsystemView::deskRow( $t, $names, $settings ) + array( 'in_note' => isset( $found['in_notes'][(int)$t->attribute( 'id' )] ) );
$base = '/ticketsystem/search/(q)/' . rawurlencode( $q );
$Result = ticketsystemView::render( $user, 'search', 'search', array(
    'q' => $q, 'tickets' => $rows, 'total' => $found['total'], 'is_staff' => $access->isStaff(),
    'pager_prev' => $offset > 0 ? $base . '/(offset)/' . max( 0, $offset - $limit ) : '',
    'pager_next' => $offset + $limit < $found['total'] ? $base . '/(offset)/' . ( $offset + $limit ) : '',
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Search' ) ) );
