<?php
/**
 * The export side of the WHMCS import (doc/import.md). Runs on the WHMCS host with WHMCS's own bootstrap (Capsule),
 * read only, and writes the support tickets as JSON for bin/php/import_whmcs.php. Not a script of Exponential: copy it
 * next to WHMCS and run it as the WHMCS site user.
 *
 *   php whmcs_export.php <whmcs root> --counts            counts only (no client data at all)
 *   php whmcs_export.php <whmcs root> > tickets.json       every ticket with its replies and notes
 *
 * The JSON carries for each ticket: the WHMCS ids (id, tid, the client id userid, the department), the date, the
 * subject, the message, the status, the urgency, the last reply, and its replies (client or administrator, date,
 * message) and notes. Never e-mail addresses, names or IPs: the import finds the customer by the WHMCS client id
 * only. Keep the file private (0600) and remove it after the import.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */
$root = isset( $argv[1] ) && $argv[1][0] !== '-' ? $argv[1] : getcwd();
$countsOnly = in_array( '--counts', $argv, true );
chdir( $root );
require $root . '/init.php';
use WHMCS\Database\Capsule;

$departments = array();
foreach ( Capsule::table( 'tblticketdepartments' )->get( array( 'id', 'name' ) ) as $d )
    $departments[(int)$d->id] = (string)$d->name;
$tickets = Capsule::table( 'tbltickets' )->orderBy( 'id' )->get( array( 'id', 'tid', 'did', 'userid', 'date', 'title', 'message', 'status',
                                                                         'urgency', 'lastreply', 'merged_ticket_id' ) );
$out = array( 'source' => 'whmcs', 'exported' => date( 'c' ), 'counts' => array( 'tickets' => 0, 'guest_tickets' => 0, 'merged' => 0,
                                                                                  'replies' => 0, 'staff_replies' => 0, 'notes' => 0 ),
              'departments' => $departments, 'tickets' => array() );
foreach ( $tickets as $t )
{
    $out['counts']['tickets']++;
    if ( !(int)$t->userid )
        $out['counts']['guest_tickets']++;
    if ( (int)$t->merged_ticket_id )
        $out['counts']['merged']++;
    $replies = Capsule::table( 'tblticketreplies' )->where( 'tid', (int)$t->id )->orderBy( 'id' )->get( array( 'id', 'userid', 'admin', 'date', 'message' ) );
    $notes = Capsule::table( 'tblticketnotes' )->where( 'ticketid', (int)$t->id )->orderBy( 'id' )->get( array( 'id', 'date', 'message' ) );
    $out['counts']['replies'] += count( $replies );
    $out['counts']['notes'] += count( $notes );
    foreach ( $replies as $r )
        if ( trim( (string)$r->admin ) !== '' )
            $out['counts']['staff_replies']++;
    if ( $countsOnly )
        continue;
    $row = array( 'id' => (int)$t->id, 'tid' => (string)$t->tid, 'department' => (int)$t->did, 'userid' => (int)$t->userid,
                  'date' => (string)$t->date, 'subject' => (string)$t->title, 'message' => (string)$t->message, 'status' => (string)$t->status,
                  'urgency' => (string)$t->urgency, 'lastreply' => (string)$t->lastreply, 'merged_into' => (int)$t->merged_ticket_id,
                  'replies' => array(), 'notes' => array() );
    foreach ( $replies as $r )
        $row['replies'][] = array( 'id' => (int)$r->id, 'userid' => (int)$r->userid, 'staff' => trim( (string)$r->admin ) !== '',
                                   'date' => (string)$r->date, 'message' => (string)$r->message );
    foreach ( $notes as $n )
        $row['notes'][] = array( 'id' => (int)$n->id, 'date' => (string)$n->date, 'message' => (string)$n->message );
    $out['tickets'][] = $row;
}
if ( $countsOnly )
    unset( $out['tickets'], $out['departments'] );
echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
