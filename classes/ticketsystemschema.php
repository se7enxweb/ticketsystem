<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The tables of the help desk (the index of tickets, the number counter and, since 1.1, notes, history, canned
 * replies, attachments, the post index of the search, the mail-in log and the import map), defined once in the kernel's
 * generic schema format, so the kernel's schema handler of each database creates them: MySQL, PostgreSQL, SQLite,
 * Oracle (the ezoracle extension) and MongoDB (expMongoSchema: collections with the same indexes).
 *
 * sql/mysql, sql/postgresql and sql/sqlite/schema.sql are the files of 1.0 and stay as they were; sql/oracle/schema.sql
 * and sql/mongodb/schema.json are generated from definition() (bin/php/install.php --write-schema-files) for a site
 * that creates the tables by hand.
 *
 * Oracle: "number" is an Oracle reserved word. The schema handler creates the column quoted ("NUMBER") and the
 * driver quotes the name in every statement because settings/ezoracle.ini.append.php lists it in
 * [QuotedIdentifierSettings] ColumnNames[], so eZPersistentObject and the queries stay the same on every database.
 * Every other name is no reserved word and stays within Oracle's 30 characters.
 */
class ticketsystemSchema
{
    const TABLES = array( 'ticketsystem_ticket', 'ticketsystem_counter', 'ticketsystem_note', 'ticketsystem_event', 'ticketsystem_canned',
                          'ticketsystem_attachment', 'ticketsystem_post', 'ticketsystem_mailin', 'ticketsystem_import' );

