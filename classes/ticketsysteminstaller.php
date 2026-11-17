<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Installs and updates what the help desk needs (bin/php/install.php, doc/install.md). Idempotent: every step looks
 * first and changes only what is missing; a dry run reports only.
 *
 * - the tables ticketsystem_ticket and ticketsystem_counter (sql/<database>/schema.sql);
 * - the classes ticketsystem, ticket, ticket_comment and ticket_attachment in the class group "Help desk". Existing
 *   classes (from the ticketsystem 1.x package) are kept; only missing attributes are added (ticket_number,
 *   subscription);
 * - the user group "Technical Support Group" below the Users root, the role "Ticket Support" assigned to it, and
 *   the role "Ticket Customer" assigned to the customer groups given.
 */
class ticketsystemInstaller
{
    const CLASS_GROUP = 'Help desk';
    const STAFF_ROLE = 'Ticket Support';
    const CUSTOMER_ROLE = 'Ticket Customer';
    const STAFF_GROUP = 'Technical Support Group';

    /** @var bool */
    protected $dryRun;
    /** @var string[] */
    public $lines = array();
    /** @var bool */
    public $ok = true;
    /** @var ticketsystemSettings */
    protected $settings;
    /** @var int */
    protected $userId;
    /** @var string */
    protected $language;

    public function __construct( $dryRun = true, ?ticketsystemSettings $settings = null )
    {
        $this->dryRun = (bool)$dryRun;
        $this->settings = $settings ?: ticketsystemSettings::instance();
        $this->userId = (int)eZINI::instance()->variable( 'UserSettings', 'AnonymousUserID' ) === (int)eZUser::currentUserID() ? 14 : (int)eZUser::currentUserID();
        $this->language = (string)eZContentObject::defaultLanguage();
    }

    protected function line( $text, $change = false )
    {
        $this->lines[] = ( $change ? ( $this->dryRun ? 'WOULD ' : 'DONE  ' ) : 'OK    ' ) . $text;
    }

    protected function fail( $text )
    {
        $this->ok = false;
        $this->lines[] = 'FAIL  ' . $text;
    }

    // ------------------------------------------------------------------------------------------------- tables

    public function tables()
    {
        $db = eZDB::instance();
        $engine = ticketsystemSchema::engineOf( $db );
        if ( $engine === '' )
        {
            $this->fail( 'no schema for the database type ' . $db->databaseName() . ' (create the tables of sql/mysql/schema.sql by hand)' );
            return;
        }
        // the tables that existed before this run; missing ones are created by the kernel's schema handler of the
        // database from ticketsystemSchema::definition() (doc/databases.md)
        $have = ticketsystemSchema::existingTables( $db );
        $missing = array();
        foreach ( ticketsystemSchema::TABLES as $table )
        {
            if ( !in_array( $table, $have, true ) )
            {
                $this->line( "create table $table", true );
                $missing[] = $table;
                continue;
            }
            $this->line( "table $table exists" );
            // a table of an older release: the columns (and their indexes) it lacks
            $done = array();
            foreach ( ticketsystemSchema::missingColumns( $table, $db ) as $column => $statements )
            {
                $this->line( "table $table: add column $column", true );
                if ( $this->dryRun )
                    continue;
                foreach ( $statements as $i => $sql )
                {
                    if ( isset( $done[$sql] ) )
                        continue;
                    $done[$sql] = true;
                    if ( !ticketsystemSchema::tryQuery( $db, $sql ) )
                    {
                        if ( $i === 0 )
                            $this->fail( "adding $table.$column" );
                        else
                            $this->lines[] = "NOTE  $table: an index of $column was not added (it may exist under another name)";
                    }
                }
            }
        }
        if ( $missing && !$this->dryRun )
        {
            // SQLite: inside a transaction a statement waits for the site's writer instead of failing at once
            if ( $engine === 'sqlite' )
                $db->begin();
            $errors = ticketsystemSchema::create( $missing, $db );
            if ( $engine === 'sqlite' )
                $db->commit();
            foreach ( $errors as $error )
                $this->fail( 'creating the tables: ' . $error );
            $left = array_diff( $missing, ticketsystemSchema::existingTables( $db ) );
            if ( $left && $this->ok )
                $this->fail( 'not created: ' . implode( ', ', $left ) );
        }
    }

