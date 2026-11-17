<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Merge and split of tickets (doc/merge-split.md; staff only, ticketsystem/manage).
 *
 * **Merge** (a duplicate into the ticket that stays): only tickets of the same account, so no post ever reaches another
 * customer. The replies of the merged ticket move below the ticket that stays (content nodes, with their authors, dates
 * and files), its opening post becomes a post there ("Merged from ..."), dated as it was; its internal notes, time
 * entries and CC addresses move with it and stay what they were (a note stays staff-only). Its followers follow the
 * ticket that stays. The merged ticket is closed with merged_into set: its number stays valid and ticketsystem/view
 * of it redirects to the ticket that stays. No mail is sent; both histories record it.
 *
 * **Split** (posts that are another matter): staff pick replies of a ticket; a new ticket of the same opener, account,
 * queue and priority is opened with a staff post naming where it came from, and the picked replies move below it with
 * their files, time entries and search index. Followers of the old ticket follow the new one. The opening post is never
 * split off. No mail is sent; both histories record it.
 */
class ticketsystemMerge extends ticketsystemService
{
    /** Whether two tickets may be merged (pure): array( ok, reason ). */
    public static function mergeable( array $source, array $target, array $doneStatuses = array() )
    {
        if ( (int)$source['id'] === (int)$target['id'] )
            return array( false, 'A ticket cannot be merged into itself.' );
        if ( (int)$source['merged_into'] > 0 )
            return array( false, 'This ticket was merged already.' );
        if ( (int)$target['merged_into'] > 0 )
            return array( false, 'That ticket was merged into another one; merge into that one.' );
        if ( (string)$source['account_key'] !== (string)$target['account_key'] )
            return array( false, 'Only tickets of the same customer account can be merged.' );
        if ( in_array( (string)$target['status'], $doneStatuses, true ) )
            return array( false, 'That ticket is closed. Reopen it first, or merge the other way round.' );
        return array( true, '' );
    }

    /** The reply objects below a ticket node (any class but the ticket's own), oldest first. */
    protected function childNodes( ticketsystemTicket $t )
    {
        $out = array();
        foreach ( (array)eZContentObjectTreeNode::subTreeByNodeID( array(
                      'Depth' => 1, 'SortBy' => array( array( 'published', true ) ), 'Limitation' => array(), 'IgnoreVisibility' => true,
                      'AsObject' => true ), (int)$t->attribute( 'node_id' ) ) as $child )
            $out[] = $child;
        return $out;
    }

    /** Moves the rows of the small tables of the given posts (object ids) or of all a ticket's rows to another ticket. */
    protected static function moveRows( $class, $fromTicket, $toTicket, ?array $postIds = null, $postField = 'post_id' )
    {
        $conds = array( 'ticket_id' => (int)$fromTicket );
        $n = 0;
        foreach ( $class::fetchBy( $conds, null, 0, 10000 ) as $row )
        {
            if ( $postIds !== null && !in_array( (int)$row->attribute( $postField ), $postIds, true ) )
                continue;
            $row->setAttribute( 'ticket_id', (int)$toTicket );
            $row->store();
            $n++;
        }
        return $n;
    }

    /** Recounts what a ticket holds after a merge or split and stores it. */
    protected function recount( ticketsystemTicket $t )
    {
        $tid = (int)$t->attribute( 'id' );
        $posts = 1;
        $last = (int)$t->attribute( 'created' );
        $lastBy = (int)$t->attribute( 'opener_id' );
        $node = (int)$t->attribute( 'node_id' );
        foreach ( (array)eZContentObjectTreeNode::subTreeByNodeID( array(
                      'Depth' => 1, 'ClassFilterType' => 'include', 'ClassFilterArray' => array( $this->settings->postClass() ),
                      'SortBy' => array( array( 'published', true ) ), 'Limitation' => array(), 'IgnoreVisibility' => true, 'AsObject' => true ), $node ) as $child )
        {
            $posts++;
            $o = $child->attribute( 'object' );
            if ( (int)$o->attribute( 'published' ) >= $last )
            {
                $last = (int)$o->attribute( 'published' );
                $lastBy = (int)$o->attribute( 'owner_id' );
            }
        }
        $t->setAttribute( 'post_count', $posts );
        $t->setAttribute( 'last_post', $last );
        $t->setAttribute( 'last_post_by', $lastBy );
        $t->setAttribute( 'note_count', ticketsystemNote::countBy( array( 'ticket_id' => $tid ) ) );
        $t->setAttribute( 'attachment_count', ticketsystemAttachment::countBy( array( 'ticket_id' => $tid ) ) );
        $t->setAttribute( 'modified', time() );
        $t->store();
        ticketsystemTime::resum( $t );
    }

