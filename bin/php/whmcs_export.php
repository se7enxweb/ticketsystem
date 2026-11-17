#!/usr/bin/env php
<?php
/**
 * The export side of the WHMCS import (doc/import-whmcs.md). Runs on the WHMCS host with WHMCS's own bootstrap
 * (Capsule), READ ONLY, and prints the support tickets as JSON for bin/php/import_whmcs.php. It is not a script of
 * Exponential: copy it next to WHMCS and run it as the WHMCS site user with WHMCS's PHP.
 *
 *   php whmcs_export.php <whmcs root> --counts             counts only (no ticket content, no client data at all;
 *                                                         also with the environment variable TS_COUNTS=1)
 *   php whmcs_export.php <whmcs root> > whmcs-tickets.json  every ticket with its replies and notes
 *
 * For each ticket: the WHMCS ids (id, tid, the client id userid, the department id), the dates, subject, message,
 * status, urgency, the merge target, the number of attached files; its replies (client or staff with the WHMCS
 * admin id, date, message, number of files) and its notes. Never e-mail addresses, client names or IP addresses:
 * the import finds the customer by the WHMCS client id only. Keep the file private (0600) and remove it after the
 * import.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */
$root = isset( $argv[1] ) && $argv[1] !== '' && $argv[1][0] !== '-' ? $argv[1] : getcwd();
$countsOnly = in_array( '--counts', $argv, true ) || getenv( 'TS_COUNTS' ) === '1';
chdir( $root );
require $root . '/init.php';
use WHMCS\Database\Capsule;

/** The number of files in a WHMCS attachment field ("name1|name2"). */
function ts_files( $field )
{
    $field = trim( (string)$field );
    return $field === '' ? 0 : count( array_filter( explode( '|', $field ), 'strlen' ) );
}

// staff: WHMCS keeps the name of the administrator in replies and notes; the export carries the admin id instead
$admins = array();
foreach ( Capsule::table( 'tbladmins' )->get( array( 'id', 'firstname', 'lastname', 'username' ) ) as $a )
{
    $admins[strtolower( trim( $a->firstname . ' ' . $a->lastname ) )] = (int)$a->id;
    $admins[strtolower( trim( (string)$a->username ) )] = (int)$a->id;
}
$adminId = function ( $name ) use ( $admins ) {
    $k = strtolower( trim( (string)$name ) );
    return $k === '' ? 0 : ( isset( $admins[$k] ) ? $admins[$k] : -1 );
};

$departments = array();
foreach ( Capsule::table( 'tblticketdepartments' )->orderBy( 'id' )->get( array( 'id', 'name' ) ) as $d )
    $departments[(string)(int)$d->id] = (string)$d->name;

$counts = array( 'tickets' => 0, 'guest_tickets' => 0, 'merged' => 0, 'replies' => 0, 'staff_replies' => 0, 'notes' => 0, 'files' => 0,
                 'clients' => 0, 'departments' => count( $departments ) );
$clients = array();
$out = array( 'source' => 'whmcs', 'format' => 1, 'exported' => date( 'c' ), 'counts' => null, 'departments' => $departments, 'tickets' => array() );
foreach ( Capsule::table( 'tbltickets' )->orderBy( 'id' )->get( array( 'id', 'tid', 'did', 'userid', 'date', 'title', 'message', 'status',
                                                                         'urgency', 'admin', 'attachment', 'lastreply', 'merged_ticket_id' ) ) as $t )
{
    $counts['tickets']++;
    if ( (int)$t->userid > 0 )
        $clients[(int)$t->userid] = true;
    else
        $counts['guest_tickets']++;
    if ( (int)$t->merged_ticket_id )
        $counts['merged']++;
    $replies = Capsule::table( 'tblticketreplies' )->where( 'tid', (int)$t->id )->orderBy( 'id' )
                      ->get( array( 'id', 'userid', 'admin', 'date', 'message', 'attachment' ) );
    $notes = Capsule::table( 'tblticketnotes' )->where( 'ticketid', (int)$t->id )->orderBy( 'id' )
                    ->get( array( 'id', 'admin', 'date', 'message', 'attachments' ) );
    $counts['replies'] += count( $replies );
    $counts['notes'] += count( $notes );
    $counts['files'] += ts_files( $t->attachment );
    $row = array( 'id' => (int)$t->id, 'tid' => (string)$t->tid, 'department' => (int)$t->did, 'userid' => (int)$t->userid,
                  'date' => (string)$t->date, 'subject' => (string)$t->title, 'message' => (string)$t->message,
                  'status' => (string)$t->status, 'urgency' => (string)$t->urgency, 'lastreply' => (string)$t->lastreply,
                  'merged_into' => (int)$t->merged_ticket_id, 'files' => ts_files( $t->attachment ),
                  'replies' => array(), 'notes' => array() );
    foreach ( $replies as $r )
    {
        $staff = trim( (string)$r->admin ) !== '';
        if ( $staff )
            $counts['staff_replies']++;
        $counts['files'] += ts_files( $r->attachment );
        $row['replies'][] = array( 'id' => (int)$r->id, 'staff' => $staff, 'admin_id' => $staff ? $adminId( $r->admin ) : 0,
                                   'date' => (string)$r->date, 'message' => (string)$r->message, 'files' => ts_files( $r->attachment ) );
    }
    foreach ( $notes as $n )
    {
        $counts['files'] += ts_files( $n->attachments );
        $row['notes'][] = array( 'id' => (int)$n->id, 'admin_id' => $adminId( $n->admin ), 'date' => (string)$n->date,
                                 'message' => (string)$n->message, 'files' => ts_files( $n->attachments ) );
    }
    if ( !$countsOnly )
        $out['tickets'][] = $row;
}
$counts['clients'] = count( $clients );
$out['counts'] = $counts;
if ( $countsOnly )
    $out = array( 'source' => 'whmcs', 'format' => 1, 'exported' => $out['exported'], 'counts' => $counts );
echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ), "\n";