    /** The post index of the staff search for tickets posted before 1.1 (idempotent; a few hundred tickets a run). */
    public function postIndex( $limit = 500 )
    {
        $settings = $this->settings;
        $n = 0;
        foreach ( ticketsystemTicket::fetchWhere( '', 0, (int)$limit, 'id ASC' ) as $t )
        {
            $object = $t->contentObject();
            if ( !$object || ticketsystemPostIndex::countBy( array( 'object_id' => (int)$object->attribute( 'id' ) ) ) )
                continue;
            $n++;
            if ( $this->dryRun )
                continue;
            $map = $object->dataMap();
            $text = isset( $map['description'] ) ? implode( "\n\n", ticketsystemText::fromXml( $map['description']->attribute( 'data_text' ) ) ) : '';
            ticketsystemService::indexPost( $t, $object, (int)$t->attribute( 'opener_id' ), false, $text );
            foreach ( (array)eZContentObjectTreeNode::subTreeByNodeID( array( 'Depth' => 1, 'ClassFilterType' => 'include',
                          'ClassFilterArray' => array( $settings->postClass() ), 'Limitation' => array(), 'IgnoreVisibility' => true ),
                          (int)$t->attribute( 'node_id' ) ) as $child )
            {
                $o = $child->attribute( 'object' );
                $m = $o->dataMap();
                $owner = (int)$o->attribute( 'owner_id' );
                $u = eZUser::fetch( $owner );
                $staff = $u instanceof eZUser && $owner !== (int)$t->attribute( 'opener_id' ) && ( new ticketsystemAccess( $u ) )->isStaff();
                ticketsystemService::indexPost( $t, $o, $owner, $staff, isset( $m['comment'] ) ? implode( "\n\n", ticketsystemText::fromXml( $m['comment']->attribute( 'data_text' ) ) ) : '' );
            }
            if ( !(int)$t->attribute( 'priority_rank' ) )
            {
                $t->setAttribute( 'priority_rank', $settings->priorityRank( $t->attribute( 'priority' ) ) );
                $t->store();
            }
        }
        $this->line( $n ? "index the posts of $n ticket(s) for the staff search" : 'the posts of every ticket are in the search index', $n > 0 );
    }

    // ------------------------------------------------------------------------------------------------ classes

    /** The attribute definitions of the four classes (identifier => definition). */
    public function classDefinitions()
    {
        $s = function ( $name, $required = false, $len = 200 ) { return array( 'name' => $name, 'type' => 'ezstring', 'required' => $required, 'searchable' => true, 'params' => array( 'max_length' => $len ) ); };
        $sel = function ( $name, array $options, $required = false ) { return array( 'name' => $name, 'type' => 'ezselection', 'required' => $required, 'searchable' => true, 'params' => array( 'options' => array_values( $options ) ) ); };
        $x = function ( $name, $required = false ) { return array( 'name' => $name, 'type' => 'ezxmltext', 'required' => $required, 'searchable' => true, 'params' => array() ); };
        $t = function ( $type, $name, $required = false ) { return array( 'name' => $name, 'type' => $type, 'required' => $required, 'searchable' => false, 'params' => array() ); };
        return array(
            'ticketsystem' => array( 'name' => 'Ticketsystem', 'pattern' => '<name>', 'container' => 1,
                'description' => 'A folder of tickets (the list of ticketsystem/tickets).',
                'attributes' => array( 'name' => $s( 'Name', true ), 'description' => $x( 'Description' ) ) ),
            'ticket' => array( 'name' => 'Ticket', 'pattern' => '<name>', 'container' => 1,
                'description' => 'A help desk ticket; its replies are ticket_comment objects below it.',
                'attributes' => array(
                    'name' => $s( 'Subject', true ),
                    'ticket_number' => $s( 'Ticket number', false, 32 ),
                    'category' => $sel( 'Category', $this->settings->categories() ),
                    'type' => $sel( 'Type', array( 'Bug / Failure', 'Enhancement', 'Suggestion', 'Question' ) ),
                    'description' => $x( 'Description', true ),
                    'project' => $t( 'ezobjectrelation', 'Related to project' ),
                    'priority' => $sel( 'Priority', $this->settings->priorities() ),
                    'state' => $sel( 'Status', $this->settings->statusSet()->optionLabels() ),
                    'assigned' => $t( 'ezobjectrelation', 'Assigned to' ),
                    'deadline' => $t( 'ezdate', 'Deadline' ),
                    'subscription' => $t( 'ezinteger', 'Site or subscription' ),
                ) ),
            'ticket_comment' => array( 'name' => 'Ticket Comment', 'pattern' => '<name>', 'container' => 0,
                'description' => 'A reply to a ticket.',
                'attributes' => array( 'name' => $s( 'Name', true ), 'comment' => $x( 'Comment', true ) ) ),
            'ticket_attachment' => array( 'name' => 'Ticket attachment', 'pattern' => '<name>', 'container' => 0,
                'description' => 'A file attached to a ticket.',
                'attributes' => array( 'name' => $s( 'Name', true ), 'file' => $t( 'ezbinaryfile', 'File', true ) ) ),
        );
    }

