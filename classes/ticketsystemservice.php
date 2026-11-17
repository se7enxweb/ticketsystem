<?php
/**
 * @copyright Copyright (C) 2006 - 2007 xrow GbR, Hannover (Germany); Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Opening, answering and changing tickets (doc/architecture.md).
 *
 * A ticket is a content object of the class "ticket" below the main node of its opener's user object; each reply
 * is a "ticket_comment" object below the ticket. Both are published with eZContentFunctions as the posting user,
 * without the kernel's notification (the posts are mailed by ticketsystemMailer to the ticket's followers, and the
 * kernel's subtree mails would reach subscribers of the Users tree who may not read the ticket). The index row in
 * ticketsystem_ticket carries the readable number, the account, the status and the times. The poster is subscribed
 * to the ticket node (Exponential notification subscriptions), so every later post reaches them.
 *
 * Every method checks access itself (ticketsystemAccess) and answers array( ok, message, ... ); messages are plain
 * English for the person who acted, never an address or an internal id.
 */
class ticketsystemService
{
    /** @var ticketsystemAccess */
    protected $access;
    /** @var ticketsystemSettings */
    protected $settings;
    /** @var ticketsystemMailer */
    protected $mailer;

    public function __construct( ticketsystemAccess $access, ?ticketsystemSettings $settings = null, ?ticketsystemMailer $mailer = null )
    {
        $this->access = $access;
        $this->settings = $settings ?: ticketsystemSettings::instance();
        $this->mailer = $mailer ?: new ticketsystemMailer( $this->settings );
    }

    /** True while this service publishes (the workflow event then leaves the publication alone). */
    public static $publishing = false;

    /** Publishes a new object without the kernel's notification. */
    protected static function publishNew( array $params )
    {
        self::$publishing = true;
        try
        {
            return eZContentFunctions::createAndPublishObject( $params, false );
        }
        finally
        {
            self::$publishing = false;
        }
    }

    /** Publishes a new version of an object without the kernel's notification. */
    protected static function publishUpdate( eZContentObject $object, array $params )
    {
        self::$publishing = true;
        try
        {
            return eZContentFunctions::updateAndPublishObject( $object, $params, false );
        }
        finally
        {
            self::$publishing = false;
        }
    }

    /**
     * The index row of a ticket object published elsewhere (the workflow event): created with a new number when
     * missing (the account of its owner alone), else brought to the object's subject and status.
     *
     * @return ticketsystemTicket|null
     */
    public static function indexObject( eZContentObject $object, ?ticketsystemSettings $settings = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        if ( !$object->attribute( 'main_node_id' ) )
            return null;
        $set = $settings->statusSet();
        $map = $object->dataMap();
        $status = '';
        if ( isset( $map['state'] ) && $map['state']->attribute( 'data_type_string' ) === 'ezselection' )
        {
            $content = $map['state']->attribute( 'class_content' );
            $ids = explode( '-', (string)$map['state']->attribute( 'data_text' ) );
            foreach ( isset( $content['options'] ) ? $content['options'] : array() as $o )
                if ( in_array( (string)$o['id'], $ids, true ) )
                    $status = (string)array_search( (string)$o['name'], $set->statuses, true );
        }
        $now = time();
        $t = ticketsystemTicket::fetchByObjectID( (int)$object->attribute( 'id' ) );
        $db = eZDB::instance();
        $db->begin();
        if ( !$t )
        {
            list( $number, $prefix, $year, $seq ) = ticketsystemNumber::take( $settings );
            $owner = (int)$object->attribute( 'owner_id' );
            $t = ticketsystemTicket::create( array(
                'contentobject_id' => (int)$object->attribute( 'id' ), 'node_id' => (int)$object->attribute( 'main_node_id' ),
                'number' => $number, 'prefix' => $prefix, 'year' => $year, 'seq' => $seq,
                'account_key' => ticketsystemAccount::userKey( $owner ), 'opener_id' => $owner,
                'subject' => '', 'status' => $set->role( 'Default' ), 'category' => '', 'priority' => '', 'subscription_id' => 0,
                'post_count' => 1, 'last_post' => $now, 'last_post_by' => $owner, 'last_post_staff' => 0,
                'created' => (int)$object->attribute( 'published' ), 'modified' => $now, 'resolved' => 0, 'closed' => 0 ) );
            if ( isset( $map['ticket_number'] ) && trim( (string)$map['ticket_number']->toString() ) === '' )
            {
                $map['ticket_number']->fromString( $number );
                $map['ticket_number']->store();
            }
        }
        $t->setAttribute( 'subject', mb_substr( (string)$object->attribute( 'name' ), 0, 255 ) );
        if ( $status !== '' && $status !== (string)$t->attribute( 'status' ) )
        {
            $t->setAttribute( 'status', $status );
            $t->setAttribute( 'resolved', $set->isDone( $status ) ? $now : 0 );
            $t->setAttribute( 'closed', $set->isClosed( $status ) ? $now : 0 );
        }
        $t->setAttribute( 'modified', $now );
        $t->store();
        $db->commit();
        return $t;
    }

