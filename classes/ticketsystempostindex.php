<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The text of a customer-visible post for the staff search (doc/search.md); internal notes are never in it.
 */
class ticketsystemPostIndex extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemPostIndex', 'ticketsystem_post', array( 'id' => 'int', 'ticket_id' => 'int', 'object_id' => 'int', 'author_id' => 'int', 'is_staff' => 'int', 'created' => 'int', 'body' => 'string' ) );
    }
}