    /** The ezselection class XML of option labels (ids 0..n-1). */
    public static function selectionXml( array $options )
    {
        $doc = new DOMDocument( '1.0', 'utf-8' );
        $root = $doc->createElement( 'ezselection' );
        $doc->appendChild( $root );
        $list = $doc->createElement( 'options' );
        $root->appendChild( $list );
        $i = 0;
        foreach ( $options as $name )
        {
            $node = $doc->createElement( 'option' );
            $node->setAttribute( 'id', (string)$i++ );
            $node->setAttribute( 'name', (string)$name );
            $list->appendChild( $node );
        }
        return $doc->saveXML();
    }

    protected function createAttribute( eZContentClass $class, $identifier, array $def, $placement, $initializeObjects )
    {
        $values = array(
            'version' => eZContentClass::VERSION_STATUS_DEFINED, 'identifier' => $identifier, 'name' => $def['name'],
            'placement' => $placement, 'is_required' => $def['required'] ? 1 : 0, 'is_searchable' => $def['searchable'] ? 1 : 0,
            'can_translate' => 1, 'is_information_collector' => 0,
        );
        if ( $def['type'] === 'ezstring' )
            $values['data_int1'] = (int)$def['params']['max_length'];
        if ( $def['type'] === 'ezselection' )
        {
            $values['data_int1'] = 0;
            $values['data_text5'] = self::selectionXml( $def['params']['options'] );
        }
        $attribute = eZContentClassAttribute::create( (int)$class->attribute( 'id' ), $def['type'], $values, $this->language );
        $dataType = $attribute->dataType();
        if ( $dataType && $def['type'] !== 'ezselection' )
            $dataType->initializeClassAttribute( $attribute );
        $attribute->store();
        if ( $initializeObjects )
            $attribute->initializeObjectAttributes();
        return $attribute;
    }

