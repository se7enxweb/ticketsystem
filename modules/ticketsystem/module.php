<?php
/**
 * The module ticketsystem (doc/views.md, doc/permissions.md).
 *
 * @copyright Copyright (C) 2006 - 2007 xrow GbR, Hannover (Germany); Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

$Module = array( 'name' => 'ticketsystem' );

$ViewList = array();
// the help desk: the user's tickets, a new ticket, one ticket with its posts
// (the views check their policy themselves, ticketsystemView::guard(): a visitor who is not signed in goes to the
// sign-in page and comes back, instead of reading "Access denied")
// In the administration they belong to the tab TicketSystem (ezticketsystemnavigationpart, settings/menu.ini).
// Filters are view parameters, never GET parameters (ticketsystemDesk::filter()).
$ViewList['list'] = array(
    'script' => 'list.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array(),
    'unordered_params' => array( 'status' => 'status', 'offset' => 'offset', 'q' => 'q', 'priority' => 'priority', 'category' => 'category',
                                 'reply' => 'reply', 'assignee' => 'assignee', 'sla' => 'sla', 'account' => 'account',
                                 'subscription' => 'subscription', 'sort' => 'sort', 'order' => 'order' ) );
$ViewList['new'] = array(
    'script' => 'new.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array(),
    // 1.2, staff: the customer a ticket is opened for (doc/staff-opened-tickets.md); (subscription)/<id>: the site the
    // new ticket is about, preselected (one of the account's own; anything else is ignored)
    'unordered_params' => array( 'user' => 'user', 'customer' => 'customer', 'subscription' => 'subscription' ) );
$ViewList['view'] = array(
    'script' => 'view.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array( 'Number' ) );
// 1.1: a file of a ticket (readers of the ticket; a file of an internal note: staff only)
$ViewList['file'] = array(
    'script' => 'file.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array( 'AttachmentID' ) );
// 1.1: the staff pages (ticketsystem/manage; reports and search: ticketsystem/report)
$ViewList['search'] = array(
    'script' => 'search.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array(),
    'unordered_params' => array( 'q' => 'q', 'number' => 'number', 'status' => 'status', 'priority' => 'priority', 'queue' => 'queue', 'assignee' => 'assignee', 'customer' => 'customer', 'account' => 'account', 'from' => 'from', 'to' => 'to', 'offset' => 'offset' ) );
$ViewList['reports'] = array(
    'script' => 'reports.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array(),
    'unordered_params' => array( 'from' => 'from', 'to' => 'to', 'queue' => 'queue', 'period' => 'period', 'days' => 'days', 'format' => 'format' ) );
$ViewList['canned'] = array(
    'script' => 'canned.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array(),
    'unordered_params' => array( 'edit' => 'edit' ) );
// 1.2: time tracking report and CSV (ticketsystem/report; doc/time-tracking.md)
$ViewList['time'] = array(
    'script' => 'time.php',
    'functions' => array( 'report' ),
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array(),
    'unordered_params' => array( 'from' => 'from', 'to' => 'to', 'account' => 'account', 'user' => 'user', 'billable' => 'billable', 'billed' => 'billed', 'format' => 'format' ) );
$ViewList['settings'] = array(
    'script' => 'settings.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array( 'Section' ) );
// 1.1: the SLA board (ticketsystem/manage), the satisfaction ratings (ticketsystem/report) and the rating link of the
// mail "How did we do?" (no sign-in: a signed token that works once; PolicyOmitList)
$ViewList['sla'] = array(
    'script' => 'sla.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array(),
    'unordered_params' => array( 'state' => 'state' ) );
$ViewList['satisfaction'] = array(
    'script' => 'satisfaction.php',
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array(),
    'unordered_params' => array( 'days' => 'days' ) );
$ViewList['rate'] = array(
    'script' => 'rate.php',
    'default_navigation_part' => 'ezmynavigationpart',
    'params' => array( 'Token', 'Score' ) );
// staff (manage): the WHMCS import (doc/import-whmcs.md) and the reply-by-e-mail log (doc/mail-in.md)
$ViewList['import'] = array(
    'script' => 'import.php',
    'functions' => array( 'manage' ),
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array() );
$ViewList['mailin'] = array(
    'script' => 'mailin.php',
    'functions' => array( 'manage' ),
    'default_navigation_part' => 'ezticketsystemnavigationpart',
    'params' => array() );
// the original list of ticketsystem 1.x (the folder [TicketSettings] TicketNodeID)
$ViewList['tickets'] = array(
    'script' => 'tickets.php',
    'functions' => array( 'use' ),
    'params' => array() );

// 1 Own: the tickets the user opened; 2 Account: every ticket of the user's account; 3 All: support staff
$Scope = array(
    'name' => 'Scope',
    'values' => array(
        array( 'Name' => 'Own', 'value' => '1' ),
        array( 'Name' => 'Account', 'value' => '2' ),
        array( 'Name' => 'All', 'value' => '3' ) ) );

$FunctionList = array();
$FunctionList['read'] = array( 'Scope' => $Scope );
$FunctionList['reply'] = array( 'Scope' => $Scope );
$FunctionList['create'] = array();
$FunctionList['manage'] = array();
// 1.1: reports, the CSV export and the staff search (within the read scope)
$FunctionList['report'] = array();
// 1.1: keep canned replies (create, edit, delete): 1 Personal (one's own), 2 Shared (the team's); using them needs manage
$FunctionList['canned'] = array( 'CannedScope' => array(
    'name' => 'CannedScope',
    'values' => array(
        array( 'Name' => 'Personal', 'value' => '1' ),
        array( 'Name' => 'Shared', 'value' => '2' ) ) ) );
// the functions of ticketsystem 1.x, kept for the roles that use them
$FunctionList['use'] = array();
$FunctionList['deligate'] = array();