    /** @return array generic schema (without _info) */
    public static function definition()
    {
        $int = array( 'length' => 11, 'type' => 'int', 'not_null' => '1', 'default' => 0 );
        $varchar = function ( $length )
        {
            return array( 'length' => $length, 'type' => 'varchar', 'not_null' => '1', 'default' => '' );
        };
        $index = function ()
        {
            return array( 'type' => 'non-unique', 'fields' => func_get_args() );
        };
        $text = array( 'type' => 'longtext', 'default' => null );
        // a table of 1.1: an id first, then its fields; the primary key and its indexes
        $table = function ( $name, array $fields, array $indexes )
        {
            return array( 'name' => $name, 'fields' => array( 'id' => array( 'type' => 'auto_increment', 'default' => false ) ) + $fields,
                          'indexes' => array( 'PRIMARY' => array( 'type' => 'primary', 'fields' => array( 'id' ) ) ) + $indexes );
        };
        return array(
            'ticketsystem_ticket' => array(
                'name' => 'ticketsystem_ticket',
                'fields' => array(
                    'id' => array( 'type' => 'auto_increment', 'default' => false ),
                    'contentobject_id' => $int,
                    'node_id' => $int,
                    'number' => $varchar( 32 ),
                    'prefix' => $varchar( 16 ),
                    'year' => $int,
                    'seq' => $int,
                    'account_key' => $varchar( 64 ),
                    'opener_id' => $int,
                    'subject' => $varchar( 255 ),
                    'status' => $varchar( 32 ),
                    'category' => $varchar( 64 ),
                    'priority' => $varchar( 32 ),
                    'subscription_id' => $int,
                    'post_count' => $int,
                    'last_post' => $int,
                    'last_post_by' => $int,
                    'last_post_staff' => $int,
                    'created' => $int,
                    'modified' => $int,
                    'resolved' => $int,
                    'closed' => $int,
                    // 1.1: assignment, SLA, rating, counts
                    'priority_rank' => $int,
                    'assignee_id' => $int,
                    'assigned' => $int,
                    'source' => array( 'length' => 16, 'type' => 'varchar', 'not_null' => '1', 'default' => 'web' ),
                    'external_id' => $varchar( 64 ),
                    'sla_level' => $varchar( 32 ),
                    'response_due' => $int,
                    'resolve_due' => $int,
                    'responded' => $int,
                    'paused_since' => $int,
                    'escalation' => $int,
                    'escalated' => $int,
                    'breached' => $int,
                    'rating' => $int,
                    'rating_comment' => $varchar( 1000 ),
                    'rated' => $int,
                    'note_count' => $int,
                    'attachment_count' => $int,
                ),
                'indexes' => array(
                    'PRIMARY' => array( 'type' => 'primary', 'fields' => array( 'id' ) ),
                    'ticketsystem_ticket_number' => array( 'type' => 'unique', 'fields' => array( 'number' ) ),
                    'ticketsystem_ticket_object' => array( 'type' => 'unique', 'fields' => array( 'contentobject_id' ) ),
                    'ticketsystem_ticket_account' => $index( 'account_key', 'status' ),
                    'ticketsystem_ticket_opener' => $index( 'opener_id' ),
                    'ticketsystem_ticket_status' => $index( 'status', 'modified' ),
                    'ticketsystem_ticket_assignee' => $index( 'assignee_id', 'status' ),
                    'ticketsystem_ticket_escal' => $index( 'escalation', 'status' ),
                ),
            ),
            'ticketsystem_counter' => array(
                'name' => 'ticketsystem_counter',
                'fields' => array(
                    'prefix' => $varchar( 16 ),
                    'year' => $int,
                    'last_value' => $int,
                ),
                'indexes' => array(
                    'PRIMARY' => array( 'type' => 'primary', 'fields' => array( 'prefix', 'year' ) ),
                ),
            ),
            'ticketsystem_note' => $table( 'ticketsystem_note', array(
                    'ticket_id' => $int, 'author_id' => $int, 'body' => $text, 'created' => $int, 'attachment_count' => $int ),
                array( 'ticketsystem_note_ticket' => $index( 'ticket_id' ) ) ),
            'ticketsystem_event' => $table( 'ticketsystem_event', array(
                    'ticket_id' => $int, 'actor_id' => $int, 'kind' => $varchar( 32 ), 'old_value' => $varchar( 255 ),
                    'new_value' => $varchar( 255 ), 'created' => $int ),
                array( 'ticketsystem_event_ticket' => $index( 'ticket_id', 'created' ), 'ticketsystem_event_kind' => $index( 'kind', 'created' ) ) ),
            'ticketsystem_canned' => $table( 'ticketsystem_canned', array(
                    'title' => $varchar( 200 ), 'body' => $text, 'category' => $varchar( 64 ), 'set_status' => $varchar( 32 ),
                    'set_priority' => $varchar( 32 ), 'assign_self' => $int, 'owner_id' => $int, 'created_by' => $int,
                    'created' => $int, 'modified' => $int, 'use_count' => $int ),
                array( 'ticketsystem_canned_owner' => $index( 'owner_id' ) ) ),
            'ticketsystem_attachment' => $table( 'ticketsystem_attachment', array(
                    'ticket_id' => $int, 'post_id' => $int, 'note_id' => $int, 'uploader_id' => $int, 'name' => $varchar( 255 ),
                    'mime' => $varchar( 128 ), 'filesize' => $int, 'sha256' => $varchar( 64 ), 'storage_key' => $varchar( 128 ),
                    'created' => $int ),
                array( 'ticketsystem_attach_ticket' => $index( 'ticket_id' ) ) ),
            'ticketsystem_post' => $table( 'ticketsystem_post', array(
                    'ticket_id' => $int, 'object_id' => $int, 'author_id' => $int, 'is_staff' => $int, 'created' => $int, 'body' => $text ),
                array( 'ticketsystem_post_ticket' => $index( 'ticket_id' ),
                       'ticketsystem_post_object' => array( 'type' => 'unique', 'fields' => array( 'object_id' ) ) ) ),
            'ticketsystem_mailin' => $table( 'ticketsystem_mailin', array(
                    'message_key' => $varchar( 64 ), 'ticket_id' => $int, 'user_id' => $int, 'result' => $varchar( 32 ), 'created' => $int ),
                array( 'ticketsystem_mailin_key' => array( 'type' => 'unique', 'fields' => array( 'message_key' ) ) ) ),
            'ticketsystem_import' => $table( 'ticketsystem_import', array(
                    'source' => $varchar( 16 ), 'kind' => $varchar( 16 ), 'external_id' => $varchar( 64 ), 'local_id' => $int,
                    'created' => $int ),
                array( 'ticketsystem_import_ext' => array( 'type' => 'unique', 'fields' => array( 'source', 'kind', 'external_id' ) ) ) ),
        );
    }