    public function classes()
    {
        $group = eZContentClassGroup::fetchByName( self::CLASS_GROUP );
        if ( !$group )
        {
            $this->line( 'create the class group "' . self::CLASS_GROUP . '"', true );
            if ( !$this->dryRun )
            {
                $group = eZContentClassGroup::create( $this->userId );
                $group->setAttribute( 'name', self::CLASS_GROUP );
                $group->store();
            }
        }
        foreach ( $this->classDefinitions() as $identifier => $def )
        {
            $class = eZContentClass::fetchByIdentifier( $identifier );
            if ( $class )
            {
                $have = array();
                $placement = 0;
                foreach ( $class->fetchAttributes() as $a )
                {
                    $have[$a->attribute( 'identifier' )] = $a->attribute( 'data_type_string' );
                    $placement = max( $placement, (int)$a->attribute( 'placement' ) );
                }
                $missing = array_diff_key( $def['attributes'], $have );
                foreach ( $missing as $ai => $adef )
                {
                    $this->line( "class $identifier: add $ai ({$adef['type']}, optional)", true );
                    if ( $this->dryRun )
                        continue;
                    $adef['required'] = false;
                    $db = eZDB::instance();
                    $db->begin();
                    $this->createAttribute( $class, $ai, $adef, ++$placement, true );
                    $db->commit();
                }
                if ( !$missing )
                    $this->line( "class $identifier exists (" . count( $have ) . ' attributes)' );
                if ( $identifier === 'ticket' && isset( $have['state'] ) )
                    $this->checkStateOptions( $class );
                if ( (int)$def['container'] && !(int)$class->attribute( 'is_container' ) )
                {
                    $this->line( "class $identifier: make it a container (its replies live below it)", true );
                    if ( !$this->dryRun )
                    {
                        $class->setAttribute( 'is_container', 1 );
                        $class->store();
                    }
                }
                continue;
            }
            $this->line( "create class $identifier (" . count( $def['attributes'] ) . ' attributes) in "' . self::CLASS_GROUP . '"', true );
            if ( $this->dryRun )
                continue;
            $db = eZDB::instance();
            $db->begin();
            $class = eZContentClass::create( $this->userId, array(
                'version' => eZContentClass::VERSION_STATUS_DEFINED, 'identifier' => $identifier,
                'remote_id' => 'ticketsystem-class-' . $identifier, 'contentobject_name' => $def['pattern'],
                'is_container' => $def['container'], 'always_available' => 0, 'name' => $def['name'],
                'description' => $def['description'] ), $this->language );
            $class->store();
            $placement = 0;
            foreach ( $def['attributes'] as $ai => $adef )
                $this->createAttribute( $class, $ai, $adef, ++$placement, false );
            if ( $group )
                eZContentClassClassGroup::create( $class->attribute( 'id' ), eZContentClass::VERSION_STATUS_DEFINED,
                                                  $group->attribute( 'id' ), $group->attribute( 'name' ) )->store();
            $db->commit();
        }
        if ( !$this->dryRun )
            eZContentClass::expireCache();
    }

    /**
     * Completes the options of the selections state, category and priority of the class ticket with the labels of
     * the settings they lack (a class made with other settings, or by ticketsystem 1.x): new options are appended
     * with new ids, existing ones keep theirs, so the objects keep their values. Then the attributes of a ticket can
     * follow every status, queue and priority the index gets.
     */
    protected function checkStateOptions( eZContentClass $class )
    {
        $want = array( 'state' => $this->settings->statusSet()->optionLabels(), 'category' => array_values( $this->settings->categories() ),
                       'priority' => array_values( $this->settings->priorities() ) );
        foreach ( $want as $identifier => $labels )
        {
            $attr = $class->fetchAttributeByIdentifier( $identifier );
            if ( !$attr || $attr->attribute( 'data_type_string' ) !== 'ezselection' )
                continue;
            $content = $attr->content();
            $options = array();
            foreach ( isset( $content['options'] ) ? $content['options'] : array() as $o )
                $options[(int)$o['id']] = (string)$o['name'];
            $missing = array_values( array_diff( array_map( 'strval', $labels ), $options ) );
            if ( !$missing )
            {
                $this->line( "class ticket: \"$identifier\" has every option of the settings" );
                continue;
            }
            $this->line( "class ticket: add the options " . implode( ', ', $missing ) . " to \"$identifier\"", true );
            if ( $this->dryRun )
                continue;
            $next = $options ? max( array_keys( $options ) ) + 1 : 0;
            foreach ( $missing as $label )
                $options[$next++] = $label;
            $attr->setAttribute( 'data_text5', self::selectionXmlWithIds( $options ) );
            $attr->store();
        }
    }

    /** The ezselection class XML of id => label options. */
    public static function selectionXmlWithIds( array $options )
    {
        $doc = new DOMDocument( '1.0', 'utf-8' );
        $root = $doc->createElement( 'ezselection' );
        $doc->appendChild( $root );
        $list = $doc->createElement( 'options' );
        $root->appendChild( $list );
        foreach ( $options as $id => $name )
        {
            $node = $doc->createElement( 'option' );
            $node->setAttribute( 'id', (string)(int)$id );
            $node->setAttribute( 'name', (string)$name );
            $list->appendChild( $node );
        }
        return $doc->saveXML();
    }