    /** Every follower of one ticket follows another. */
    protected static function copyFollowers( ticketsystemTicket $from, ticketsystemTicket $to )
    {
        foreach ( ticketsystemWatch::subscriberIds( $from ) as $uid )
            self::subscribe( (int)$to->attribute( 'node_id' ), $uid );
    }

    /**
     * Merges $source into $target (staff). The source is closed and points to the target.
     *
     * @return array ok, message, errors, target
     */
    public function merge( ticketsystemTicket $source, ticketsystemTicket $target )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $source ) || !$this->access->canRead( $target ) )
            return self::fail( 'Only support staff can merge tickets.' );
        $set = $this->settings->statusSet();
        list( $ok, $why ) = self::mergeable( $source->values(), $target->values(), (array)$set->closed );
        if ( !$ok )
            return self::fail( $why );
        $sourceObject = $source->contentObject();
        if ( !$sourceObject || !$target->contentObject() )
            return self::fail( 'This ticket cannot be found.' );
        $me = $this->access->userId();
        $sid = (int)$source->attribute( 'id' );
        $tid = (int)$target->attribute( 'id' );
        $map = $sourceObject->dataMap();
        $paragraphs = isset( $map['description'] ) ? ticketsystemText::fromXml( $map['description']->attribute( 'data_text' ) ) : array();
        $text = sprintf( '(Merged from ticket %s: %s)', $source->attribute( 'number' ), $source->attribute( 'subject' ) ) . "\n\n"
              . implode( "\n\n", array_map( function ( $p ) { return is_array( $p ) ? implode( "\n", $p ) : (string)$p; }, $paragraphs ) );
        $db = eZDB::instance();
        $db->begin();
        // the opening post of the merged ticket, as a post of its author, dated as it was
        $post = self::publishNew( array(
            'parent_node_id' => (int)$target->attribute( 'node_id' ), 'class_identifier' => $this->settings->postClass(),
            'creator_id' => (int)$sourceObject->attribute( 'owner_id' ),
            'attributes' => array( 'name' => sprintf( 'Merged from %s: %s', $source->attribute( 'number' ), $source->attribute( 'subject' ) ),
                                   'comment' => ticketsystemText::toXml( trim( $text ) ) ) ) );
        if ( !$post instanceof eZContentObject || !$post->attribute( 'main_node_id' ) )
        {
            $db->rollback();
            return self::fail( 'The tickets could not be merged. Please try again.' );
        }
        $post->setAttribute( 'published', (int)$sourceObject->attribute( 'published' ) );
        $post->store();
        $moved = array( (int)$post->attribute( 'id' ) );
        foreach ( $this->childNodes( $source ) as $child )
        {
            if ( !eZContentObjectTreeNodeOperations::move( (int)$child->attribute( 'node_id' ), (int)$target->attribute( 'node_id' ) ) )
            {
                $db->rollback();
                return self::fail( 'The tickets could not be merged: a post could not be moved.' );
            }
            $moved[] = (int)$child->attribute( 'contentobject_id' );
        }
        // the files of the opening post now belong to its copy; every row of the merged ticket moves
        foreach ( ticketsystemAttachment::fetchBy( array( 'ticket_id' => $sid, 'post_id' => (int)$sourceObject->attribute( 'id' ) ) ) as $a )
        {
            $a->setAttribute( 'post_id', (int)$post->attribute( 'id' ) );
            $a->store();
        }
        foreach ( ticketsystemPostIndex::fetchBy( array( 'object_id' => (int)$sourceObject->attribute( 'id' ) ) ) as $row )
        {
            $row->setAttribute( 'object_id', (int)$post->attribute( 'id' ) );
            $row->store();
        }
        foreach ( array( 'ticketsystemAttachment', 'ticketsystemNote', 'ticketsystemPostIndex', 'ticketsystemTimeEntry' ) as $class )
            self::moveRows( $class, $sid, $tid );
        $have = array();
        foreach ( ticketsystemWatch::ccRows( $target ) as $c )
            $have[strtolower( (string)$c->attribute( 'email' ) )] = true;
        foreach ( ticketsystemWatch::ccRows( $source ) as $c )
        {
            if ( isset( $have[strtolower( (string)$c->attribute( 'email' ) )] ) )
                $c->remove();
            else
            {
                $c->setAttribute( 'ticket_id', $tid );
                $c->store();
            }
        }
        $now = time();
        $before = (string)$source->attribute( 'status' );
        $this->applyStatus( $source, $set->role( 'Closed' ), $now );
        $source->setAttribute( 'merged_into', $tid );
        $source->setAttribute( 'modified', $now );
        $source->store();
        $this->recount( $source );
        $this->recount( $target );
        $db->commit();
        self::copyFollowers( $source, $target );
        self::event( $source, $me, 'merged', $before, (string)$target->attribute( 'number' ) );
        self::event( $target, $me, 'merge', '', (string)$source->attribute( 'number' ) );
        $this->syncStateAttribute( $source );
        eZContentObject::clearCache( array_merge( $moved, array( (int)$sourceObject->attribute( 'id' ), (int)$target->attribute( 'contentobject_id' ) ) ) );
        return array( 'ok' => true, 'message' => sprintf( 'Ticket %s was merged into %s. Its number leads here now.', $source->attribute( 'number' ), $target->attribute( 'number' ) ),
                      'errors' => array(), 'target' => $target );
    }

    /**
     * Splits replies off a ticket into a new ticket (staff).
     *
     * @param int[] $postIds content object ids of replies of $t
     * @return array ok, message, errors, ticket (the new one)
     */
    public function split( ticketsystemTicket $t, array $postIds, $subject )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) || !$this->access->may( 'create' ) )
            return self::fail( 'Only support staff can split tickets.' );
        if ( (int)$t->attribute( 'merged_into' ) > 0 )
            return self::fail( 'This ticket was merged into another one.' );
        $postIds = array_values( array_unique( array_filter( array_map( 'intval', $postIds ) ) ) );
        $nodes = array();
        foreach ( $this->childNodes( $t ) as $child )
            if ( in_array( (int)$child->attribute( 'contentobject_id' ), $postIds, true ) )
                $nodes[] = $child;
        if ( !$nodes || count( $nodes ) !== count( $postIds ) )
            return self::fail( 'Please pick one or more replies of this ticket (the opening post stays).', array( 'split' => 'Please pick one or more replies of this ticket (the opening post stays).' ) );
        $opener = eZUser::fetch( (int)$t->attribute( 'opener_id' ) );
        if ( !$opener instanceof eZUser )
            return self::fail( 'The customer of this ticket cannot be found.' );
        list( $errors, $v ) = $this->validateNew( array( 'subject' => $subject, 'category' => (string)$t->attribute( 'category' ),
                                                         'priority' => (string)$t->attribute( 'priority' ), 'subscription_id' => (int)$t->attribute( 'subscription_id' ),
                                                         'body' => sprintf( 'This ticket was split from ticket %s: %d of its replies continue here.', $t->attribute( 'number' ), count( $nodes ) ) ) );
        if ( isset( $errors['subject'] ) )
            return self::fail( 'Please check the form.', array( 'split_subject' => $errors['subject'] ) );
        $r = $this->create( $opener, (string)$t->attribute( 'account_key' ), $v, array( 'creator_id' => $this->access->userId(), 'staff_post' => true,
                                                                                        'notify' => false, 'source' => 'split', 'message' => 'Ticket %s was split off.' ) );
        if ( !$r['ok'] )
            return $r;
        $new = $r['ticket'];
        $db = eZDB::instance();
        $db->begin();
        $moved = array();
        foreach ( $nodes as $child )
        {
            if ( !eZContentObjectTreeNodeOperations::move( (int)$child->attribute( 'node_id' ), (int)$new->attribute( 'node_id' ) ) )
            {
                $db->rollback();
                return self::fail( sprintf( 'Ticket %s was opened, but the replies could not be moved to it.', $new->attribute( 'number' ) ) );
            }
            $moved[] = (int)$child->attribute( 'contentobject_id' );
        }
        $tid = (int)$t->attribute( 'id' );
        $nid = (int)$new->attribute( 'id' );
        self::moveRows( 'ticketsystemAttachment', $tid, $nid, $moved );
        self::moveRows( 'ticketsystemPostIndex', $tid, $nid, $moved, 'object_id' );
        self::moveRows( 'ticketsystemTimeEntry', $tid, $nid, $moved );
        $this->recount( $t );
        $this->recount( $new );
        $db->commit();
        self::copyFollowers( $t, $new );
        self::event( $t, $this->access->userId(), 'split', '', (string)$new->attribute( 'number' ) );
        self::event( $new, $this->access->userId(), 'splitfrom', '', (string)$t->attribute( 'number' ) );
        eZContentObject::clearCache( array_merge( $moved, array( (int)$t->attribute( 'contentobject_id' ), (int)$new->attribute( 'contentobject_id' ) ) ) );
        return array( 'ok' => true, 'message' => sprintf( '%d replies were split off into ticket %s.', count( $moved ), $new->attribute( 'number' ) ),
                      'errors' => array(), 'ticket' => $new );
    }

    /**
     * The replies staff can split off (content object id, author, date, an excerpt of the text): every reply, never the
     * opening post.
     */
    public function splittable( ticketsystemTicket $t )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return array();
        $out = array();
        foreach ( $this->posts( $t ) as $p )
            if ( !$p['opening'] )
                $out[] = array( 'id' => $p['id'], 'author' => $p['author'], 'created' => $p['created'] );
        return $out;
    }
}