    /**
     * The engine of a connection: mysql, postgresql, sqlite, oracle or mongodb ('' for anything else).
     *
     * @param eZDBInterface $db
     * @return string
     */
    public static function engineOf( $db )
    {
        $name = strtolower( (string)$db->databaseName() );
        if ( $name === 'postgresql' || $name === 'pgsql' )
            return 'postgresql';
        if ( strpos( $name, 'sqlite' ) === 0 )
            return 'sqlite';
        if ( strpos( $name, 'mongo' ) === 0 )
            return 'mongodb';
        return in_array( $name, array( 'mysql', 'oracle' ), true ) ? $name : '';
    }

    /**
     * The kernel's schema handler of an engine for $schema.
     *
     * @return eZDBSchemaInterface|null
     */
    public static function handler( $engine, array $schema, $db = null )
    {
        $classes = array( 'mysql' => 'eZMysqlSchema', 'postgresql' => 'eZPgsqlSchema', 'sqlite' => 'eZSQLiteSchema',
                          'oracle' => 'eZOracleSchema', 'mongodb' => 'expMongoSchema' );
        if ( !isset( $classes[$engine] ) )
            return null;
        $class = $classes[$engine];
        if ( !class_exists( $class ) && $engine === 'oracle' && class_exists( 'eZExtension' ) )
        {
            $file = eZExtension::baseDirectory() . '/ezoracle/ezdb/dbms-schema/ezoracleschema.php';
            if ( is_file( $file ) )
                require_once $file;
        }
        if ( !class_exists( $class ) )
            return null;
        return new $class( array( 'schema' => $schema, 'instance' => $db, 'type' => $engine ) );
    }

    /**
     * The SQL statements that create $tables (all when empty) on an SQL engine, from definition().
     *
     * @return string[] empty when the engine has no handler here
     */
    public static function sqlStatements( $engine, array $tables = array() )
    {
        $schema = self::definition();
        $handler = self::handler( $engine, $schema );
        if ( !$handler || $engine === 'mongodb' )
            return array();
        $local = $schema;
        $local['_info'] = array( 'format' => 'generic' );
        $handler->transformSchema( $local, true );
        unset( $local['_info'] );
        $out = array();
        foreach ( $local as $table => $def )
        {
            if ( $tables && !in_array( $table, $tables, true ) )
                continue;
            foreach ( $handler->generateTableSQLList( $table, $def, array( 'format' => 'local', 'table_type' => $engine === 'mysql' ? 'innodb' : '' ), false ) as $statement )
            {
                $statement = trim( $statement );
                // PL/SQL blocks (Oracle sequences and triggers) end in "END;", which belongs to them
                if ( !preg_match( '/^(BEGIN\b|CREATE OR REPLACE TRIGGER\b)/i', $statement ) )
                    $statement = rtrim( $statement, ';' );
                if ( $statement !== '' )
                    $out[] = $statement;
            }
        }
        return $out;
    }