    // ---------------------------------------------------------------------------------------------- roles

    /** The role definitions: name => policies (module, function, limitations). */
    public static function roleDefinitions( $frontSiteaccess, $adminSiteaccess )
    {
        $login = array();
        foreach ( array_filter( array( $frontSiteaccess, $adminSiteaccess ) ) as $sa )
            $login[] = (string)eZSys::ezcrc32( $sa );
        return array(
            self::CUSTOMER_ROLE => array(
                array( 'ticketsystem', 'read', array( 'Scope' => array( '2' ) ) ),
                array( 'ticketsystem', 'reply', array( 'Scope' => array( '2' ) ) ),
                array( 'ticketsystem', 'create', array() ),
            ),
            self::STAFF_ROLE => array(
                array( 'ticketsystem', 'read', array( 'Scope' => array( '3' ) ) ),
                array( 'ticketsystem', 'reply', array( 'Scope' => array( '3' ) ) ),
                array( 'ticketsystem', 'create', array() ),
                array( 'ticketsystem', 'manage', array() ),
                array( 'ticketsystem', 'report', array() ),
                array( 'ticketsystem', 'use', array() ),
                array( 'user', 'login', $login ? array( 'SiteAccess' => $login ) : array() ),
                array( 'user', 'selfedit', array() ),
                array( 'user', 'password', array() ),
                array( 'content', 'read', array( 'Section' => array( '1' ) ) ),
                array( 'notification', 'use', array() ),
            ),
        );
    }

    protected static function signature( $module, $function, array $limits )
    {
        ksort( $limits );
        $parts = array();
        foreach ( $limits as $k => $v )
        {
            $v = array_map( 'strval', (array)$v );
            sort( $v );
            $parts[] = $k . '=' . implode( ',', $v );
        }
        return "$module/$function" . ( $parts ? ' (' . implode( '; ', $parts ) . ')' : '' );
    }

