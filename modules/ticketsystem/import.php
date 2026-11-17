<?php
/**
 * ticketsystem/import (staff, policy manage): the import of WHMCS tickets (doc/import-whmcs.md). Shows the export
 * file ([ImportSettings] ExportFile) with its counts, what a run would do (a dry run, every time the page is shown)
 * and what was imported so far. POST TicketImportUpload with the file ImportFile stores an export of
 * bin/php/whmcs_export.php; POST TicketImportRun runs the import. Both need the form token. Counts only: the page
 * never shows a ticket, a name or an address. design:ticketsystem/import.tpl.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'manage', '/ticketsystem/import' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$settings = ticketsystemSettings::instance();
$file = ticketsystemWhmcsImport::exportFile( $settings );
$messages = array();
$errors = array();
$result = null;

if ( $http->hasPostVariable( 'TicketImportUpload' ) || $http->hasPostVariable( 'TicketImportRun' ) )
{
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
        $errors[] = 'The form had expired. Nothing was done; please try again.';
    else if ( $http->hasPostVariable( 'TicketImportUpload' ) )
    {
        $upload = isset( $_FILES['ImportFile'] ) ? $_FILES['ImportFile'] : null;
        if ( !$upload || (int)$upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file( $upload['tmp_name'] ) )
            $errors[] = 'Please choose the export file (whmcs-tickets.json).';
        else
        {
            $error = ticketsystemWhmcsImport::storeExport( (string)file_get_contents( $upload['tmp_name'] ), $settings );
            if ( $error !== '' )
                $errors[] = $error;
            else
            {
                ticketsystemView::flash( array( 'The export was stored. Check the dry run below, then run the import.' ) );
                return $Module->redirectTo( '/ticketsystem/import' );
            }
        }
    }
    else
    {
        list( $export, $error ) = ticketsystemWhmcsImport::readExport( $file );
        if ( !$export )
            $errors[] = $error;
        else
        {
            $result = ( new ticketsystemWhmcsImport( false, array(), $settings ) )->run( $export );
            $messages[] = $result['failed'] ? 'The import ran with errors; see the counts below and the debug log.' : 'The import ran.';
        }
    }
}

list( $export, $exportError ) = ticketsystemWhmcsImport::readExport( $file );
$dry = $export ? ( new ticketsystemWhmcsImport( true, array(), $settings ) )->run( $export ) : null;
$labels = array( 'tickets' => 'Tickets in the export', 'new_tickets' => 'New tickets', 'already' => 'Already imported',
                 'skipped_guest' => 'Skipped: guest tickets (no client)', 'skipped_no_customer' => 'Skipped: client without a customer here',
                 'skipped_no_user' => 'Skipped: customer without a user', 'new_replies' => 'New replies', 'new_notes' => 'New internal notes',
                 'notes_waiting' => 'Notes waiting for the internal notes', 'files_not_copied' => 'Files not copied (kept in WHMCS)',
                 'failed' => 'Failed' );

return ticketsystemView::render( $user, 'import', 'import', array(
    'export_present' => (bool)$export,
    'export_error' => $export ? '' : $exportError,
    'export_date' => $export && isset( $export['exported'] ) ? (string)$export['exported'] : '',
    'export_counts' => $export && isset( $export['counts'] ) && is_array( $export['counts'] ) ? $export['counts'] : array(),
    'dry' => $dry ? $dry : array(),
    'result' => $result ? $result : array(),
    'labels' => $labels,
    'imported' => ticketsystemWhmcsImport::imported(),
    'ts_messages' => $messages,
    'ts_errors' => $errors,
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'WHMCS import' ) ) );
