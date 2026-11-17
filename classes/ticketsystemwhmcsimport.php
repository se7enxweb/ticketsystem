<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The import of WHMCS support tickets as closed history (doc/import.md, bin/php/import_whmcs.php), from the JSON of
 * bin/php/whmcs_export.php. Idempotent: every ticket, reply and note is recorded in ticketsystem_import and never made
 * twice; a later run adds only what is new. A dry run (the default) counts what it would do and changes nothing.
 *
 * - The customer: the WHMCS client id only, never an e-mail address. [ImportSettings] ClientResolver=katalyst finds
 *   the Katalyst customer linked to that client (katalyst_customer_ref, source whmcs) and its user; a client map
 *   (WHMCS client id => user id) can be given instead or on top. A ticket whose client has no customer or user here,
 *   and a guest ticket (no client), is skipped and counted.
 * - The ticket: a "ticket" object below the opener's user node, created by the opener, with the WHMCS date; the
 *   number of its year (7X-2024-00001); status Closed; source "whmcs". The replies are "ticket_comment" objects (an
 *   administrator's reply as [ImportSettings] StaffUserID), the notes internal notes. No mail is sent and nobody is
 *   subscribed.
 */
class ticketsystemWhmcsImport
{
    const SOURCE = 'whmcs';

    /** @var ticketsystemSettings */
    protected $settings;
    /** @var bool */
    protected $dryRun;
    /** @var array WHMCS client id => user id */
    protected $clientMap;
    /** @var array */
    public $counts = array( 'tickets' => 0, 'imported' => 0, 'already' => 0, 'skipped_guest' => 0, 'skipped_no_customer' => 0,
                            'skipped_no_user' => 0, 'replies' => 0, 'notes' => 0, 'failed' => 0 );

    public function __construct( $dryRun = true, array $clientMap = array(), ?ticketsystemSettings $settings = null )
    {
        $this->dryRun = (bool)$dryRun;
        $this->clientMap = array_map( 'intval', $clientMap );
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    /** Plain text of a WHMCS message (HTML and entities out, line endings kept; pure). */
    public static function text( $message )
    {
        $m = str_replace( array( "\r\n", "\r" ), "\n", (string)$message );
        $m = preg_replace( '#<br\s*/?>#i', "\n", $m );
        $m = preg_replace( '#</p>#i', "\n\n", $m );
        $m = html_entity_decode( strip_tags( $m ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return trim( preg_replace( "/\n{3,}/", "\n\n", $m ) );
    }

    /** A WHMCS date ("2024-05-11 14:03:29") as a Unix time (0 for none). */
    public static function time( $date )
    {
        $t = strtotime( (string)$date );
        return $t && $t > 0 ? (int)$t : 0;
    }

    /** The import map row of a record, or null. */
    protected static function mapped( $kind, $externalId )
    {
        $rows = ticketsystemImportMap::fetchBy( array( 'source' => self::SOURCE, 'kind' => (string)$kind, 'external_id' => (string)$externalId ) );
        return $rows ? $rows[0] : null;
    }

    protected function remember( $kind, $externalId, $localId )
    {
        ticketsystemImportMap::make( array( 'source' => self::SOURCE, 'kind' => $kind, 'external_id' => (string)$externalId,
                                            'local_id' => (int)$localId, 'created' => time() ) )->store();
    }

    /** The user (content object id) and account key of a WHMCS client, or a reason it has none. */
    public function opener( $clientId )
    {
        $clientId = (int)$clientId;
        if ( $clientId <= 0 )
            return array( 0, '', 'skipped_guest' );
        if ( isset( $this->clientMap[$clientId] ) && $this->clientMap[$clientId] > 0 )
            return array( $this->clientMap[$clientId], ticketsystemAccount::userKey( $this->clientMap[$clientId] ), '' );
        if ( (string)$this->settings->value( 'ImportSettings', 'ClientResolver', 'katalyst' ) !== 'katalyst'
             || !class_exists( 'expKatalystCustomerRef' ) || !class_exists( 'expKatalystCustomer' ) )
            return array( 0, '', 'skipped_no_customer' );
        $refs = eZPersistentObject::fetchObjectList( expKatalystCustomerRef::definition(), null,
                                                     array( 'source' => 'whmcs', 'external_id' => (string)$clientId ), null, null, false );
        $customerId = 0;
        foreach ( (array)$refs as $r )
            if ( (int)$r['customer_id'] > 0 && empty( $r['gone'] ) )
                $customerId = (int)$r['customer_id'];
        if ( !$customerId )
            return array( 0, '', 'skipped_no_customer' );
        $c = expKatalystCustomer::fetch( $customerId );
        $userId = $c ? (int)$c->attribute( 'user_id' ) : 0;
        if ( $userId <= 0 || !eZUser::fetch( $userId ) )
            return array( 0, 'katalyst:' . $customerId, 'skipped_no_user' );
        return array( $userId, 'katalyst:' . $customerId, '' );
    }

    /** The staff user that administrators' replies and notes are posted as. */
    protected function staffUser()
    {
        $id = (int)$this->settings->value( 'ImportSettings', 'StaffUserID', 0 );
        return $id > 0 ? $id : 14;
    }

    /** The priority of a WHMCS urgency (Low, Medium, High; anything else the default). */
    protected function priority( $urgency )
    {
        $u = strtolower( trim( (string)$urgency ) );
        return isset( $this->settings->priorities()[$u] ) ? $u : $this->settings->defaultPriority();
    }

    /**
     * Imports the tickets of an export.
     *
     * @param array $export the decoded JSON of whmcs_export.php
     * @return array the counts
     */
    public function run( array $export )
    {
        foreach ( isset( $export['tickets'] ) ? (array)$export['tickets'] : array() as $t )
        {
            $this->counts['tickets']++;
            try
            {
                $this->one( (array)$t, isset( $export['departments'] ) ? (array)$export['departments'] : array() );
            }
            catch ( Throwable $e )
            {
                $this->counts['failed']++;
                eZDebug::writeError( 'WHMCS ticket ' . (int)$t['id'] . ': ' . $e->getMessage(), __METHOD__ );
            }
        }
        return $this->counts;
    }

    protected function one( array $w, array $departments )
    {
        $map = self::mapped( 'ticket', $w['id'] );
        $ticket = $map ? ticketsystemTicket::fetch( (int)$map->attribute( 'local_id' ) ) : null;
        if ( !$ticket )
        {
            list( $userId, $accountKey, $skip ) = $this->opener( $w['userid'] );
            if ( $skip !== '' )
            {
                $this->counts[$skip]++;
                return;
            }
            $this->counts['imported']++;
            $this->counts['replies'] += count( (array)$w['replies'] );
            $this->counts['notes'] += count( (array)$w['notes'] );
            if ( $this->dryRun )
                return;
            $ticket = $this->createTicket( $w, $userId, $accountKey, $departments );
            if ( !$ticket )
            {
                $this->counts['failed']++;
                return;
            }
        }
        else
        {
            $this->counts['already']++;
            foreach ( (array)$w['replies'] as $r )
                if ( !self::mapped( 'reply', $r['id'] ) )
                    $this->counts['replies']++;
            foreach ( (array)$w['notes'] as $n )
                if ( !self::mapped( 'note', $n['id'] ) )
                    $this->counts['notes']++;
            if ( $this->dryRun )
                return;
        }
        $this->addReplies( $ticket, (array)$w['replies'] );
        $this->addNotes( $ticket, (array)$w['notes'] );
    }

    /** Publishes an object as created on a WHMCS date. */
    protected static function publishAt( array $params, $time )
    {
        $o = ticketsystemService::publishNew( $params );
        if ( $o instanceof eZContentObject && $time > 0 )
        {
            $o->setAttribute( 'published', (int)$time );
            $o->setAttribute( 'modified', (int)$time );
            $o->store();
        }
        return $o;
    }

    protected function createTicket( array $w, $userId, $accountKey, array $departments )
    {
        $user = eZUser::fetch( $userId );
        $object = $user ? $user->contentObject() : null;
        $parent = $object ? (int)$object->attribute( 'main_node_id' ) : 0;
        if ( $parent <= 0 )
            return null;
        $created = self::time( $w['date'] ) ?: time();
        $closed = self::time( $w['lastreply'] ) ?: $created;
        $set = $this->settings->statusSet();
        $status = $set->role( 'Closed' );
        $subject = mb_substr( trim( preg_replace( '/\s+/u', ' ', self::text( $w['subject'] ) ) ), 0, 200 ) ?: 'WHMCS ticket ' . $w['tid'];
        $body = self::text( $w['message'] ) ?: '(no text)';
        $have = ticketsystemService::classAttributes( $this->settings->ticketClass() );
        $priority = $this->priority( $w['urgency'] );
        $db = eZDB::instance();
        ticketsystemNumber::prepareTake( $this->settings, $created );
        $db->begin();
        list( $number, $prefix, $year, $seq ) = ticketsystemNumber::take( $this->settings, $created );
        $attributes = array( 'name' => $subject, 'description' => ticketsystemText::toXml( $body ) );
        if ( isset( $have['ticket_number'] ) )
            $attributes['ticket_number'] = $number;
        if ( isset( $have['state'] ) )
            $attributes['state'] = $set->label( $status );
        if ( isset( $have['priority'] ) )
            $attributes['priority'] = $this->settings->priorities()[$priority];
        $o = self::publishAt( array( 'parent_node_id' => $parent, 'class_identifier' => $this->settings->ticketClass(),
                                     'creator_id' => $userId, 'attributes' => $attributes ), $created );
        if ( !$o instanceof eZContentObject || !$o->attribute( 'main_node_id' ) )
        {
            $db->rollback();
            return null;
        }
        $t = ticketsystemTicket::create( array(
            'contentobject_id' => (int)$o->attribute( 'id' ), 'node_id' => (int)$o->attribute( 'main_node_id' ),
            'number' => $number, 'prefix' => $prefix, 'year' => $year, 'seq' => $seq, 'account_key' => $accountKey, 'opener_id' => $userId,
            'subject' => $subject, 'status' => $status, 'category' => $this->settings->defaultCategory(), 'priority' => $priority,
            'priority_rank' => $this->settings->priorityRank( $priority ), 'post_count' => 1, 'last_post' => $closed, 'last_post_by' => $userId,
            'created' => $created, 'modified' => $closed, 'resolved' => $closed, 'closed' => $closed,
            'source' => self::SOURCE, 'external_id' => 'whmcs:' . $w['tid'] ) );
        $t->store();
        ticketsystemService::indexPost( $t, $o, $userId, false, $body );
        $this->remember( 'ticket', $w['id'], (int)$t->attribute( 'id' ) );
        $db->commit();
        ticketsystemService::event( $t, 0, 'import', '', 'whmcs:' . $w['tid'] . ( isset( $departments[$w['department']] ) ? ' (' . $departments[$w['department']] . ')' : '' ) );
        eZContentObject::clearCache( array( (int)$o->attribute( 'id' ) ) );
        return $t;
    }

    protected function addReplies( ticketsystemTicket $t, array $replies )
    {
        foreach ( $replies as $r )
        {
            if ( self::mapped( 'reply', $r['id'] ) )
                continue;
            $staff = !empty( $r['staff'] );
            $author = $staff ? $this->staffUser() : (int)$t->attribute( 'opener_id' );
            $when = self::time( $r['date'] ) ?: (int)$t->attribute( 'created' );
            $count = (int)$t->attribute( 'post_count' ) + 1;
            $body = self::text( $r['message'] ) ?: '(no text)';
            $db = eZDB::instance();
            $db->begin();
            $p = self::publishAt( array( 'parent_node_id' => (int)$t->attribute( 'node_id' ), 'class_identifier' => $this->settings->postClass(),
                                         'creator_id' => $author,
                                         'attributes' => array( 'name' => sprintf( 'Re: %s (#%d)', (string)$t->attribute( 'subject' ), $count ),
                                                                'comment' => ticketsystemText::toXml( $body ) ) ), $when );
            if ( !$p instanceof eZContentObject )
            {
                $db->rollback();
                $this->counts['failed']++;
                continue;
            }
            $t->setAttribute( 'post_count', $count );
            if ( $when >= (int)$t->attribute( 'last_post' ) )
            {
                $t->setAttribute( 'last_post', $when );
                $t->setAttribute( 'last_post_by', $author );
                $t->setAttribute( 'last_post_staff', $staff ? 1 : 0 );
            }
            if ( $staff && ( !(int)$t->attribute( 'responded' ) || $when < (int)$t->attribute( 'responded' ) ) )
                $t->setAttribute( 'responded', $when );
            $t->store();
            ticketsystemService::indexPost( $t, $p, $author, $staff, $body );
            $this->remember( 'reply', $r['id'], (int)$p->attribute( 'id' ) );
            $db->commit();
        }
    }

    protected function addNotes( ticketsystemTicket $t, array $notes )
    {
        foreach ( $notes as $n )
        {
            if ( self::mapped( 'note', $n['id'] ) )
                continue;
            $note = ticketsystemNote::make( array( 'ticket_id' => (int)$t->attribute( 'id' ), 'author_id' => $this->staffUser(),
                                                   'body' => self::text( $n['message'] ) ?: '(no text)', 'created' => self::time( $n['date'] ) ?: time() ) );
            $note->store();
            $this->remember( 'note', $n['id'], (int)$note->attribute( 'id' ) );
        }
        $t->setAttribute( 'note_count', ticketsystemNote::countBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ) ) );
        $t->store();
    }

    /** The counts of what was imported before (the administration's import page). */
    public static function imported()
    {
        $out = array();
        foreach ( array( 'ticket', 'reply', 'note' ) as $k )
            $out[$k] = ticketsystemImportMap::countBy( array( 'source' => self::SOURCE, 'kind' => $k ) );
        return $out;
    }
}
