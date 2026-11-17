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
$ViewList['list'] = array(
    'script' => 'list.php',
    'functions' => array( 'read' ),
    'params' => array(),
    'unordered_params' => array( 'status' => 'Status', 'offset' => 'Offset' ) );
$ViewList['new'] = array(
    'script' => 'new.php',
    'functions' => array( 'create' ),
    'params' => array() );
$ViewList['view'] = array(
    'script' => 'view.php',
    'functions' => array( 'read' ),
    'params' => array( 'Number' ) );
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
// the functions of ticketsystem 1.x, kept for the roles that use them
$FunctionList['use'] = array();
$FunctionList['deligate'] = array();
