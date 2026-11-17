<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The import of WHMCS support tickets as history (doc/import-whmcs.md), from the JSON of bin/php/whmcs_export.php.
 *
 * - Idempotent: every ticket, reply and note is recorded in ticketsystem_import (ticketsystemImportMap) and never
 *   made twice; a later run adds only what is new (a reply written in WHMCS after the first run).
 * - Dry run by default: counts what it would do and changes nothing.
 * - Silent: posts are published without the kernel's notification and without ticketsystemMailer, nobody is
 *   subscribed to the ticket, so no mail and no notification leaves while importing.
 * - The customer: by the WHMCS client id only, never by an e-mail address. [ImportSettings] ClientResolver=katalyst
 *   finds the Katalyst customer linked to that client (katalyst_customer_ref, source whmcs) and its user; a client map
 *   (WHMCS client id => user id) can be given instead or on top. A ticket of a client without a customer or user here,
 *   and a guest ticket (no client), is skipped and counted.
 * - The ticket: a "ticket" object below the opener's user node, owned by the opener and dated as in WHMCS; a number of
 *   the year it was opened (7X-2024-00001); the status of [ImportSettings] StatusMap (default: the status set's Closed,
 *   decision 33: closed history); the department as category (DepartmentMap, else the category of the same name);
 *   the urgency as priority. The opening post ends with a line naming the WHMCS ticket, its department and status.
 * - Replies: "ticket_comment" objects; a client's reply as the opener, a staff reply as the user of
 *   [ImportSettings] StaffUserMap[<WHMCS admin id>], else StaffUserID (a named import user).
 * - Notes: internal notes when the help desk has them (a class ticketsystemNote with make()/store()); else counted
 *   as notes_waiting and imported by a later run.
 * - Files: WHMCS keeps attachments in its own folder; they are counted (files_not_copied), not copied.
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
    /** @var array WHMCS client id => array( user id, account key, skip reason ) */
    protected $openers = array();

    /** @var array */
    public $counts = array();

    public function __construct( $dryRun = true, array $clientMap = array(), ?ticketsystemSettings $settings = null )
    {
        $this->dryRun = (bool)$dryRun;
        $this->clientMap = array_map( 'intval', $clientMap );
        $this->settings = $settings ?: ticketsystemSettings::instance();
        $this->counts = self::emptyCounts();
    }

    public static function emptyCounts()
    {
        return array( 'tickets' => 0, 'new_tickets' => 0, 'already' => 0, 'skipped_guest' => 0, 'skipped_no_customer' => 0,
                      'skipped_no_user' => 0, 'new_replies' => 0, 'new_notes' => 0, 'notes_waiting' => 0, 'files_not_copied' => 0,
                      'failed' => 0 );
    }

    /** Plain text of a WHMCS message (HTML and entities out, paragraphs kept; pure). */
    public static function text( $message )
    {
        $m = str_replace( array( "\r\n", "\r" ), "\n", (string)$message );
        if ( preg_match( '#<(br|p|div)\b#i', $m ) )
        {
            $m = preg_replace( '#<br\s*/?>#i', "\n", $m );
            $m = preg_replace( '#</(p|div)>#i', "\n\n", $m );
        }
        $m = html_entity_decode( strip_tags( $m ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $m = preg_replace( "/[ \t]+\n/", "\n", $m );
        return trim( preg_replace( "/\n{3,}/", "\n\n", $m ) );
    }

    /** A WHMCS date ("2024-05-11 14:03:29") as a Unix time (0 for none or "0000-00-00 ..."; pure). */
    public static function time( $date )
    {
        $date = trim( (string)$date );
        if ( $date === '' || strpos( $date, '0000-00-00' ) === 0 )
            return 0;
        $t = strtotime( $date );
        return $t && $t > 0 ? (int)$t : 0;
    }

    /** The status key of a WHMCS status: [ImportSettings] StatusMap, else the set's Closed (pure apart from settings). */
    public function status( $whmcsStatus )
    {
        $set = $this->settings->statusSet();
        $map = (array)$this->settings->value( 'ImportSettings', 'StatusMap', array() );
        $k = trim( (string)$whmcsStatus );
        foreach ( $map as $from => $to )
            if ( strcasecmp( (string)$from, $k ) === 0 && isset( $set->statuses[(string)$to] ) )
                return (string)$to;
        return $set->role( 'Closed' );
    }

    /** The priority key of a WHMCS urgency (Low, Medium, High; anything else the default). */
    public function priority( $urgency )
    {
        $u = strtolower( trim( (string)$urgency ) );
        return isset( $this->settings->priorities()[$u] ) ? $u : $this->settings->defaultPriority();
    }

    /** The category key of a WHMCS department: DepartmentMap[<id>], else the category of the same name, else the default. */
    public function category( $departmentId, $departmentName )
    {
        $cats = $this->settings->categories();
        $map = (array)$this->settings->value( 'ImportSettings', 'DepartmentMap', array() );
        if ( isset( $map[(string)(int)$departmentId] ) && isset( $cats[(string)$map[(string)(int)$departmentId]] ) )
            return (string)$map[(string)(int)$departmentId];
        foreach ( $cats as $key => $label )
            if ( strcasecmp( trim( (string)$label ), trim( (string)$departmentName ) ) === 0 || strcasecmp( (string)$key, trim( (string)$departmentName ) ) === 0 )
                return (string)$key;
        return $this->settings->defaultCategory();
    }

    /** The user a staff reply or note is posted as. */
    public function staffUser( $adminId )
    {
        $map = (array)$this->settings->value( 'ImportSettings', 'StaffUserMap', array() );
        if ( (int)$adminId > 0 && isset( $map[(string)(int)$adminId] ) && (int)$map[(string)(int)$adminId] > 0 )
            return (int)$map[(string)(int)$adminId];
        $id = (int)$this->settings->value( 'ImportSettings', 'StaffUserID', 0 ) ?: (int)$this->settings->value( 'ImportSettings', 'ImportUserID', 0 );
        return $id > 0 ? $id : 14;
    }

    /**
     * The user (content object id) and account key of a WHMCS client, or the reason it has none.
     *
     * @return array( user id, account key, skip reason or '' )
     */
    public function opener( $clientId )
    {
        $clientId = (int)$clientId;
        if ( isset( $this->openers[$clientId] ) )
            return $this->openers[$clientId];
        return $this->openers[$clientId] = $this->findOpener( $clientId );
    }

    protected function findOpener( $clientId )
    {
        if ( $clientId <= 0 )
            return array( 0, '', 'skipped_guest' );
        if ( isset( $this->clientMap[$clientId] ) && $this->clientMap[$clientId] > 0 )
        {
            $uid = $this->clientMap[$clientId];
            if ( !eZUser::fetch( $uid ) )
                return array( 0, '', 'skipped_no_user' );
            $account = new ticketsystemAccount();
            return array( $uid, $account->keyFor( $uid ), '' );
        }
        if ( (string)$this->settings->value( 'ImportSettings', 'ClientResolver', 'katalyst' ) !== 'katalyst'
             || !class_exists( 'expKatalystCustomerRef' ) || !class_exists( 'expKatalystCustomer' ) )
            return array( 0, '', 'skipped_no_customer' );
        $customerId = 0;
        foreach ( (array)eZPersistentObject::fetchObjectList( expKatalystCustomerRef::definition(), null,
                                                               array( 'source' => 'whmcs', 'external_id' => (string)$clientId ), null, null, false ) as $r )
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

    /** Whether internal notes can be stored (the help desk's notes, once they are there). */
    public static function notesAvailable()
    {
        return class_exists( 'ticketsystemNote' ) && method_exists( 'ticketsystemNote', 'make' );
    }

    /**
     * Imports (or, in a dry run, counts) the tickets of an export.
     *
     * @param array $export the decoded JSON of whmcs_export.php
     * @return array the counts
     */
    public function run( array $export )
    {
        $this->counts = self::emptyCounts();
        $departments = isset( $export['departments'] ) ? (array)$export['departments'] : array();
        foreach ( isset( $export['tickets'] ) ? (array)$export['tickets'] : array() as $t )
        {
            $this->counts['tickets']++;
            try
            {
                $this->one( self::normalize( (array)$t ), $departments );
            }
            catch ( Throwable $e )
            {
                $this->counts['failed']++;
                eZDebug::writeError( 'WHMCS ticket ' . (int)( isset( $t['id'] ) ? $t['id'] : 0 ) . ': ' . $e->getMessage(), __METHOD__ );
                if ( eZDB::instance()->transactionCounter() > 0 )
                    eZDB::instance()->rollback();
            }
        }
        eZLog::write( sprintf( 'WHMCS import (%s): %s', $this->dryRun ? 'dry run' : 'executed',
                               http_build_query( $this->counts, '', ', ' ) ), 'ticketsystem.log' );
        return $this->counts;
    }

    /** A ticket of the export with every key there (pure). */
    public static function normalize( array $t )
    {
        $t += array( 'id' => 0, 'tid' => '', 'department' => 0, 'userid' => 0, 'date' => '', 'subject' => '', 'message' => '', 'status' => '',
                     'urgency' => '', 'lastreply' => '', 'merged_into' => 0, 'files' => 0, 'replies' => array(), 'notes' => array() );
        foreach ( $t['replies'] as $i => $r )
            $t['replies'][$i] = (array)$r + array( 'id' => 0, 'staff' => false, 'admin_id' => 0, 'date' => '', 'message' => '', 'files' => 0 );
        foreach ( $t['notes'] as $i => $n )
            $t['notes'][$i] = (array)$n + array( 'id' => 0, 'admin_id' => 0, 'date' => '', 'message' => '', 'files' => 0 );
        return $t;
    }

    protected function one( array $w, array $departments )
    {
        $map = ticketsystemImportMap::find( self::SOURCE, 'ticket', (int)$w['id'] );
        $ticket = $map ? ticketsystemTicket::fetch( (int)$map->attribute( 'local_id' ) ) : null;
        if ( !$ticket )
        {
            list( $userId, $accountKey, $skip ) = $this->opener( $w['userid'] );
            if ( $skip !== '' )
            {
                $this->counts[$skip]++;
                return;
            }
            $this->counts['new_tickets']++;
            $this->counts['files_not_copied'] += (int)$w['files'];
            if ( $this->dryRun )
            {
                $this->countNew( null, $w );
                return;
            }
            $ticket = $this->createTicket( $w, $userId, $accountKey, $departments );
            if ( !$ticket )
            {
                $this->counts['new_tickets']--;
                $this->counts['failed']++;
                return;
            }
        }
        else
            $this->counts['already']++;
        if ( $this->dryRun )
        {
            $this->countNew( $ticket, $w );
            return;
        }
        $this->addReplies( $ticket, $w['replies'] );
        $this->addNotes( $ticket, $w['notes'] );
    }

    /** Counts the replies and notes a run would add. */
    protected function countNew( $ticket, array $w )
    {
        foreach ( $w['replies'] as $r )
            if ( !$ticket || !ticketsystemImportMap::find( self::SOURCE, 'reply', (int)$r['id'] ) )
            {
                $this->counts['new_replies']++;
                $this->counts['files_not_copied'] += (int)$r['files'];
            }
        foreach ( $w['notes'] as $n )
            if ( !$ticket || !ticketsystemImportMap::find( self::SOURCE, 'note', (int)$n['id'] ) )
                $this->counts[self::notesAvailable() ? 'new_notes' : 'notes_waiting']++;
    }

    /** Publishes an object without notification, dated $time. */
    protected static function publishAt( array $params, $time )
    {
        // the help desk's own publication: without the kernel's notification, the workflow event stands aside
        $o = ticketsystemService::publishNew( $params );
        if ( $o instanceof eZContentObject && $time > 0 )
        {
            $o->setAttribute( 'published', (int)$time );
            $o->setAttribute( 'modified', (int)$time );
            $o->store();
            $v = $o->currentVersion();
            if ( $v )
            {
                $v->setAttribute( 'created', (int)$time );
                $v->setAttribute( 'modified', (int)$time );
                $v->store();
            }
        }
        return $o;
    }

    /** The class attributes of a class (identifier => datatype). */
    protected static function classAttributes( $classIdentifier )
    {
        $class = eZContentClass::fetchByIdentifier( $classIdentifier );
        $out = array();
        if ( $class )
            foreach ( $class->fetchAttributes() as $a )
                $out[$a->attribute( 'identifier' )] = $a->attribute( 'data_type_string' );
        return $out;
    }

    /** The line that ends an imported opening post (pure). */
    public static function originLine( array $w, $departmentName, $statusLabel )
    {
        $parts = array_filter( array( trim( (string)$departmentName ), trim( (string)$w['status'] ) ), 'strlen' );
        return sprintf( '(Imported from WHMCS ticket #%s%s.)', (string)$w['tid'] !== '' ? $w['tid'] : $w['id'],
                        $parts ? ', ' . implode( ', ', $parts ) : '' );
    }

    protected function createTicket( array $w, $userId, $accountKey, array $departments )
    {
        $user = eZUser::fetch( $userId );
        $object = $user ? $user->contentObject() : null;
        $parent = $object ? (int)$object->attribute( 'main_node_id' ) : 0;
        if ( $this->settings->value( 'TicketSettings', 'Placement', 'user' ) === 'node' )
            $parent = (int)$this->settings->value( 'TicketSettings', 'TicketNodeID', 0 );
        if ( $parent <= 0 )
            return null;
        $created = self::time( $w['date'] ) ?: time();
        $last = max( $created, self::time( $w['lastreply'] ) );
        foreach ( $w['replies'] as $r )
            $last = max( $last, self::time( $r['date'] ) );
        $set = $this->settings->statusSet();
        $status = $this->status( $w['status'] );
        $department = isset( $departments[(string)(int)$w['department']] ) ? (string)$departments[(string)(int)$w['department']] : '';
        $category = $this->category( $w['department'], $department );
        $priority = $this->priority( $w['urgency'] );
        $subject = mb_substr( trim( preg_replace( '/\s+/u', ' ', self::text( $w['subject'] ) ) ), 0, 200 );
        if ( $subject === '' )
            $subject = 'WHMCS ticket ' . ( $w['tid'] !== '' ? $w['tid'] : $w['id'] );
        $body = self::text( $w['message'] );
        $body = ( $body !== '' ? $body . "\n\n" : '' ) . self::originLine( $w, $department, $set->label( $status ) );
        $have = self::classAttributes( $this->settings->ticketClass() );
        if ( !$have )
            return null;

        $db = eZDB::instance();
        $db->begin();
        list( $number, $prefix, $year, $seq ) = ticketsystemNumber::take( $this->settings, $created );
        $attributes = array( 'name' => $subject, 'description' => ticketsystemText::toXml( $body ) );
        if ( isset( $have['ticket_number'] ) )
            $attributes['ticket_number'] = $number;
        if ( isset( $have['state'] ) )
            $attributes['state'] = $set->label( $status );
        if ( isset( $have['category'] ) )
            $attributes['category'] = $this->settings->categories()[$category];
        if ( isset( $have['priority'] ) )
            $attributes['priority'] = $this->settings->priorities()[$priority];
        $o = self::publishAt( array( 'parent_node_id' => $parent, 'class_identifier' => $this->settings->ticketClass(),
                                     'creator_id' => $userId, 'attributes' => $attributes ), $created );
        if ( !$o instanceof eZContentObject || !$o->attribute( 'main_node_id' ) )
        {
            $db->rollback();
            return null;
        }
        $done = $set->isDone( $status ) ? $last : 0;
        $t = ticketsystemTicket::create( array(
            'contentobject_id' => (int)$o->attribute( 'id' ), 'node_id' => (int)$o->attribute( 'main_node_id' ),
            'number' => $number, 'prefix' => $prefix, 'year' => $year, 'seq' => $seq, 'account_key' => $accountKey,
            'opener_id' => $userId, 'subject' => $subject, 'status' => $status, 'category' => $category, 'priority' => $priority,
            'subscription_id' => 0, 'post_count' => 1, 'last_post' => $created, 'last_post_by' => $userId, 'last_post_staff' => 0,
            'created' => $created, 'modified' => $last, 'resolved' => $done, 'closed' => $set->isClosed( $status ) ? $last : 0 )
            + self::known( array( 'source' => self::SOURCE, 'external_id' => 'whmcs:' . ( $w['tid'] !== '' ? $w['tid'] : $w['id'] ),
                                  'priority_rank' => method_exists( $this->settings, 'priorityRank' ) ? $this->settings->priorityRank( $priority ) : 0 ) ) );
        $t->store();
        if ( method_exists( 'ticketsystemService', 'indexPost' ) )
            ticketsystemService::indexPost( $t, $o, $userId, false, $body );
        ticketsystemImportMap::remember( self::SOURCE, 'ticket', (int)$w['id'], (int)$t->attribute( 'id' ) );
        $db->commit();
        if ( method_exists( 'ticketsystemService', 'event' ) )
            ticketsystemService::event( $t, 0, 'import', '', 'whmcs:' . ( $w['tid'] !== '' ? $w['tid'] : $w['id'] ) );
        eZContentObject::clearCache( array( (int)$o->attribute( 'id' ) ) );
        return $t;
    }

    /** The values of columns the ticket table has (a help desk without them leaves them out). */
    protected static function known( array $values )
    {
        return array_intersect_key( $values, ticketsystemTicket::definition()['fields'] );
    }

    protected function addReplies( ticketsystemTicket $t, array $replies )
    {
        foreach ( $replies as $r )
        {
            if ( ticketsystemImportMap::find( self::SOURCE, 'reply', (int)$r['id'] ) )
                continue;
            $staff = !empty( $r['staff'] );
            $author = $staff ? $this->staffUser( $r['admin_id'] ) : (int)$t->attribute( 'opener_id' );
            $when = self::time( $r['date'] ) ?: (int)$t->attribute( 'created' );
            $count = (int)$t->attribute( 'post_count' ) + 1;
            $body = self::text( $r['message'] );
            if ( $body === '' )
                $body = '(no text)';
            $db = eZDB::instance();
            $db->begin();
            $p = self::publishAt( array( 'parent_node_id' => (int)$t->attribute( 'node_id' ), 'class_identifier' => $this->settings->postClass(),
                                         'creator_id' => $author,
                                         'attributes' => array( 'name' => sprintf( 'Re: %s (#%d)', (string)$t->attribute( 'subject' ), $count ),
                                                                'comment' => ticketsystemText::toXml( $body ) ) ), $when );
            if ( !$p instanceof eZContentObject || !$p->attribute( 'main_node_id' ) )
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
            // the first staff answer (the SLA's first response), when the help desk keeps it
            if ( $staff && self::known( array( 'responded' => 1 ) ) && ( !(int)$t->attribute( 'responded' ) || $when < (int)$t->attribute( 'responded' ) ) )
                $t->setAttribute( 'responded', $when );
            $t->store();
            if ( method_exists( 'ticketsystemService', 'indexPost' ) )
                ticketsystemService::indexPost( $t, $p, $author, $staff, $body );
            ticketsystemImportMap::remember( self::SOURCE, 'reply', (int)$r['id'], (int)$p->attribute( 'id' ) );
            $db->commit();
            $this->counts['new_replies']++;
            $this->counts['files_not_copied'] += (int)$r['files'];
            eZContentObject::clearCache( array( (int)$p->attribute( 'id' ) ) );
        }
    }

    protected function addNotes( ticketsystemTicket $t, array $notes )
    {
        foreach ( $notes as $n )
        {
            if ( ticketsystemImportMap::find( self::SOURCE, 'note', (int)$n['id'] ) )
                continue;
            if ( !self::notesAvailable() )
            {
                $this->counts['notes_waiting']++;
                continue;
            }
            $db = eZDB::instance();
            $db->begin();
            $note = ticketsystemNote::make( array( 'ticket_id' => (int)$t->attribute( 'id' ), 'author_id' => $this->staffUser( $n['admin_id'] ),
                                                   'body' => self::text( $n['message'] ) ?: '(no text)',
                                                   'created' => self::time( $n['date'] ) ?: time() ) );
            $note->store();
            ticketsystemImportMap::remember( self::SOURCE, 'note', (int)$n['id'], (int)$note->attribute( 'id' ) );
            $db->commit();
            $this->counts['new_notes']++;
        }
        if ( self::notesAvailable() && self::known( array( 'note_count' => 1 ) ) )
        {
            $t->setAttribute( 'note_count', ticketsystemNote::countBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ) ) );
            $t->store();
        }
    }

    /** What was imported so far: ticket, reply, note => count (the settings page and the import page). */
    public static function imported()
    {
        return ticketsystemImportMap::counts( self::SOURCE );
    }

    // ------------------------------------------------------------------------------- the export file

    /** The path of the export file the administration page reads ([ImportSettings] ExportFile, relative to the root). */
    public static function exportFile( ?ticketsystemSettings $settings = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        $f = trim( (string)$settings->value( 'ImportSettings', 'ExportFile', 'var/ticketsystem/whmcs-tickets.json' ) );
        if ( $f === '' )
            return '';
        return $f[0] === '/' ? $f : eZSys::rootDir() . '/' . $f;
    }

    /**
     * Reads an export.
     *
     * @return array( export or null, error message or '' )
     */
    public static function readExport( $file )
    {
        if ( $file === '' || !is_file( $file ) || !is_readable( $file ) )
            return array( null, 'There is no WHMCS export to import yet.' );
        $export = json_decode( (string)file_get_contents( $file ), true );
        return self::checkExport( $export );
    }

    /** @return array( export or null, error message or '' ) (pure) */
    public static function checkExport( $export )
    {
        if ( !is_array( $export ) || !isset( $export['source'] ) || $export['source'] !== self::SOURCE )
            return array( null, 'The file is no WHMCS ticket export.' );
        if ( !isset( $export['tickets'] ) || !is_array( $export['tickets'] ) )
            return array( null, 'The export has counts only (made with --counts); it cannot be imported.' );
        return array( $export, '' );
    }

    /** Stores an uploaded export as the export file (private: 0600). */
    public static function storeExport( $content, ?ticketsystemSettings $settings = null )
    {
        list( $export, $error ) = self::checkExport( json_decode( (string)$content, true ) );
        if ( !$export )
            return $error;
        $file = self::exportFile( $settings );
        if ( $file === '' )
            return 'No export file is set ([ImportSettings] ExportFile).';
        if ( !is_dir( dirname( $file ) ) )
            eZDir::mkdir( dirname( $file ), 0750, true );
        if ( file_put_contents( $file, (string)$content, LOCK_EX ) === false )
            return 'The export could not be stored.';
        @chmod( $file, 0600 );
        return '';
    }
}