    protected static function fail( $message, array $errors = array() )
    {
        return array( 'ok' => false, 'message' => $message, 'errors' => $errors ? $errors : array( $message ) );
    }

    /**
     * Checks the input of a new ticket (pure apart from the settings).
     *
     * @return array( errors (field => message), clean values )
     */
    public function validateNew( array $in )
    {
        $errors = array();
        $subject = trim( preg_replace( '/\s+/u', ' ', ticketsystemText::clean( isset( $in['subject'] ) ? $in['subject'] : '' ) ) );
        $body = ticketsystemText::clean( isset( $in['body'] ) ? $in['body'] : '' );
        $category = isset( $in['category'] ) ? (string)$in['category'] : '';
        $priority = isset( $in['priority'] ) ? (string)$in['priority'] : '';
        $maxSubject = $this->settings->limit( 'MaxSubjectLength', 200 );
        $maxBody = $this->settings->limit( 'MaxBodyLength', 50000 );
        if ( $subject === '' )
            $errors['subject'] = 'Please give the ticket a short subject.';
        else if ( mb_strlen( $subject ) > $maxSubject )
            $errors['subject'] = sprintf( 'The subject can be at most %d characters long.', $maxSubject );
        if ( $body === '' )
            $errors['body'] = 'Please describe what you need.';
        else if ( mb_strlen( $body ) > $maxBody )
            $errors['body'] = sprintf( 'The message can be at most %d characters long.', $maxBody );
        if ( !isset( $this->settings->categories()[$category] ) )
            $category = $this->settings->defaultCategory();
        if ( !isset( $this->settings->priorities()[$priority] ) )
            $priority = $this->settings->defaultPriority();
        return array( $errors, array( 'subject' => $subject, 'body' => $body, 'category' => $category, 'priority' => $priority,
                                      'subscription_id' => isset( $in['subscription_id'] ) ? max( 0, (int)$in['subscription_id'] ) : 0 ) );
    }

    public function validateReply( $body )
    {
        $body = ticketsystemText::clean( $body );
        $max = $this->settings->limit( 'MaxBodyLength', 50000 );
        if ( $body === '' )
            return array( array( 'body' => 'Please write your reply.' ), $body );
        if ( mb_strlen( $body ) > $max )
            return array( array( 'body' => sprintf( 'The reply can be at most %d characters long.', $max ) ), $body );
        return array( array(), $body );
    }

    /** The class attributes a class has (identifier => true). */
    protected static function classAttributes( $classIdentifier )
    {
        $class = eZContentClass::fetchByIdentifier( $classIdentifier );
        $out = array();
        if ( $class )
            foreach ( $class->fetchAttributes() as $a )
                $out[$a->attribute( 'identifier' )] = $a->attribute( 'data_type_string' );
        return $out;
    }

    /** The node a new ticket of this user goes below. */
    protected function parentNodeFor( eZUser $user )
    {
        if ( $this->settings->value( 'TicketSettings', 'Placement', 'user' ) === 'node' )
            return (int)$this->settings->value( 'TicketSettings', 'TicketNodeID', 0 );
        $object = $user->contentObject();
        return $object ? (int)$object->attribute( 'main_node_id' ) : 0;
    }

