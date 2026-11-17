#!/usr/bin/env php
<?php
/**
 * Imports WHMCS support tickets as history (doc/import-whmcs.md). A dry run by default: it counts what it would
 * import and changes nothing; --execute imports. Idempotent: a second run imports only what is new. Sends no mail
 * and no notification. Prints counts only, never a name, an address or a text; ends with PASS or FAIL.
 *
 *   php extension/ticketsystem/bin/php/import_whmcs.php -s <admin siteaccess> [--file=<whmcs-tickets.json>] [--dry-run]
 *   php extension/ticketsystem/bin/php/import_whmcs.php -s <admin siteaccess> [--file=<whmcs-tickets.json>] --execute
 *       [--client-map=<csv of "WHMCS client id,user content object id">] [--admin-user=<login>]
 *
 * Without --file the export file of [ImportSettings] ExportFile is read. The JSON comes from bin/php/whmcs_export.php,
 * run read only on the WHMCS host.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => 'ticketsystem: import WHMCS tickets as history (a dry run unless --execute)',
                                     'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions( '[file:][dry-run][execute][client-map:][admin-user:]', '',
    array( 'file' => 'The JSON of whmcs_export.php (default: [ImportSettings] ExportFile)',
           'dry-run' => 'Count only, change nothing (the default)',
           'execute' => 'Import',
           'client-map' => 'A CSV of "WHMCS client id,user id" lines, used before the Katalyst link',
           'admin-user' => 'Login of the user the import runs as (default: admin)' ) );
$script->initialize();

if ( $options['execute'] && $options['dry-run'] )
{
    $cli->output( 'FAIL give either --dry-run or --execute' );
    $script->shutdown( 1 );
}
$file = $options['file'] ? (string)$options['file'] : ticketsystemWhmcsImport::exportFile();
list( $export, $error ) = ticketsystemWhmcsImport::readExport( $file );
if ( !$export )
{
    $cli->output( 'FAIL ' . $error );
    $script->shutdown( 1 );
}
$map = array();
if ( $options['client-map'] )
    foreach ( (array)@file( (string)$options['client-map'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line )
        if ( preg_match( '/^\s*(\d+)\s*[,;]\s*(\d+)\s*$/', $line, $m ) )
            $map[(int)$m[1]] = (int)$m[2];

$admin = eZUser::fetchByName( $options['admin-user'] ? $options['admin-user'] : 'admin' );
if ( !$admin )
{
    $cli->output( 'FAIL no user to run as (--admin-user)' );
    $script->shutdown( 1 );
}
eZUser::setCurrentlyLoggedInUser( $admin, (int)$admin->attribute( 'contentobject_id' ) );

$execute = (bool)$options['execute'];
$import = new ticketsystemWhmcsImport( !$execute, $map );
$counts = $import->run( $export );
$cli->output( ( $execute ? 'IMPORT' : 'DRY RUN' ) . ' of the WHMCS export (' . count( $export['tickets'] ) . ' ticket(s)):' );
foreach ( $counts as $k => $v )
    $cli->output( sprintf( '  %-20s %d', $k, $v ) );
$cli->output( 'imported so far: ' . http_build_query( ticketsystemImportMap::counts( ticketsystemWhmcsImport::SOURCE ), '', ', ' ) );
$cli->output( $counts['failed'] ? 'FAIL' : ( $execute ? 'PASS' : 'PASS (dry run: nothing was changed; --execute imports)' ) );
$script->shutdown( $counts['failed'] ? 1 : 0 );
