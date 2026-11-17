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
    public static function publishNew( array $params )
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
        if ( !$t )
            ticketsystemNumber::prepareTake( $settings );
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
    public static function classAttributes( $classIdentifier )
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
        ticketsystemNumber::prepareTake( $this->settings );
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
        $sla = new ticketsystemSla( $this->settings );
        $level = $sla->enabled() ? $sla->levelFor( $accountKey, $v['subscription_id'], $v['priority'] ) : array( 'level' => '', 'fallback' => '' );
        list( $responseDue, $resolveDue ) = $sla->enabled() ? $sla->dues( $now, $level['level'], $v['priority'], $level['fallback'], $v['category'] ) : array( 0, 0 );
        $files = isset( $in['files'] ) ? (array)$in['files'] : array();
        $ticket = ticketsystemTicket::create( array(
            'contentobject_id' => (int)$object->attribute( 'id' ), 'node_id' => (int)$object->attribute( 'main_node_id' ),
            'number' => $number, 'prefix' => $prefix, 'year' => $year, 'seq' => $seq,
            'account_key' => $accountKey, 'opener_id' => $uid, 'subject' => $v['subject'],
            'status' => $status, 'category' => $v['category'], 'priority' => $v['priority'],
            'priority_rank' => $this->settings->priorityRank( $v['priority'] ),
            'subscription_id' => $v['subscription_id'], 'post_count' => 1, 'last_post' => $now, 'last_post_by' => $uid,
            'last_post_staff' => 0, 'created' => $now, 'modified' => $now, 'resolved' => 0, 'closed' => 0,
            'source' => isset( $in['source'] ) && preg_match( '/^[a-z]{1,16}$/', (string)$in['source'] ) ? (string)$in['source'] : 'web',
            'sla_level' => (string)$level['level'], 'response_due' => $responseDue, 'resolve_due' => $resolveDue ) );
        $ticket->store();
        self::subscribe( (int)$object->attribute( 'main_node_id' ), $uid );
        self::indexPost( $ticket, $object, $uid, false, $v['body'] );
        $db->commit();
        if ( $files )
        {
            $stored = ( new ticketsystemFiles( $this->settings ) )->store( $ticket, $files, (int)$object->attribute( 'id' ), 0, $uid );
            $ticket->setAttribute( 'attachment_count', count( $stored ) );
            $ticket->store();
        }
        self::event( $ticket, $uid, 'open', '', $ticket->attribute( 'source' ) );
        $this->autoAssign( $ticket );
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
    public function reply( ticketsystemTicket $t, $body, $status = '', array $files = array(), $cannedId = 0 )
    {
        if ( !$this->access->canReply( $t ) )
            return self::fail( 'You cannot reply to this ticket.' );
        list( $errors, $body ) = $this->validateReply( $body );
        if ( $errors )
            return self::fail( 'Please check the form.', $errors );
        $staff = $this->access->isStaff() && !$this->isCustomerOf( $t );
        $set = $this->settings->statusSet();
        $canned = $staff && (int)$cannedId > 0 ? $this->cannedFor( (int)$cannedId ) : null;
        if ( $canned && (string)$canned->attribute( 'set_status' ) !== '' && (string)$status === '' )
            $status = (string)$canned->attribute( 'set_status' );
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
        $before = (string)$t->attribute( 'status' );
        $t->setAttribute( 'post_count', $count );
        $t->setAttribute( 'last_post', $now );
        $t->setAttribute( 'last_post_by', $uid );
        $t->setAttribute( 'last_post_staff', $staff ? 1 : 0 );
        $t->setAttribute( 'modified', $now );
        if ( $staff && !(int)$t->attribute( 'responded' ) )
            $t->setAttribute( 'responded', $now );
        if ( $staff && !(int)$t->attribute( 'assignee_id' ) && $this->settings->value( 'AssignmentSettings', 'ClaimOnReply', 'enabled' ) === 'enabled' )
        {
            $t->setAttribute( 'assignee_id', $uid );
            $t->setAttribute( 'assigned', $now );
            self::event( $t, $uid, 'claim', '0', (string)$uid );
        }
        $this->applyStatus( $t, $next, $now );
        $t->store();
        self::subscribe( (int)$t->attribute( 'node_id' ), $uid );
        self::indexPost( $t, $post, $uid, $staff, $body );
        $db->commit();
        if ( $files )
        {
            $stored = ( new ticketsystemFiles( $this->settings ) )->store( $t, $files, (int)$post->attribute( 'id' ), 0, $uid );
            $t->setAttribute( 'attachment_count', (int)$t->attribute( 'attachment_count' ) + count( $stored ) );
            $t->store();
        }
        if ( $next !== $before )
            self::event( $t, $uid, 'status', $before, $next );
        if ( $next !== $before && $set->isDone( $next ) )
            ( new ticketsystemCsat( $this->settings ) )->ask( $t );
        if ( $canned )
            $this->applyCannedActions( $t, $canned );
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
        self::event( $t, $this->access->userId(), 'status', $current, $next );
        $this->syncStateAttribute( $t );
        if ( $set->isDone( $next ) && !$set->isDone( $current ) )
            ( new ticketsystemCsat( $this->settings ) )->ask( $t );
        return array( 'ok' => true, 'message' => sprintf( 'The ticket is now: %s.', $set->label( $next ) ), 'errors' => array() );
    }

    /** Sets the status and its times on the index row (not stored). */
    protected function applyStatus( ticketsystemTicket $t, $status, $now )
    {
        $set = $this->settings->statusSet();
        $sla = new ticketsystemSla( $this->settings );
        foreach ( $sla->onStatus( $t->values(), (string)$t->attribute( 'status' ), (string)$status, $now, $set->isDone( $status ) ) as $k => $v )
            $t->setAttribute( $k, $v );
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

    // ------------------------------------------------------------------------------------------- 1.1: history

    /** Records one change of a ticket (ticketsystem_event). Values are identifiers or ids only. */
    public static function event( ticketsystemTicket $t, $actorId, $kind, $old = '', $new = '' )
    {
        $e = ticketsystemEvent::make( array( 'ticket_id' => (int)$t->attribute( 'id' ), 'actor_id' => (int)$actorId, 'kind' => (string)$kind,
                                             'old_value' => mb_substr( (string)$old, 0, 255 ), 'new_value' => mb_substr( (string)$new, 0, 255 ),
                                             'created' => time() ) );
        $e->store();
        return $e;
    }

    /** The history of a ticket for staff, oldest first: kind, actor, old, new, created (labels resolved). */
    public function history( ticketsystemTicket $t )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return array();
        $set = $this->settings->statusSet();
        $prios = $this->settings->priorities();
        $cats = $this->settings->categories();
        $names = array();
        $name = function ( $id ) use ( &$names ) {
            $id = (int)$id;
            if ( $id <= 0 )
                return '';
            if ( !isset( $names[$id] ) )
            {
                $o = eZContentObject::fetch( $id );
                $names[$id] = $o ? (string)$o->attribute( 'name' ) : '#' . $id;
            }
            return $names[$id];
        };
        $label = function ( $kind, $v ) use ( $set, $prios, $cats, $name ) {
            switch ( $kind )
            {
                case 'status': return $set->label( $v );
                case 'priority': return isset( $prios[$v] ) ? $prios[$v] : $v;
                case 'category': return isset( $cats[$v] ) ? $cats[$v] : $v;
                case 'assign': case 'claim': case 'unassign': return $name( $v );
                default: return (string)$v;
            }
        };
        $out = array();
        foreach ( ticketsystemEvent::fetchBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ), array( 'created' => 'asc', 'id' => 'asc' ), 0, 500 ) as $e )
        {
            $k = (string)$e->attribute( 'kind' );
            $out[] = array( 'kind' => $k, 'actor' => $name( $e->attribute( 'actor_id' ) ), 'created' => (int)$e->attribute( 'created' ),
                            'old' => $label( $k, (string)$e->attribute( 'old_value' ) ), 'new' => $label( $k, (string)$e->attribute( 'new_value' ) ) );
        }
        return $out;
    }

    // ---------------------------------------------------------------------------------------- 1.1: search index

    /** Keeps the text of a customer-visible post for the staff search (never a note). */
    public static function indexPost( ticketsystemTicket $t, eZContentObject $post, $authorId, $isStaff, $text )
    {
        $have = ticketsystemPostIndex::fetchBy( array( 'object_id' => (int)$post->attribute( 'id' ) ) );
        $row = $have ? $have[0] : ticketsystemPostIndex::make( array( 'object_id' => (int)$post->attribute( 'id' ) ) );
        $row->setAttribute( 'ticket_id', (int)$t->attribute( 'id' ) );
        $row->setAttribute( 'author_id', (int)$authorId );
        $row->setAttribute( 'is_staff', $isStaff ? 1 : 0 );
        $row->setAttribute( 'created', (int)$post->attribute( 'published' ) ?: time() );
        $row->setAttribute( 'body', mb_substr( (string)$text, 0, 50000 ) );
        $row->store();
    }

    // ------------------------------------------------------------------------------------ 1.1: assignment

    /**
     * The members of [AssignmentSettings] StaffGroup who can be assigned: user id => name, enabled users only.
     */
    public function staffMembers()
    {
        static $cache = array();
        $group = (string)$this->settings->value( 'AssignmentSettings', 'StaffGroup', 'Technical Support Group' );
        if ( isset( $cache[$group] ) )
            return $cache[$group];
        $out = array();
        $root = (int)eZINI::instance( 'content.ini' )->variable( 'NodeSettings', 'UserRootNode' );
        $groups = eZContentObjectTreeNode::subTreeByNodeID( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'user_group' ),
                                                                   'Limitation' => array(), 'IgnoreVisibility' => true,
                                                                   'AttributeFilter' => array( array( 'name', '=', $group ) ) ), $root );
        foreach ( (array)$groups as $g )
            foreach ( (array)eZContentObjectTreeNode::subTreeByNodeID( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'user' ),
                                                                               'Limitation' => array(), 'IgnoreVisibility' => true ), (int)$g->attribute( 'node_id' ) ) as $n )
            {
                $u = eZUser::fetch( (int)$n->attribute( 'contentobject_id' ) );
                if ( $u instanceof eZUser && $u->isEnabled() )
                    $out[(int)$n->attribute( 'contentobject_id' )] = (string)$n->attribute( 'name' );
            }
        asort( $out );
        return $cache[$group] = $out;
    }

    /** Whether a user may be assigned tickets: staff (ticketsystem/manage) and able to read the ticket. */
    protected function assignable( ticketsystemTicket $t, $userId )
    {
        $u = eZUser::fetch( (int)$userId );
        if ( !$u instanceof eZUser || !$u->isEnabled() )
            return false;
        $a = new ticketsystemAccess( $u );
        return $a->isStaff() && $a->canRead( $t );
    }

    /**
     * Assigns a ticket (staff only): to a staff member, to oneself ("claim", $userId = 0 or one's own id), or to
     * nobody ($userId = -1).
     */
    public function assign( ticketsystemTicket $t, $userId )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return self::fail( 'You cannot assign this ticket.' );
        $me = $this->access->userId();
        $userId = (int)$userId === 0 ? $me : (int)$userId;
        $old = (int)$t->attribute( 'assignee_id' );
        if ( $userId < 0 )
        {
            if ( !$old )
                return array( 'ok' => true, 'message' => 'Nothing changed.', 'errors' => array() );
            $t->setAttribute( 'assignee_id', 0 );
            $t->setAttribute( 'assigned', 0 );
            $t->setAttribute( 'modified', time() );
            $t->store();
            self::event( $t, $me, 'unassign', (string)$old, '0' );
            return array( 'ok' => true, 'message' => 'The ticket is not assigned to anyone now.', 'errors' => array() );
        }
        if ( $userId === $old )
            return array( 'ok' => true, 'message' => 'Nothing changed.', 'errors' => array() );
        if ( !$this->assignable( $t, $userId ) )
            return self::fail( 'This person cannot work on tickets.' );
        $t->setAttribute( 'assignee_id', $userId );
        $t->setAttribute( 'assigned', time() );
        $t->setAttribute( 'modified', time() );
        $t->store();
        self::event( $t, $me, $userId === $me ? 'claim' : 'assign', (string)$old, (string)$userId );
        if ( $userId !== $me )
        {
            self::subscribe( (int)$t->attribute( 'node_id' ), $userId );
            $this->mailer->sendNotice( $t, $userId, 'assigned' );
        }
        else
            self::subscribe( (int)$t->attribute( 'node_id' ), $userId );
        return array( 'ok' => true, 'message' => $userId === $me ? 'You took the ticket.' : 'The ticket was assigned.', 'errors' => array() );
    }

    /**
     * Round-robin for a new ticket of a queue with [AssignmentSettings] AutoAssign[<category>]=enabled: the staff
     * member with the fewest active assigned tickets, on a tie the one whose last assignment is oldest.
     */
    public function autoAssign( ticketsystemTicket $t )
    {
        $auto = (array)$this->settings->value( 'AssignmentSettings', 'AutoAssign', array() );
        $cat = (string)$t->attribute( 'category' );
        if ( !isset( $auto[$cat] ) || $auto[$cat] !== 'enabled' || (int)$t->attribute( 'assignee_id' ) )
            return 0;
        $set = $this->settings->statusSet();
        $quote = ticketsystemDesk::quoter();
        $active = ticketsystemDesk::statusCondition( $set->active(), $quote );
        $best = null;
        foreach ( array_keys( $this->staffMembers() ) as $uid )
        {
            if ( !$this->assignable( $t, $uid ) )
                continue;
            $load = ticketsystemTicket::countWhere( 'assignee_id = ' . (int)$uid . ( $active !== '' ? ' AND ' . $active : '' ) );
            $last = ticketsystemTicket::fetchWhere( 'assignee_id = ' . (int)$uid, 0, 1, 'assigned DESC, id DESC' );
            $key = array( $load, $last ? (int)$last[0]->attribute( 'assigned' ) : 0, $uid );
            if ( $best === null || $key < $best )
                $best = $key;
        }
        if ( $best === null )
            return 0;
        $t->setAttribute( 'assignee_id', $best[2] );
        $t->setAttribute( 'assigned', time() );
        $t->store();
        self::event( $t, 0, 'assign', '0', (string)$best[2] );
        self::subscribe( (int)$t->attribute( 'node_id' ), $best[2] );
        $this->mailer->sendNotice( $t, $best[2], 'assigned' );
        return $best[2];
    }

    // -------------------------------------------------------------------------- 1.1: priority and queue

    /** Changes the priority (staff): the index, the attribute "priority" of the ticket object, the SLA targets. */
    public function changePriority( ticketsystemTicket $t, $priority )
    {
        $prios = $this->settings->priorities();
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) || !isset( $prios[$priority] ) )
            return self::fail( 'This change is not possible now.' );
        $old = (string)$t->attribute( 'priority' );
        if ( $old === (string)$priority )
            return array( 'ok' => true, 'message' => 'Nothing changed.', 'errors' => array() );
        $sla = new ticketsystemSla( $this->settings );
        if ( $sla->enabled() && (string)$t->attribute( 'sla_level' ) !== '' )
        {
            // the new targets from the opening, plus the time the clock was paused so far
            $lvl = $sla->levelFor( (string)$t->attribute( 'account_key' ), (int)$t->attribute( 'subscription_id' ), $priority );
            list( $oldR, $oldS ) = $sla->dues( (int)$t->attribute( 'created' ), (string)$t->attribute( 'sla_level' ), $old, '', (string)$t->attribute( 'category' ) );
            list( $newR, $newS ) = $sla->dues( (int)$t->attribute( 'created' ), $lvl['level'], $priority, $lvl['fallback'], (string)$t->attribute( 'category' ) );
            if ( !(int)$t->attribute( 'responded' ) && $newR )
                $t->setAttribute( 'response_due', $newR + max( 0, (int)$t->attribute( 'response_due' ) - $oldR ) * ( $oldR ? 1 : 0 ) );
            if ( $newS )
                $t->setAttribute( 'resolve_due', $newS + max( 0, (int)$t->attribute( 'resolve_due' ) - $oldS ) * ( $oldS ? 1 : 0 ) );
            $t->setAttribute( 'sla_level', $lvl['level'] );
        }
        $t->setAttribute( 'priority', (string)$priority );
        $t->setAttribute( 'priority_rank', $this->settings->priorityRank( $priority ) );
        $t->setAttribute( 'modified', time() );
        $t->store();
        self::event( $t, $this->access->userId(), 'priority', $old, (string)$priority );
        $this->syncSelection( $t, 'priority', $prios[$priority] );
        return array( 'ok' => true, 'message' => sprintf( 'The priority is now: %s.', $prios[$priority] ), 'errors' => array() );
    }

    /** Moves the ticket to another queue (category; staff); a queue with round-robin assigns it when it has nobody. */
    public function changeQueue( ticketsystemTicket $t, $category )
    {
        $cats = $this->settings->categories();
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) || !isset( $cats[$category] ) )
            return self::fail( 'This change is not possible now.' );
        $old = (string)$t->attribute( 'category' );
        if ( $old === (string)$category )
            return array( 'ok' => true, 'message' => 'Nothing changed.', 'errors' => array() );
        $t->setAttribute( 'category', (string)$category );
        $t->setAttribute( 'modified', time() );
        $t->store();
        self::event( $t, $this->access->userId(), 'category', $old, (string)$category );
        $this->syncSelection( $t, 'category', $cats[$category] );
        $this->autoAssign( $t );
        return array( 'ok' => true, 'message' => sprintf( 'The ticket is now in the queue %s.', $cats[$category] ), 'errors' => array() );
    }

    /** Writes a label into an ezselection attribute of the ticket object when it differs and the option exists. */
    public function syncSelection( ticketsystemTicket $t, $identifier, $label )
    {
        $object = $t->contentObject();
        $map = $object ? $object->dataMap() : array();
        if ( !isset( $map[$identifier] ) || $map[$identifier]->attribute( 'data_type_string' ) !== 'ezselection' )
            return;
        if ( $this->stateText( $map[$identifier] ) === (string)$label )
            return;
        $content = $map[$identifier]->attribute( 'class_content' );
        foreach ( isset( $content['options'] ) ? $content['options'] : array() as $o )
            if ( (string)$o['name'] === (string)$label )
            {
                self::publishUpdate( $object, array( 'attributes' => array( $identifier => (string)$label ) ) );
                return;
            }
    }

    // ------------------------------------------------------------------------------------- 1.1: escalation

    /**
     * Escalates by hand (staff): one level up, the queue leads (or the support group) alerted, the ticket marked
     * escalated (ticketsystemEscalation::manual()).
     */
    public function escalate( ticketsystemTicket $t )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return self::fail( 'You cannot escalate this ticket.' );
        return ( new ticketsystemEscalation( $this->settings, $this ) )->manual( $t, $this->access->userId() );
    }

    /**
     * The SLA check of the cronjob (ticketsystemEscalation::run()): the escalation steps due on every active ticket
     * with a target, each once. Runs as the cronjob (no access checks).
     *
     * @return array checked, steps, mails, warned, breached, locked
     */
    public static function slaRun( ?ticketsystemSettings $settings = null, $now = null, $dryRun = false )
    {
        return ( new ticketsystemEscalation( $settings ) )->run( $now, $dryRun );
    }

    /** The SLA state of a ticket for the views (ticketsystemSla::state() plus labels). */
    public function slaState( ticketsystemTicket $t )
    {
        $sla = new ticketsystemSla( $this->settings );
        if ( !$sla->enabled() || ( !(int)$t->attribute( 'response_due' ) && !(int)$t->attribute( 'resolve_due' ) ) )
            return null;
        $s = $sla->state( $t->values(), time() );
        $s['level'] = (string)$t->attribute( 'sla_level' );
        $s['level_label'] = $sla->levelLabel( $s['level'] );
        $s['worst_label'] = ticketsystemSla::stateLabel( $s['worst'] );
        $s['escalated'] = (int)$t->attribute( 'escalated' );
        $s['log'] = $this->access->isStaff() ? ticketsystemEscalation::logFor( (int)$t->attribute( 'id' ) ) : array();
        foreach ( array( 'response', 'resolution' ) as $k )
        {
            $s[$k]['text'] = $s[$k]['state'] === 'none' ? '' : ticketsystemDesk::left( $s[$k]['left'] );
            $s[$k]['label'] = ticketsystemSla::stateLabel( $s[$k]['state'] );
        }
        return $s;
    }

    // ------------------------------------------------------------------------------------------ 1.1: notes

    /**
     * Adds an internal staff note. Notes are never shown to customers, never mailed to them, never in their exports
     * or the search of a reader who is not staff.
     */
    public function addNote( ticketsystemTicket $t, $body, array $files = array() )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return self::fail( 'Only support staff can write internal notes.' );
        $body = ticketsystemText::clean( $body );
        $max = $this->settings->limit( 'MaxNoteLength', 20000 );
        if ( $body === '' )
            return self::fail( 'Please write the note.', array( 'note' => 'Please write the note.' ) );
        if ( mb_strlen( $body ) > $max )
            return self::fail( 'Please check the form.', array( 'note' => sprintf( 'A note can be at most %d characters long.', $max ) ) );
        $n = ticketsystemNote::make( array( 'ticket_id' => (int)$t->attribute( 'id' ), 'author_id' => $this->access->userId(),
                                            'body' => $body, 'created' => time() ) );
        $n->store();
        if ( $files )
        {
            $stored = ( new ticketsystemFiles( $this->settings ) )->store( $t, $files, 0, (int)$n->attribute( 'id' ), $this->access->userId() );
            $n->setAttribute( 'attachment_count', count( $stored ) );
            $n->store();
        }
        $t->setAttribute( 'note_count', ticketsystemNote::countBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ) ) );
        $t->store();
        self::event( $t, $this->access->userId(), 'note', '', (string)$n->attribute( 'id' ) );
        return array( 'ok' => true, 'message' => 'The internal note was saved. Customers never see it.', 'errors' => array(), 'note' => $n );
    }

    /** The internal notes of a ticket, for staff only (an empty list for everyone else). */
    public function notes( ticketsystemTicket $t )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return array();
        $out = array();
        foreach ( ticketsystemNote::fetchBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ), array( 'created' => 'asc', 'id' => 'asc' ), 0, 500 ) as $n )
        {
            $o = eZContentObject::fetch( (int)$n->attribute( 'author_id' ) );
            $out[] = array( 'id' => (int)$n->attribute( 'id' ), 'author' => $o ? (string)$o->attribute( 'name' ) : '',
                            'author_id' => (int)$n->attribute( 'author_id' ), 'created' => (int)$n->attribute( 'created' ),
                            'paragraphs' => ticketsystemText::paragraphs( (string)$n->attribute( 'body' ) ) );
        }
        return $out;
    }

    // ---------------------------------------------------------------------------------- 1.1: attachments

    /**
     * The files of a ticket the reader may see: the files of posts for every reader, the files of notes for staff only.
     *
     * @return ticketsystemAttachment[]
     */
    public function attachments( ticketsystemTicket $t )
    {
        if ( !$this->access->canRead( $t ) )
            return array();
        $out = array();
        foreach ( ticketsystemAttachment::fetchBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ), array( 'id' => 'asc' ), 0, 500 ) as $a )
            if ( (int)$a->attribute( 'note_id' ) === 0 || $this->access->isStaff() )
                $out[] = $a;
        return $out;
    }

    /** The attachment of an id if the reader may download it, else null (also for a file of a note to a customer). */
    public function attachment( $id )
    {
        $a = ticketsystemAttachment::fetchByID( (int)$id );
        if ( !$a )
            return null;
        $t = ticketsystemTicket::fetch( (int)$a->attribute( 'ticket_id' ) );
        if ( !$t || !$this->access->canRead( $t ) )
            return null;
        if ( (int)$a->attribute( 'note_id' ) > 0 && !$this->access->isStaff() )
            return null;
        return $a;
    }

    // ---------------------------------------------------------------------------------------- 1.1: rating

    /** Whether the reader may rate the ticket now: a customer of it, the ticket resolved or closed, CSAT on. */
    public function mayRate( ticketsystemTicket $t )
    {
        return $this->settings->enabled( 'CSATSettings', 'Enabled' ) && $this->access->canReply( $t ) && $this->isCustomerOf( $t )
            && $this->settings->statusSet()->isDone( (string)$t->attribute( 'status' ) );
    }

    /** The customer's satisfaction rating: 1 to 5 and a comment; can be changed while the ticket is done. */
    public function rate( ticketsystemTicket $t, $score, $comment = '' )
    {
        if ( !$this->mayRate( $t ) )
            return self::fail( 'This ticket cannot be rated now.' );
        return ( new ticketsystemCsat( $this->settings ) )->record( $t, $score, $comment, $this->access->userId(), 'web' );
    }

    // ------------------------------------------------------------------------------------ 1.1: canned replies

    /** The placeholders of a canned reply (doc/canned-replies.md): name => what it stands for. */
    const CANNED_PLACEHOLDERS = array(
        'number' => 'the ticket number', 'subject' => 'the subject', 'status' => 'the status', 'queue' => 'the queue', 'priority' => 'the priority',
        'customer' => 'the name of the customer who opened the ticket', 'customer_first_name' => 'the first name of that customer',
        'staff_name' => 'your name', 'staff_first_name' => 'your first name',
        'link' => 'the address of the ticket', 'tickets_link' => 'the address of the customer\'s ticket list', 'new_ticket_link' => 'the address of a new ticket',
        'site' => 'the name of the site' );
    /** Other names of the same placeholders (1.1). */
    const CANNED_ALIASES = array( 'first_name' => 'customer_first_name', 'agent' => 'staff_name', 'ticket_link' => 'link' );

    /** The canned replies a staff member may use: the shared ones and their own, optionally for one queue. */
    public function cannedReplies( $category = '' )
    {
        if ( !$this->access->isStaff() )
            return array();
        $out = array();
        foreach ( ticketsystemCannedReply::fetchBy( array(), array( 'title' => 'asc', 'id' => 'asc' ), 0, 500 ) as $c )
        {
            if ( !self::cannedVisible( (int)$c->attribute( 'owner_id' ), $this->access->userId() ) )
                continue;
            if ( !self::cannedInQueue( (string)$c->attribute( 'category' ), (string)$category ) )
                continue;
            $out[] = $c;
        }
        return $out;
    }

    /** Whether a staff member sees a canned reply: the shared ones (owner 0) and their own (pure). */
    public static function cannedVisible( $ownerId, $userId )
    {
        return (int)$ownerId === 0 || ( (int)$userId > 0 && (int)$ownerId === (int)$userId );
    }

    /** Whether a canned reply of a queue ('' = every queue) is offered on a ticket of a queue ('' = any) (pure). */
    public static function cannedInQueue( $cannedQueue, $ticketQueue )
    {
        return (string)$cannedQueue === '' || (string)$ticketQueue === '' || (string)$cannedQueue === (string)$ticketQueue;
    }

    /**
     * Whether a staff member may create, change or delete a canned reply owned by $ownerId (0 = shared), and make it
     * owned by $newOwnerId (pure). $scopes: ticketsystemAccess::cannedScopes().
     */
    public static function mayKeepCanned( array $scopes, $userId, $ownerId, $newOwnerId = null )
    {
        $need = function ( $owner ) use ( $scopes, $userId ) {
            if ( (int)$owner === 0 )
                return !empty( $scopes['shared'] );
            return (int)$owner === (int)$userId && (int)$userId > 0 && !empty( $scopes['personal'] );
        };
        return $need( $ownerId ) && ( $newOwnerId === null || $need( $newOwnerId ) );
    }

    /** A canned reply the reader may use, or null. */
    public function cannedFor( $id )
    {
        $c = ticketsystemCannedReply::fetchByID( (int)$id );
        if ( !$c || !$this->access->isStaff() )
            return null;
        return self::cannedVisible( (int)$c->attribute( 'owner_id' ), $this->access->userId() ) ? $c : null;
    }

    /** The first name of a person: the attribute first_name when given, else the first word of the name (pure). */
    public static function firstName( $name, $first = '' )
    {
        $first = trim( (string)$first );
        if ( $first !== '' )
            return $first;
        $parts = preg_split( '/\s+/u', trim( (string)$name ) );
        return $parts && $parts[0] !== '' ? (string)$parts[0] : '';
    }

    /**
     * The values of the placeholders for a ticket (pure): $facts carries number, subject, status, queue, priority,
     * customer, customer_first, staff, staff_first, site_url, site.
     */
    public static function cannedValues( array $facts )
    {
        $f = $facts + array( 'number' => '', 'subject' => '', 'status' => '', 'queue' => '', 'priority' => '', 'customer' => '', 'customer_first' => '',
                             'staff' => '', 'staff_first' => '', 'site_url' => '', 'site' => '' );
        $base = rtrim( (string)$f['site_url'], '/' );
        $v = array(
            'number' => (string)$f['number'], 'subject' => (string)$f['subject'], 'status' => (string)$f['status'], 'queue' => (string)$f['queue'],
            'priority' => (string)$f['priority'], 'customer' => (string)$f['customer'],
            'customer_first_name' => self::firstName( $f['customer'], $f['customer_first'] ),
            'staff_name' => (string)$f['staff'], 'staff_first_name' => self::firstName( $f['staff'], $f['staff_first'] ),
            'link' => $base . '/ticketsystem/view/' . rawurlencode( (string)$f['number'] ),
            'tickets_link' => $base . '/ticketsystem/list', 'new_ticket_link' => $base . '/ticketsystem/new', 'site' => (string)$f['site'] );
        foreach ( self::CANNED_ALIASES as $alias => $name )
            $v[$alias] = $v[$name];
        return $v;
    }

    /** The first name attribute of a user object ('' when it has none). */
    protected static function firstNameOf( $object )
    {
        if ( !$object instanceof eZContentObject )
            return '';
        $map = $object->dataMap();
        return isset( $map['first_name'] ) ? trim( (string)$map['first_name']->toString() ) : '';
    }

    /** The text of a canned reply for a ticket, every placeholder filled in (doc/canned-replies.md). */
    public function cannedText( ticketsystemCannedReply $c, ticketsystemTicket $t )
    {
        $opener = eZContentObject::fetch( (int)$t->attribute( 'opener_id' ) );
        $me = eZContentObject::fetch( $this->access->userId() );
        $cats = $this->settings->categories();
        $prios = $this->settings->priorities();
        return self::expand( (string)$c->attribute( 'body' ), self::cannedValues( array(
            'number' => (string)$t->attribute( 'number' ), 'subject' => (string)$t->attribute( 'subject' ),
            'status' => $this->settings->statusSet()->label( $t->attribute( 'status' ) ),
            'queue' => isset( $cats[$t->attribute( 'category' )] ) ? $cats[$t->attribute( 'category' )] : '',
            'priority' => isset( $prios[$t->attribute( 'priority' )] ) ? $prios[$t->attribute( 'priority' )] : '',
            'customer' => $opener ? (string)$opener->attribute( 'name' ) : '', 'customer_first' => self::firstNameOf( $opener ),
            'staff' => $me ? (string)$me->attribute( 'name' ) : '', 'staff_first' => self::firstNameOf( $me ),
            'site_url' => $this->mailer->siteUrl(), 'site' => $this->mailer->brand() ) ) );
    }

    /**
     * The canned replies offered in the reply form of a ticket: id, title, shared, macro, text (filled in), set_status,
     * set_priority, assign_self. Staff only (an empty list for everyone else).
     */
    public function cannedChoices( ticketsystemTicket $t )
    {
        $out = array();
        foreach ( $this->cannedReplies( (string)$t->attribute( 'category' ) ) as $c )
            $out[] = array( 'id' => (int)$c->attribute( 'id' ), 'title' => (string)$c->attribute( 'title' ), 'shared' => !(int)$c->attribute( 'owner_id' ),
                            'macro' => (string)$c->attribute( 'set_status' ) !== '' || (string)$c->attribute( 'set_priority' ) !== '' || (int)$c->attribute( 'assign_self' ),
                            'text' => $this->cannedText( $c, $t ), 'set_status' => (string)$c->attribute( 'set_status' ),
                            'set_priority' => (string)$c->attribute( 'set_priority' ), 'assign_self' => (bool)$c->attribute( 'assign_self' ) );
        return $out;
    }

    /** Replaces {name} placeholders in one pass (pure): a value is never read again; unknown ones stay as they are. */
    public static function expand( $text, array $values )
    {
        return preg_replace_callback( '/\{([a-z_]{2,30})\}/', function ( $m ) use ( $values ) {
            return array_key_exists( $m[1], $values ) ? (string)$values[$m[1]] : $m[0];
        }, (string)$text );
    }

    /** The changes a macro makes after its reply was sent: priority, assign to the sender; counts the use. */
    protected function applyCannedActions( ticketsystemTicket $t, ticketsystemCannedReply $c )
    {
        if ( (string)$c->attribute( 'set_priority' ) !== '' && (string)$c->attribute( 'set_priority' ) !== (string)$t->attribute( 'priority' ) )
            $this->changePriority( $t, (string)$c->attribute( 'set_priority' ) );
        if ( (int)$c->attribute( 'assign_self' ) && (int)$t->attribute( 'assignee_id' ) !== $this->access->userId() )
            $this->assign( $t, 0 );
        $c->setAttribute( 'use_count', (int)$c->attribute( 'use_count' ) + 1 );
        $c->store();
    }

    /** The canned replies the reader may keep (the management page): the ones whose owner the policy reaches. */
    public function cannedManageable()
    {
        $scopes = $this->access->cannedScopes();
        $out = array();
        foreach ( $this->cannedReplies() as $c )
            if ( self::mayKeepCanned( $scopes, $this->access->userId(), (int)$c->attribute( 'owner_id' ) ) )
                $out[] = $c;
        return $out;
    }

    /**
     * Saves a canned reply: a new one ($id 0) or one the reader may keep (policy ticketsystem/canned: Personal for
     * one's own, Shared for the team's ones; making one shared or personal needs both ends).
     */
    public function saveCanned( $id, array $in )
    {
        if ( !$this->access->isStaff() )
            return self::fail( 'Only support staff can keep canned replies.' );
        $scopes = $this->access->cannedScopes();
        $uid = $this->access->userId();
        $newOwner = !empty( $in['shared'] ) ? 0 : $uid;
        $c = (int)$id > 0 ? $this->cannedFor( (int)$id ) : ticketsystemCannedReply::make( array( 'created_by' => $uid, 'created' => time(), 'owner_id' => $newOwner ) );
        if ( !$c )
            return self::fail( 'This canned reply cannot be found.' );
        if ( !self::mayKeepCanned( $scopes, $uid, (int)$c->attribute( 'owner_id' ), $newOwner ) )
            return self::fail( $newOwner === 0 || (int)$c->attribute( 'owner_id' ) === 0
                ? 'You cannot change the canned replies shared with the team.' : 'You cannot keep canned replies.' );
        $errors = array();
        $title = trim( preg_replace( '/\s+/u', ' ', ticketsystemText::clean( isset( $in['title'] ) ? $in['title'] : '' ) ) );
        $body = ticketsystemText::clean( isset( $in['body'] ) ? $in['body'] : '' );
        $maxBody = min( $this->settings->limit( 'MaxBodyLength', 50000 ), max( 100, (int)$this->settings->value( 'CannedSettings', 'MaxLength', 20000 ) ) );
        if ( $title === '' || mb_strlen( $title ) > 200 )
            $errors['title'] = 'Please give it a title of at most 200 characters.';
        if ( $body === '' )
            $errors['body'] = 'Please write the text.';
        else if ( mb_strlen( $body ) > $maxBody )
            $errors['body'] = sprintf( 'The text can be at most %d characters long.', $maxBody );
        $cat = isset( $in['category'] ) && isset( $this->settings->categories()[$in['category']] ) ? (string)$in['category'] : '';
        $status = isset( $in['set_status'] ) && in_array( (string)$in['set_status'], $this->settings->statusSet()->staffChoices, true ) ? (string)$in['set_status'] : '';
        $prio = isset( $in['set_priority'] ) && isset( $this->settings->priorities()[$in['set_priority']] ) ? (string)$in['set_priority'] : '';
        $macros = $this->settings->enabled( 'CannedSettings', 'Macros', 'enabled' );
        if ( $errors )
            return self::fail( 'Please check the form.', $errors );
        $c->setAttribute( 'title', $title );
        $c->setAttribute( 'body', $body );
        $c->setAttribute( 'category', $cat );
        $c->setAttribute( 'set_status', $macros ? $status : '' );
        $c->setAttribute( 'set_priority', $macros ? $prio : '' );
        $c->setAttribute( 'assign_self', $macros && !empty( $in['assign_self'] ) ? 1 : 0 );
        $c->setAttribute( 'owner_id', $newOwner );
        $c->setAttribute( 'modified', time() );
        $c->store();
        return array( 'ok' => true, 'message' => 'The canned reply was saved.', 'errors' => array(), 'canned' => $c );
    }

    public function deleteCanned( $id )
    {
        $c = $this->cannedFor( (int)$id );
        if ( !$c )
            return self::fail( 'This canned reply cannot be found.' );
        if ( !self::mayKeepCanned( $this->access->cannedScopes(), $this->access->userId(), (int)$c->attribute( 'owner_id' ) ) )
            return self::fail( 'You cannot delete this canned reply.' );
        $c->remove();
        return array( 'ok' => true, 'message' => 'The canned reply was deleted.', 'errors' => array() );
    }
}