    /**
     * Opens a ticket for the signed-in user.
     *
     * @param array $in subject, body, category, priority, subscription_id (checked against the account when Katalyst
     *                  is present)
     * @return array ok, message, errors, ticket (ticketsystemTicket)
     */
    public function open( array $in )
    {
        if ( !$this->access->may( 'create' ) )
            return self::fail( 'You cannot open tickets with this account.' );
        list( $errors, $v ) = $this->validateNew( $in );
        if ( $errors )
            return self::fail( 'Please check the form.', $errors );
        $user = $this->access->user();
        $uid = $this->access->userId();
        $limit = $this->settings->limit( 'MaxOpenPerHour', 10 );
        if ( $limit > 0 && ticketsystemTicket::countOpenedSince( $uid, time() - 3600 ) >= $limit )
            return self::fail( 'You opened many tickets in the last hour. Please add to an open ticket, or try again later.' );
        $customer = $this->access->account()->customerFor( $uid );
        // the account is resolved before the transaction: a failing lookup must not roll the ticket back
        $accountKey = $customer ? 'katalyst:' . (int)$customer->attribute( 'id' ) : ticketsystemAccount::userKey( $uid );
        if ( $v['subscription_id'] && !( $customer && class_exists( 'expKatalystAccessService' )
                                         && expKatalystAccessService::subscriptionForCustomer( (int)$customer->attribute( 'id' ), $v['subscription_id'] ) ) )
            $v['subscription_id'] = 0;
        $parent = $this->parentNodeFor( $user );
        if ( $parent <= 0 || !eZContentObjectTreeNode::fetch( $parent, false, false ) )
            return self::fail( 'Tickets cannot be stored right now. Please contact us another way.' );

        $set = $this->settings->statusSet();
        $status = $set->role( 'Default' );
        $have = self::classAttributes( $this->settings->ticketClass() );
        if ( !$have )
            return self::fail( 'Tickets cannot be stored right now. Please contact us another way.' );

        $db = eZDB::instance();
        $db->begin();
        list( $number, $prefix, $year, $seq ) = ticketsystemNumber::take( $this->settings );
        $attributes = array( 'name' => $v['subject'], 'description' => ticketsystemText::toXml( $v['body'] ) );
        if ( isset( $have['ticket_number'] ) )
            $attributes['ticket_number'] = $number;
        if ( isset( $have['state'] ) )
            $attributes['state'] = $set->label( $status );
        if ( isset( $have['category'] ) )
            $attributes['category'] = $this->settings->categories()[$v['category']];
        if ( isset( $have['priority'] ) )
            $attributes['priority'] = $this->settings->priorities()[$v['priority']];
        if ( isset( $have['subscription'] ) && $v['subscription_id'] )
            $attributes['subscription'] = (string)$v['subscription_id'];
        $object = self::publishNew( array(
            'parent_node_id' => $parent, 'class_identifier' => $this->settings->ticketClass(),
            'creator_id' => $uid, 'attributes' => $attributes ) );
        if ( !$object instanceof eZContentObject || !$object->attribute( 'main_node_id' ) )
        {
            $db->rollback();
            return self::fail( 'The ticket could not be saved. Please try again.' );
        }
        $now = time();
        $ticket = ticketsystemTicket::create( array(
            'contentobject_id' => (int)$object->attribute( 'id' ), 'node_id' => (int)$object->attribute( 'main_node_id' ),
            'number' => $number, 'prefix' => $prefix, 'year' => $year, 'seq' => $seq,
            'account_key' => $accountKey, 'opener_id' => $uid, 'subject' => $v['subject'],
            'status' => $status, 'category' => $v['category'], 'priority' => $v['priority'],
            'subscription_id' => $v['subscription_id'], 'post_count' => 1, 'last_post' => $now, 'last_post_by' => $uid,
            'last_post_staff' => 0, 'created' => $now, 'modified' => $now, 'resolved' => 0, 'closed' => 0 ) );
        $ticket->store();
        self::subscribe( (int)$object->attribute( 'main_node_id' ), $uid );
        $db->commit();
        eZContentObject::clearCache( array( (int)$object->attribute( 'id' ) ) );
        $mail = $this->mailer->sendPost( $ticket, $object, $uid, true, $this->access->isStaff() );
        return array( 'ok' => true, 'message' => sprintf( 'Ticket %s is open. We sent you a copy by e-mail.', $number ), 'errors' => array(),
                      'ticket' => $ticket, 'mail' => $mail );
    }

