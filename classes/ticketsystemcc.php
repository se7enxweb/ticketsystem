<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * A CC address of a ticket (doc/watchers.md): it receives a copy of every post of the ticket, nothing else. The address
 * is never matched to a user: it grants no access, carries no reply reference and is never an identity.
 */
class ticketsystemCC extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemCC', 'ticketsystem_cc', array( 'id' => 'int', 'ticket_id' => 'int', 'email' => 'string', 'added_by' => 'int', 'created' => 'int' ) );
    }
}
