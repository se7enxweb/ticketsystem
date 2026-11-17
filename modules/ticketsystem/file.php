<?php
/**
 * ticketsystem/file/<id>: one file of a ticket (doc/attachments.md), only to a user who may read the ticket; the file
 * of an internal note only to staff. Anything else answers "not found". Always a download: Content-Disposition
 * attachment, the type found when it was stored, nosniff, no caching by shared caches.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$id = isset( $Params['AttachmentID'] ) ? (int)$Params['AttachmentID'] : 0;
if ( ( $denied = ticketsystemView::guard( $Module, 'read', '/ticketsystem/file/' . $id ) ) !== null )
    return $denied;
$service = new ticketsystemService( new ticketsystemAccess( eZUser::currentUser() ) );
$a = $id > 0 ? $service->attachment( $id ) : null;
$files = new ticketsystemFiles();
$path = $a ? $files->path( $a ) : '';
if ( !$a || $path === '' )
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );

$name = (string)$a->attribute( 'name' );
$ascii = preg_replace( '/[^A-Za-z0-9._-]+/', '_', $name );
while ( ob_get_level() > 0 )
    ob_end_clean();
header( 'Content-Type: ' . ( preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', (string)$a->attribute( 'mime' ) ) ? (string)$a->attribute( 'mime' ) : 'application/octet-stream' ) );
header( 'Content-Length: ' . filesize( $path ) );
header( "Content-Disposition: attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode( $name ) );
header( 'X-Content-Type-Options: nosniff' );
header( 'Cache-Control: private, no-store' );
header( "Content-Security-Policy: default-src 'none'; sandbox" );
readfile( $path );
eZExecution::cleanExit();