    /**
     * The collections and indexes on MongoDB (sql/mongodb/schema.json).
     *
     * @return array
     */
    public static function mongoDescription()
    {
        $collections = array();
        foreach ( self::definition() as $table => $def )
        {
            $indexes = array();
            foreach ( $def['indexes'] as $name => $index )
            {
                $keys = array();
                foreach ( $index['fields'] as $field )
                    $keys[$field] = 1;
                $options = array( 'name' => $name );
                if ( $index['type'] === 'primary' || $index['type'] === 'unique' )
                    $options['unique'] = true;
                $indexes[] = array( 'keys' => $keys, 'options' => $options );
            }
            $collections[] = array( 'name' => $table, 'indexes' => $indexes );
        }
        return array(
            'database' => 'ticketsystem',
            'comment' => 'ticketsystem (the help desk) on MongoDB: the collections and indexes the kernel\'s MongoDB schema handler (expMongoSchema) creates from ticketsystemSchema::definition(); bin/php/install.php does it in a running installation. A single-key PRIMARY index makes the driver fill ticketsystem_ticket.id; ticket numbers are taken with an atomic $inc on ticketsystem_counter.',
            'collections' => $collections,
        );
    }

    /**
     * The text of a generated schema file: sql/oracle/schema.sql or sql/mongodb/schema.json.
     *
     * @param string $engine oracle or mongodb
     * @return string|null null when the engine's handler is not there
     */
    public static function schemaFileText( $engine )
    {
        if ( $engine === 'mongodb' )
            return json_encode( self::mongoDescription(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
        $statements = self::sqlStatements( $engine );
        if ( !$statements )
            return null;
        $out = "-- ticketsystem (the help desk): its tables on $engine.\n"
             . "-- Generated from ticketsystemSchema::definition() by bin/php/install.php --write-schema-files with the kernel's\n"
             . "-- schema handler; bin/php/install.php creates the same tables in a running installation.\n\n";
        foreach ( $statements as $statement )
            $out .= $statement . ( preg_match( '/^(BEGIN\b|CREATE OR REPLACE TRIGGER\b)/i', $statement ) ? "\n/\n" : ";\n" ) . "\n";
        return $out;
    }

    /**
     * The help desk tables that exist. The drivers' relationList() lists only the kernel's ez* tables on some
     * engines, so each engine's catalog is asked.
     *
     * @return string[]
     */
    public static function existingTables( $db = null )
    {
        $db = $db ?: eZDB::instance();
        $engine = self::engineOf( $db );
        if ( $engine === 'mongodb' )
            $names = method_exists( $db, 'listCollectionNames' ) ? (array)$db->listCollectionNames() : array();
        else
        {
            switch ( $engine )
            {
                case 'sqlite':
                    $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'ticketsystem%'";
                    break;
                case 'mysql':
                    $sql = "SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'ticketsystem%'";
                    break;
                case 'postgresql':
                    $sql = "SELECT relname AS name FROM pg_class WHERE relkind = 'r' AND relname LIKE 'ticketsystem%'";
                    break;
                case 'oracle':
                    $sql = "SELECT LOWER(table_name) AS name FROM user_tables WHERE table_name LIKE 'TICKETSYSTEM%'";
                    break;
                default:
                    return array_values( array_intersect( self::TABLES, array_map( 'strtolower', (array)$db->relationList() ) ) );
            }
            $names = array();
            foreach ( (array)$db->arrayQuery( $sql ) as $row )
            {
                $row = array_change_key_case( $row, CASE_LOWER );
                $names[] = isset( $row['name'] ) ? $row['name'] : reset( $row );
            }
        }
        return array_values( array_intersect( self::TABLES, array_map( 'strtolower', $names ) ) );
    }

    /**
     * Creates the missing tables with the engine's schema handler (Oracle, MongoDB).
     *
     * @param string[] $tables the missing ones
     * @return string[] errors (empty: done)
     */
    public static function create( array $tables, $db = null )
    {
        $db = $db ?: eZDB::instance();
        $engine = self::engineOf( $db );
        if ( !$tables )
            return array();
        if ( $engine === 'mongodb' )
        {
            $schema = array_intersect_key( self::definition(), array_flip( $tables ) );
            $handler = self::handler( 'mongodb', $schema, $db );
            if ( $handler && method_exists( $handler, 'insertSchema' ) && $handler->insertSchema( array( 'schema' => true, 'data' => false ) ) )
                return array();
            return array( 'mongodb: the schema handler could not create ' . implode( ', ', $tables ) );
        }
        $statements = self::sqlStatements( $engine, $tables );
        if ( !$statements )
            return array( "no schema handler for the database type $engine" );
        $errors = array();
        foreach ( $statements as $statement )
        {
            if ( $db->query( $statement ) === false )
                $errors[] = $engine . ': ' . substr( $statement, 0, 80 );
        }
        return $errors;
    }

    // ---------------------------------------------------------------------------------- 1.1: the upgrade

    protected static function errorHandling( $db )
    {
        try
        {
            $mode = ( function () { return isset( $this->errorHandling ) ? $this->errorHandling : null; } )->call( $db );
        }
        catch ( Throwable $e )
        {
            $mode = null;
        }
        return $mode === null ? eZDB::ERROR_HANDLING_STANDARD : $mode;
    }

    /** A statement that may fail without stopping the run. */
    public static function tryQuery( $db, $sql )
    {
        $before = self::errorHandling( $db );
        $db->setErrorHandling( eZDB::ERROR_HANDLING_EXCEPTIONS );
        try
        {
            $ok = $db->query( $sql ) !== false;
        }
        catch ( Throwable $e )
        {
            $ok = false;
        }
        $db->setErrorHandling( $before );
        return $ok;
    }

    /** A query that may fail: its rows, or null. */
    public static function tryArrayQuery( $db, $sql, array $params = array() )
    {
        $before = self::errorHandling( $db );
        $db->setErrorHandling( eZDB::ERROR_HANDLING_EXCEPTIONS );
        try
        {
            $rows = $db->arrayQuery( $sql, $params );
        }
        catch ( Throwable $e )
        {
            $rows = null;
        }
        $db->setErrorHandling( $before );
        return is_array( $rows ) ? $rows : null;
    }

    /** Whether a column exists (always on MongoDB: a document gets its fields when it is written). */
    public static function hasColumn( $db, $table, $column )
    {
        if ( self::engineOf( $db ) === 'mongodb' )
            return true;
        if ( self::engineOf( $db ) === 'oracle' && $column === 'number' )
            $column = '"NUMBER"';
        return self::tryArrayQuery( $db, "SELECT $column FROM $table WHERE 1 = 0" ) !== null;
    }

    /**
     * The columns of definition() an existing table lacks (an installation of an older release), and the
     * statements that add them and their indexes, from the engine's schema handler.
     *
     * @return array column => string[] statements
     */
    public static function missingColumns( $table, $db = null )
    {
        $db = $db ?: eZDB::instance();
        $engine = self::engineOf( $db );
        $def = self::definition();
        if ( !isset( $def[$table] ) || $engine === 'mongodb' )
            return array();
        $local = array( $table => $def[$table], '_info' => array( 'format' => 'generic' ) );
        $handler = self::handler( $engine, $local, $db );
        if ( !$handler )
            return array();
        $handler->transformSchema( $local, true );
        $out = array();
        foreach ( $def[$table]['fields'] as $field => $fdef )
        {
            if ( self::hasColumn( $db, $table, $field ) )
                continue;
            $sql = array( rtrim( trim( (string)$handler->generateAddFieldSql( $table, $field, $local[$table]['fields'][$field], array() ) ), ';' ) );
            // the indexes that need the new column; the last argument: "embedded" on MySQL/SQLite, "with closure" else
            foreach ( $local[$table]['indexes'] as $name => $idef )
                if ( $idef['type'] !== 'primary' && end( $idef['fields'] ) === $field || ( $idef['type'] !== 'primary' && $idef['fields'][0] === $field ) )
                    $sql[] = rtrim( trim( (string)$handler->generateAddIndexSql( $table, $name, $idef, array(), !in_array( $engine, array( 'mysql', 'sqlite' ), true ) ) ), ';' );
            $out[$field] = array_values( array_unique( array_filter( $sql ) ) );
        }
        return $out;
    }
}
