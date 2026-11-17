<?php
/**
 * ticketsystem/tickets: the original list view of ticketsystem 1.x, the folder [TicketSettings] TicketNodeID with
 * its tickets (design:ticketsystem/ticketsystem.tpl). Without that folder, and always in the administration (where the
 * staff desk replaces it), the reader goes to ticketsystem/list.
 *
 * @copyright Copyright (C) 2006 - 2007 xrow GbR, Hannover (Germany); Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$nodeId = (int)ticketsystemSettings::instance()->value( 'TicketSettings', 'TicketNodeID', 0 );
$node = $nodeId > 0 ? eZContentObjectTreeNode::fetch( $nodeId ) : null;
// in the administration the staff desk (ticketsystem/list) replaces the 1.x list
if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() || ticketsystemChrome::isAdmin() )
    return $Module->redirectTo( '/ticketsystem/list' );

$tpl = eZTemplate::factory();
$tpl->setVariable( 'TicketNodeID', $nodeId );
$tpl->setVariable( 'node', $node );
$Result = array();
$Result['content'] = $tpl->fetch( 'design:ticketsystem/ticketsystem.tpl' );
$Result['path'] = array( array( 'url' => false, 'text' => 'Ticket System' ) );
