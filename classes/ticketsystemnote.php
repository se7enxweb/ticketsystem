<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * An internal staff note of a ticket (doc/notes.md). Never shown to a customer, never mailed, never in a customer's export or search: only ticketsystemService::notes() reads it, for staff.
 */
class ticketsystemNote extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemNote', 'ticketsystem_note', array( 'id' => 'int', 'ticket_id' => 'int', 'author_id' => 'int', 'body' => 'string', 'created' => 'int', 'attachment_count' => 'int' ) );
    }
}
