<?php
/**
 * DB-less tests of the table definition the help desk creates on every database (ticketsystemSchema): it is the
 * schema of sql/mysql/schema.sql, every name fits Oracle, and the generated files sql/oracle/schema.sql and
 * sql/mongodb/schema.json are current.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemSchemaTest extends TestCase
{
    /** The columns and indexes of sql/mysql/schema.sql, table => array( columns, indexes ) */
    private function mysqlSchema()
    {
        $out = array();
        $sql = file_get_contents( __DIR__ . '/../sql/mysql/schema.sql' );
        preg_match_all( '/CREATE TABLE (\w+) \((.*?)\n\)/s', $sql, $tables, PREG_SET_ORDER );
        foreach ( $tables as $t )
        {
            $columns = array();
            $indexes = array();
            foreach ( explode( "\n", $t[2] ) as $line )
            {
                $line = trim( $line, " \t," );
                if ( preg_match( '/^PRIMARY KEY \( (.*) \)$/', $line, $m ) )
                    $indexes['PRIMARY'] = array( 'primary', array_map( 'trim', explode( ',', $m[1] ) ) );
                else if ( preg_match( '/^(UNIQUE )?KEY (\w+) \( (.*) \)$/', $line, $m ) )
                    $indexes[$m[2]] = array( $m[1] ? 'unique' : 'non-unique', array_map( 'trim', explode( ',', $m[3] ) ) );
                else if ( preg_match( '/^(\w+) (int|varchar)\((\d+)\)/', $line, $m ) )
                    $columns[$m[1]] = array( $m[2], (int)$m[3], stripos( $line, 'AUTO_INCREMENT' ) !== false );
                else if ( preg_match( '/^(\w+) longtext\b/', $line, $m ) )
                    $columns[$m[1]] = array( 'longtext', 0, false );
            }
            $out[$t[1]] = array( $columns, $indexes );
        }
        return $out;
    }

    public function testDefinitionIsTheSchemaOfMysql()
    {
        $mysql = $this->mysqlSchema();
        $def = ticketsystemSchema::definition();
        $this->assertSame( array_keys( $mysql ), array_keys( $def ) );
        $this->assertSame( ticketsystemSchema::TABLES, array_keys( $def ) );
        foreach ( $def as $table => $d )
        {
            list( $columns, $indexes ) = $mysql[$table];
            $this->assertSame( array_keys( $columns ), array_keys( $d['fields'] ), "$table: the columns in the same order" );
            foreach ( $d['fields'] as $name => $field )
            {
                list( $type, $length, $auto ) = $columns[$name];
                if ( $auto )
                    $this->assertSame( 'auto_increment', $field['type'], "$table.$name" );
                else
                {
                    $this->assertSame( $type, $field['type'], "$table.$name" );
                    if ( $type !== 'longtext' )
                        $this->assertSame( $length, $field['length'], "$table.$name" );
                }
            }
            $this->assertEqualsCanonicalizing( array_keys( $indexes ), array_keys( $d['indexes'] ), "$table: the indexes" );
            foreach ( $d['indexes'] as $name => $index )
                $this->assertSame( $indexes[$name], array( $index['type'], $index['fields'] ), "$table: $name" );
        }
    }

    public function testNamesFitOracle()
    {
        foreach ( ticketsystemSchema::definition() as $table => $d )
        {
            $this->assertLessThanOrEqual( 30, strlen( $table ) );
            $this->assertLessThanOrEqual( 30, strlen( 'se_' . $table ), 'the sequence of the id' );
            $this->assertLessThanOrEqual( 30, strlen( $table . '_id_tr' ), 'the trigger of the id' );
            foreach ( array_keys( $d['fields'] ) as $name )
                $this->assertLessThanOrEqual( 30, strlen( $name ) );
            foreach ( array_keys( $d['indexes'] ) as $name )
                $this->assertLessThanOrEqual( 30, strlen( $name ) );
        }
    }

    public function testOracleQuotesTheNumberColumn()
    {
        $sql = file_get_contents( __DIR__ . '/../sql/oracle/schema.sql' );
        $this->assertStringContainsString( '"NUMBER" VARCHAR2(32)', $sql );
        $this->assertStringContainsString( 'CREATE UNIQUE INDEX ticketsystem_ticket_number ON ticketsystem_ticket ( "NUMBER" )', $sql );
        $this->assertStringContainsString( 'CREATE SEQUENCE se_ticketsystem_ticket', $sql );
        $this->assertStringContainsString( 'PRIMARY KEY ( prefix, year )', $sql );
        // and the driver is told to quote it in the help desk's queries
        $ini = file_get_contents( __DIR__ . '/../settings/ezoracle.ini.append.php' );
        $this->assertMatchesRegularExpression( '/^\[QuotedIdentifierSettings\]\s*\nTypeColumnNames\[\]=number$/m', $ini );
    }

    public function testMongoDescriptionIsCurrent()
    {
        $this->assertSame( ticketsystemSchema::schemaFileText( 'mongodb' ), file_get_contents( __DIR__ . '/../sql/mongodb/schema.json' ),
                           'sql/mongodb/schema.json: run bin/php/install.php --write-schema-files' );
        $collections = array();
        foreach ( ticketsystemSchema::mongoDescription()['collections'] as $c )
            $collections[$c['name']] = $c['indexes'];
        // a single-key PRIMARY index: the driver fills ticketsystem_ticket.id; the counter's is (prefix, year), unique
        $this->assertSame( array( 'keys' => array( 'id' => 1 ), 'options' => array( 'name' => 'PRIMARY', 'unique' => true ) ), $collections['ticketsystem_ticket'][0] );
        $this->assertSame( array( 'keys' => array( 'prefix' => 1, 'year' => 1 ), 'options' => array( 'name' => 'PRIMARY', 'unique' => true ) ), $collections['ticketsystem_counter'][0] );
    }

    /** 1.1: every engine's file and share/db_schema.dba (the MongoDB driver reads the column types from it) name every table. */
    public function testEveryGeneratedFileHasEveryTable()
    {
        foreach ( array( 'mysql', 'postgresql', 'sqlite', 'oracle' ) as $engine )
        {
            $sql = file_get_contents( __DIR__ . "/../sql/$engine/schema.sql" );
            foreach ( ticketsystemSchema::TABLES as $table )
                $this->assertMatchesRegularExpression( '/CREATE TABLE (IF NOT EXISTS )?' . $table . '\b/', $sql, "$engine: $table" );
        }
        $schema = null;
        include __DIR__ . '/../share/db_schema.dba';
        $this->assertSame( ticketsystemSchema::definition(), array_diff_key( $schema, array( '_info' => 1 ) ), 'share/db_schema.dba is current' );
    }
}