    /** The user groups below the Users root: name => object id. */
    protected static function groups()
    {
        $root = (int)eZINI::instance( 'content.ini' )->variable( 'NodeSettings', 'UserRootNode' );
        $out = array();
        foreach ( (array)eZContentObjectTreeNode::subTreeByNodeID( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'user_group' ),
                                                                          'Limitation' => array(), 'IgnoreVisibility' => true ), $root ) as $n )
            $out[(string)$n->attribute( 'name' )] = (int)$n->attribute( 'contentobject_id' );
        return $out;
    }

    /** Creates a user group below a node, published without workflows; the object or null. */
    protected function createGroup( $name, $description, $parentNodeId )
    {
        $class = eZContentClass::fetchByIdentifier( 'user_group' );
        $parent = eZContentObjectTreeNode::fetch( (int)$parentNodeId );
        if ( !$class || !$parent )
            return null;
        $db = eZDB::instance();
        $db->begin();
        $object = $class->instantiate( $this->userId, (int)$parent->attribute( 'object' )->attribute( 'section_id' ) );
        $object->store();
        eZNodeAssignment::create( array( 'contentobject_id' => (int)$object->attribute( 'id' ), 'contentobject_version' => 1,
                                         'parent_node' => (int)$parentNodeId, 'is_main' => 1,
                                         'sort_field' => $class->attribute( 'sort_field' ), 'sort_order' => $class->attribute( 'sort_order' ) ) )->store();
        foreach ( $object->version( 1 )->contentObjectAttributes() as $attribute )
        {
            $identifier = $attribute->attribute( 'contentclass_attribute_identifier' );
            if ( $identifier === 'name' || $identifier === 'description' )
            {
                $attribute->fromString( $identifier === 'name' ? $name : $description );
                $attribute->store();
            }
        }
        $db->commit();
        eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => (int)$object->attribute( 'id' ), 'version' => 1 ), null, false );
        eZContentObject::clearCache( array( (int)$object->attribute( 'id' ) ) );
        $object = eZContentObject::fetch( (int)$object->attribute( 'id' ) );
        if ( $object && (int)$object->attribute( 'status' ) === eZContentObject::STATUS_PUBLISHED && $object->attribute( 'main_node_id' ) )
            return $object;
        if ( $object && (int)$object->attribute( 'status' ) === eZContentObject::STATUS_DRAFT )
            $object->purge();
        return null;
    }

    /**
     * The group "Technical Support Group" and the two roles.
     *
     * @param string[] $customerGroups names of the user groups that get "Ticket Customer"
     */
    public function roles( array $customerGroups, $frontSiteaccess, $adminSiteaccess, $staffGroup = self::STAFF_GROUP )
    {
        $groups = self::groups();
        if ( !isset( $groups[$staffGroup] ) )
        {
            $this->line( "create the user group \"$staffGroup\" below the Users root", true );
            if ( !$this->dryRun )
            {
                $o = $this->createGroup( $staffGroup, 'Support representatives: read, answer and solve every help desk ticket.',
                                         (int)eZINI::instance( 'content.ini' )->variable( 'NodeSettings', 'UserRootNode' ) );
                if ( $o )
                    $groups[$staffGroup] = (int)$o->attribute( 'id' );
                else
                    $this->fail( "creating the user group \"$staffGroup\"" );
            }
        }
        else
            $this->line( "user group \"$staffGroup\" exists (object {$groups[$staffGroup]})" );

        $assign = array( self::STAFF_ROLE => array( $staffGroup ), self::CUSTOMER_ROLE => $customerGroups );
        $changed = false;
        foreach ( self::roleDefinitions( $frontSiteaccess, $adminSiteaccess ) as $roleName => $policies )
        {
            $role = eZRole::fetchByName( $roleName );
            if ( !$role )
            {
                $this->line( "create the role \"$roleName\"", true );
                if ( !$this->dryRun )
                {
                    $role = eZRole::create( $roleName );
                    $role->store();
                    $changed = true;
                }
            }
            $have = array();
            if ( $role && $role->attribute( 'id' ) )
                foreach ( $role->policyList() as $policy )
                {
                    $lims = array();
                    foreach ( $policy->limitationList() as $l )
                        $lims[$l->attribute( 'identifier' )] = $l->attribute( 'values_as_array' );
                    $have[self::signature( $policy->attribute( 'module_name' ), $policy->attribute( 'function_name' ), $lims )] = true;
                }
            foreach ( $policies as $p )
            {
                $sig = self::signature( $p[0], $p[1], $p[2] );
                if ( isset( $have[$sig] ) )
                {
                    $this->line( "role \"$roleName\" has $sig" );
                    continue;
                }
                $this->line( "role \"$roleName\": add $sig", true );
                if ( !$this->dryRun && $role )
                {
                    $role->appendPolicy( $p[0], $p[1], $p[2] );
                    $changed = true;
                }
            }
            $assigned = array();
            if ( $role && $role->attribute( 'id' ) )
                foreach ( $role->fetchUserByRole() as $a )
                    if ( empty( $a['limit_ident'] ) && $a['user_object'] )
                        $assigned[(int)$a['user_object']->attribute( 'id' )] = true;
            foreach ( $assign[$roleName] as $groupName )
            {
                $gid = isset( $groups[$groupName] ) ? $groups[$groupName] : 0;
                if ( $gid && isset( $assigned[$gid] ) )
                {
                    $this->line( "role \"$roleName\" is assigned to \"$groupName\"" );
                    continue;
                }
                if ( !$gid && !$this->dryRun )
                {
                    $this->fail( "no user group \"$groupName\" to assign \"$roleName\" to" );
                    continue;
                }
                $this->line( "assign the role \"$roleName\" to \"$groupName\"", true );
                if ( !$this->dryRun && $role )
                {
                    $role->assignToUser( $gid );
                    $changed = true;
                }
            }
        }
        if ( $changed )
        {
            eZRole::expireCache();
            eZUser::cleanupCache();
        }
    }
}
