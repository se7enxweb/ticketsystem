<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Which ticket, post or note an imported record became (doc/import-whmcs.md): one row per source record, so an
 * import never makes the same record twice. kind is ticket, reply or note; local_id the ticket id, the post's content
 * object id or the note id.
 */
class ticketsystemImportMap extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemImportMap', 'ticketsystem_import', array( 'id' => 'int', 'source' => 'string', 'kind' => 'string', 'external_id' => 'string', 'local_id' => 'int', 'created' => 'int' ) );
    }

    /** The row of a source record, or null. */
    public static function find( $source, $kind, $externalId )
    {
        $rows = self::fetchBy( array( 'source' => (string)$source, 'kind' => (string)$kind, 'external_id' => (string)$externalId ) );
        return $rows ? $rows[0] : null;
    }

    /** Records a source record as $localId. */
    public static function remember( $source, $kind, $externalId, $localId )
    {
        $row = self::make( array( 'source' => (string)$source, 'kind' => (string)$kind, 'external_id' => (string)$externalId,
                                  'local_id' => (int)$localId, 'created' => time() ) );
        $row->store();
        return $row;
    }

    /** How many records of a source were imported: kind => count. */
    public static function counts( $source )
    {
        $out = array();
        foreach ( array( 'ticket', 'reply', 'note' ) as $kind )
            $out[$kind] = self::countBy( array( 'source' => (string)$source, 'kind' => $kind ) );
        return $out;
    }
}