    /**
     * Adds a reply to a ticket as the signed-in user.
     *
     * @param ticketsystemTicket $t
     * @param string $body
     * @param string $status staff only: the status the ticket gets ('' = the set's StaffReply)
     */
    public function reply( ticketsystemTicket $t, $body, $status = '' )
    {
        if ( !$this->access->canReply( $t ) )
            return self::fail( 'You cannot reply to this ticket.' );
        list( $errors, $body ) = $this->validateReply( $body );
        if ( $errors )
            return self::fail( 'Please check the form.', $errors );
        $staff = $this->access->isStaff() && !$this->isCustomerOf( $t );
        $set = $this->settings->statusSet();
        $next = $set->afterPost( (string)$t->attribute( 'status' ), $staff, $staff ? (string)$status : '' );
        if ( $next === null )
            return self::fail( 'This ticket is closed. Please open a new ticket and mention its number.' );
        $ticketObject = $t->contentObject();
        if ( !$ticketObject )
            return self::fail( 'This ticket cannot be found.' );
        $uid = $this->access->userId();
        $count = (int)$t->attribute( 'post_count' ) + 1;
        $db = eZDB::instance();
        $db->begin();
        $post = self::publishNew( array(
            'parent_node_id' => (int)$t->attribute( 'node_id' ), 'class_identifier' => $this->settings->postClass(),
            'creator_id' => $uid,
            'attributes' => array( 'name' => sprintf( 'Re: %s (#%d)', (string)$t->attribute( 'subject' ), $count ),
                                   'comment' => ticketsystemText::toXml( $body ) ) ) );
        if ( !$post instanceof eZContentObject || !$post->attribute( 'main_node_id' ) )
        {
            $db->rollback();
            return self::fail( 'The reply could not be saved. Please try again.' );
        }
        $now = time();
        $t->setAttribute( 'post_count', $count );
        $t->setAttribute( 'last_post', $now );
        $t->setAttribute( 'last_post_by', $uid );
        $t->setAttribute( 'last_post_staff', $staff ? 1 : 0 );
        $t->setAttribute( 'modified', $now );
        $this->applyStatus( $t, $next, $now );
        $t->store();
        self::subscribe( (int)$t->attribute( 'node_id' ), $uid );
        $db->commit();
        $this->syncStateAttribute( $t );
        eZContentObject::clearCache( array( (int)$t->attribute( 'contentobject_id' ), (int)$post->attribute( 'id' ) ) );
        $mail = $this->mailer->sendPost( $t, $post, $uid, false, $staff );
        return array( 'ok' => true, 'message' => 'Your reply was added. A copy is on its way to your e-mail.', 'errors' => array(),
                      'post' => $post, 'mail' => $mail );
    }

    /** Whether the signed-in user belongs to the ticket's account (a staff member who opened it for themselves). */
    protected function isCustomerOf( ticketsystemTicket $t )
    {
        return (int)$t->attribute( 'opener_id' ) === $this->access->userId()
            || in_array( (string)$t->attribute( 'account_key' ), $this->access->keys(), true );
    }

    /**
     * Changes the status: the customer can mark a ticket solved ("resolve") or open a solved one again ("reopen");
     * staff (manage) can set any status of StaffChoices.
     */
    public function changeStatus( ticketsystemTicket $t, $action, $status = '' )
    {
        if ( !$this->access->canRead( $t ) )
            return self::fail( 'You cannot change this ticket.' );
        $set = $this->settings->statusSet();
        $current = (string)$t->attribute( 'status' );
        if ( $action === 'resolve' && $this->access->canReply( $t ) && $set->customerMayResolve( $current ) )
            $next = $set->role( 'Resolved' );
        else if ( $action === 'reopen' && $this->access->canReply( $t ) && $set->customerMayReopen( $current ) )
            $next = $set->role( 'Reopen' );
        else if ( $action === 'set' && $this->access->isStaff() && in_array( (string)$status, $set->staffChoices, true ) )
            $next = (string)$status;
        else
            return self::fail( 'This change is not possible now.' );
        if ( $next === $current )
            return array( 'ok' => true, 'message' => 'Nothing changed.', 'errors' => array() );
        $now = time();
        $this->applyStatus( $t, $next, $now );
        $t->setAttribute( 'modified', $now );
        $t->store();
        $this->syncStateAttribute( $t );
        return array( 'ok' => true, 'message' => sprintf( 'The ticket is now: %s.', $set->label( $next ) ), 'errors' => array() );
    }

    /** Sets the status and its times on the index row (not stored). */
    protected function applyStatus( ticketsystemTicket $t, $status, $now )
    {
        $set = $this->settings->statusSet();
        $t->setAttribute( 'status', $status );
        $t->setAttribute( 'resolved', $set->isDone( $status ) && !$set->isClosed( $status ) ? $now : ( $set->isDone( $status ) ? (int)$t->attribute( 'resolved' ) : 0 ) );
        $t->setAttribute( 'closed', $set->isClosed( $status ) ? $now : 0 );
    }

    /** Writes the status into the ticket object's attribute "state" (a new version) when it differs. */
    protected function syncStateAttribute( ticketsystemTicket $t )
    {
        $object = $t->contentObject();
        if ( !$object )
            return;
        $map = $object->dataMap();
        if ( !isset( $map['state'] ) || $map['state']->attribute( 'data_type_string' ) !== 'ezselection' )
            return;
        $label = $this->settings->statusSet()->label( $t->attribute( 'status' ) );
        if ( trim( (string)$map['state']->toString() ) === $label || $this->stateText( $map['state'] ) === $label )
            return;
        self::publishUpdate( $object, array( 'attributes' => array( 'state' => $label ) ) );
    }

