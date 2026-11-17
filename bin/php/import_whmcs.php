#!/usr/bin/env php
<?php
/**
 * Imports WHMCS support tickets as closed history (doc/import.md). A dry run by default: it counts what it would
 * import and changes nothing; --apply imports. Idempotent: a second run imports only what is new. Ends with PASS or
 * FAIL and prints counts only.
 *
 *   php extension/ticketsystem/bin/php/import_whmcs.php -s <admin siteaccess> --file=<tickets.json>
 *   php extension/ticketsystem/bin/php/import_whmcs.php -s <admin siteaccess> --file=<tickets.json> --apply
 *       [--client-map=<csv of whmcs client id,user content object id>]
 *
 * The JSON comes from bin/php/whmcs_export.php, run on the WHMCS host.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => 'ticketsystem: import WHMCS tickets as closed history',
                                     'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions( '[file:][apply][client-map:][admin-user:]', '',
    array( 'file' => 'The JSON of whmcs_export.php', 'apply' => 'Import (without it: a dry run)',
           'client-map' => 'A CSV of "WHMCS client id,user id" lines, used before the Katalyst link',
           'admin-user' => 'Login of the user the import runs as (default: admin)' ) );
$script->initialize();

$export = $options['file'] ? json_decode( (string)@file_get_contents( (string)$options['file'] ), true ) : null;
if ( !is_array( $export ) || !isset( $export['tickets'] ) )
{
    $cli->output( 'FAIL no export with tickets (--file); counts-only exports cannot be imported' );
    $script->shutdown( 1 );
}
$map = array();
if ( $options['client-map'] )
    foreach ( (array)@file( (string)$options['client-map'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line )
        if ( preg_match( '/^\s*(\d+)\s*[,;]\s*(\d+)\s*$/', $line, $m ) )
            $map[(int)$m[1]] = (int)$m[2];

$admin = eZUser::fetchByName( $options['admin-user'] ? $options['admin-user'] : 'admin' );
if ( $admin )
    eZUser::setCurrentlyLoggedInUser( $admin, (int)$admin->attribute( 'contentobject_id' ) );

$import = new ticketsystemWhmcsImport( !$options['apply'], $map );
$counts = $import->run( $export );
$cli->output( ( $options['apply'] ? 'IMPORT' : 'DRY RUN' ) . ' of ' . $counts['tickets'] . ' WHMCS ticket(s):' );
foreach ( $counts as $k => $v )
    $cli->output( sprintf( '  %-20s %d', $k, $v ) );
$cli->output( $counts['failed'] ? 'FAIL' : ( $options['apply'] ? 'PASS' : 'PASS (dry run: nothing was changed)' ) );
$script->shutdown( $counts['failed'] ? 1 : 0 );
