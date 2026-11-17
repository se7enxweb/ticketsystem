<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The base of the small tables of ticketsystem 1.1 (notes, events, canned replies, attachments, the post index, the
 * mail-in log, the import map): their eZPersistentObject definition from a field list, and the reads every one needs.
 * Ids are always left to the database (null on a new row): Oracle fills an id only when it is NULL, MongoDB takes the
 * next one of eZPersistentObject's own sequence.
 */
abstract class ticketsystemRow extends eZPersistentObject
{
    /** @param array $fields name => int|string */
    protected static function makeDefinition( $class, $table, array $fields, array $sort = array( 'id' => 'asc' ) )
    {
        $out = array();
        foreach ( $fields as $name => $type )
            $out[$name] = array( 'name' => $name, 'datatype' => $type === 'int' ? 'integer' : 'string',
                                 'default' => $type === 'int' ? 0 : '', 'required' => true );
        return array( 'fields' => $out, 'keys' => array( 'id' ), 'increment_key' => 'id', 'class_name' => $class,
                      'sort' => $sort, 'name' => $table );
    }

    /** A new row (not stored): the given values over the defaults, the id left to the database. */
    public static function make( array $row )
    {
        $class = get_called_class();
        $def = $class::definition();
        $values = array();
        foreach ( $def['fields'] as $name => $f )
            $values[$name] = array_key_exists( $name, $row ) ? $row[$name] : $f['default'];
        $values['id'] = null;
        return new $class( $values );
    }

    public static function fetchByID( $id )
    {
        $class = get_called_class();
        return eZPersistentObject::fetchObject( $class::definition(), null, array( 'id' => (int)$id ) );
    }

    /** @return static[] */
    public static function fetchBy( array $conds, ?array $sort = null, $offset = 0, $limit = 0 )
    {
        $class = get_called_class();
        $def = $class::definition();
        return (array)eZPersistentObject::fetchObjectList( $def, null, $conds, $sort === null ? $def['sort'] : $sort,
                                                           $limit ? array( 'offset' => (int)$offset, 'length' => (int)$limit ) : null );
    }

    public static function countBy( ?array $conds = null )
    {
        $class = get_called_class();
        return (int)eZPersistentObject::count( $class::definition(), $conds );
    }

    /** The row as an array of its fields (Oracle hands an empty string back as null). */
    public function values()
    {
        $out = array();
        $def = static::definition();
        foreach ( $def['fields'] as $name => $f )
        {
            $v = $this->attribute( $name );
            $out[$name] = $f['datatype'] === 'integer' ? (int)$v : (string)$v;
        }
        return $out;
    }
}