    protected function stateText( eZContentObjectAttribute $a )
    {
        $content = $a->attribute( 'class_content' );
        $ids = explode( '-', (string)$a->attribute( 'data_text' ) );
        foreach ( isset( $content['options'] ) ? $content['options'] : array() as $o )
            if ( in_array( (string)$o['id'], $ids, true ) )
                return (string)$o['name'];
        return '';
    }

    /** Subscribes a user to a ticket node (an Exponential notification subscription) unless they already are. */
    public static function subscribe( $nodeId, $userId )
    {
        if ( (int)$nodeId <= 0 || (int)$userId <= 0 )
            return false;
        if ( in_array( (int)$nodeId, array_map( 'intval', (array)eZSubtreeNotificationRule::fetchNodesForUserID( (int)$userId, false ) ), true ) )
            return false;
        eZSubtreeNotificationRule::create( (int)$nodeId, (int)$userId )->store();
        return true;
    }

    /**
     * Closes resolved tickets without a post for $days days (the cronjob). No mail.
     *
     * @return int how many were closed
     */
    public static function autoClose( $days, ?ticketsystemSettings $settings = null, $dryRun = false )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        $set = $settings->statusSet();
        if ( (int)$days <= 0 )
            return 0;
        $resolved = $set->role( 'Resolved' );
        $db = eZDB::instance();
        $where = "status='" . $db->escapeString( $resolved ) . "' AND resolved > 0 AND resolved < " . ( time() - (int)$days * 86400 );
        $n = 0;
        foreach ( ticketsystemTicket::fetchWhere( $where, 0, 500 ) as $t )
        {
            $n++;
            if ( $dryRun )
                continue;
            $t->setAttribute( 'status', $set->role( 'Closed' ) );
            $t->setAttribute( 'closed', time() );
            $t->store();
            $object = $t->contentObject();
            $map = $object ? $object->dataMap() : array();
            if ( isset( $map['state'] ) && $map['state']->attribute( 'data_type_string' ) === 'ezselection' )
                self::publishUpdate( $object, array( 'attributes' => array( 'state' => $set->label( $set->role( 'Closed' ) ) ) ) );
        }
        return $n;
    }

    /**
     * The posts of a ticket for display: the opening post first, then the replies oldest first.
     *
     * @return array[] id, author, author_id, is_staff, is_self, created, attribute (the ezxmltext attribute), opening
     */
    public function posts( ticketsystemTicket $t )
    {
        $object = $t->contentObject();
        if ( !$object )
            return array();
        $staffCache = array();
        $isStaff = function ( $userId ) use ( &$staffCache, $t ) {
            if ( !isset( $staffCache[$userId] ) )
            {
                $u = eZUser::fetch( (int)$userId );
                $staffCache[$userId] = $u instanceof eZUser
                    && (int)$userId !== (int)$t->attribute( 'opener_id' )
                    && ( new ticketsystemAccess( $u ) )->isStaff();
            }
            return $staffCache[$userId];
        };
        $row = function ( eZContentObject $o, $attr, $opening ) use ( $isStaff ) {
            $map = $o->dataMap();
            $owner = (int)$o->attribute( 'owner_id' );
            $ownerObject = eZContentObject::fetch( $owner );
            return array( 'id' => (int)$o->attribute( 'id' ), 'author_id' => $owner,
                          'author' => $ownerObject ? (string)$ownerObject->attribute( 'name' ) : '',
                          'is_staff' => $isStaff( $owner ), 'is_self' => $owner === $this->access->userId(),
                          'created' => (int)$o->attribute( 'published' ), 'opening' => $opening,
                          'attribute' => isset( $map[$attr] ) ? $map[$attr] : null );
        };
        $out = array( $row( $object, 'description', true ) );
        $node = eZContentObjectTreeNode::fetch( (int)$t->attribute( 'node_id' ) );
        if ( $node )
            foreach ( (array)eZContentObjectTreeNode::subTreeByNodeID( array(
                          'Depth' => 1, 'ClassFilterType' => 'include', 'ClassFilterArray' => array( $this->settings->postClass() ),
                          'SortBy' => array( array( 'published', true ) ), 'Limitation' => array(), 'IgnoreVisibility' => true,
                          'AsObject' => true ), (int)$t->attribute( 'node_id' ) ) as $child )
                $out[] = $row( $child->attribute( 'object' ), 'comment', false );
        return $out;
    }
}
