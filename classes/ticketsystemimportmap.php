<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Which ticket, post or note an imported record became (doc/import.md): one row per source record, so an import never makes the same record twice.
 */
class ticketsystemImportMap extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemImportMap', 'ticketsystem_import', array( 'id' => 'int', 'source' => 'string', 'kind' => 'string', 'external_id' => 'string', 'local_id' => 'int', 'created' => 'int' ) );
    }
}
