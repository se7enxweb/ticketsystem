<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * One change of a ticket for its history (assign, claim, priority, queue, status, escalate, rate, merge ...; doc/architecture.md). Values are identifiers or ids, never the text of a post.
 */
class ticketsystemEvent extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemEvent', 'ticketsystem_event', array( 'id' => 'int', 'ticket_id' => 'int', 'actor_id' => 'int', 'kind' => 'string', 'old_value' => 'string', 'new_value' => 'string', 'created' => 'int' ) );
    }
}
