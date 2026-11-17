<?php
/**
 * The workflow event "Automatic ticket notification" (doc/workflow.md), for content/publish after.
 *
 * When a ticket or a post is published outside the help desk views (in the administration, by an import):
 * - the publisher is subscribed to the ticket node, as ticketsystem 1.x did;
 * - the ticket gets its index row and readable number when it has none, and the index follows the subject and the
 *   status (the attribute "state") of the published version.
 * Publications made by ticketsystemService are left alone (it does all of this itself).
 *
 * @copyright Copyright (C) 2006 - 2007 xrow GbR, Hannover (Germany); Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

class ticketNotificationType extends eZWorkflowEventType
{
    const WORKFLOW_TYPE_STRING = 'ticketnotification';

    public function __construct()
    {
        parent::__construct( self::WORKFLOW_TYPE_STRING, ezpI18n::tr( 'extension/ticketsystem', 'Automatic ticket notification' ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    public function execute( $process, $event )
    {
        $parameters = $process->attribute( 'parameter_list' );
        $object = isset( $parameters['object_id'] ) ? eZContentObject::fetch( (int)$parameters['object_id'] ) : null;
        if ( !$object instanceof eZContentObject || ticketsystemService::$publishing )
            return eZWorkflowType::STATUS_ACCEPTED;
        $settings = ticketsystemSettings::instance();
        $class = (string)$object->attribute( 'class_identifier' );
        if ( $class !== $settings->ticketClass() && $class !== $settings->postClass() )
            return eZWorkflowType::STATUS_ACCEPTED;
        $userId = (int)eZUser::currentUserID();
        if ( $class === $settings->ticketClass() )
        {
            $ticket = ticketsystemService::indexObject( $object, $settings );
            $nodeId = (int)$object->attribute( 'main_node_id' );
        }
        else
        {
            $node = $object->attribute( 'main_node' );
            $nodeId = $node ? (int)$node->attribute( 'parent_node_id' ) : 0;
            $parent = $nodeId ? eZContentObjectTreeNode::fetch( $nodeId ) : null;
            $ticket = $parent ? ticketsystemTicket::fetchByObjectID( (int)$parent->attribute( 'contentobject_id' ) ) : null;
            if ( $ticket )
            {
                $ticket->setAttribute( 'post_count', (int)$ticket->attribute( 'post_count' ) + 1 );
                $ticket->setAttribute( 'last_post', time() );
                $ticket->setAttribute( 'last_post_by', $userId );
                $ticket->setAttribute( 'modified', time() );
                $ticket->store();
            }
        }
        if ( $nodeId > 0 && ticketsystemService::subscribe( $nodeId, $userId ) )
            eZDebug::writeNotice( 'Subscribed the publisher to ticket node ' . $nodeId, __METHOD__ );
        return eZWorkflowType::STATUS_ACCEPTED;
    }
}

eZWorkflowEventType::registerEventType( ticketNotificationType::WORKFLOW_TYPE_STRING, 'ticketNotificationType' );
