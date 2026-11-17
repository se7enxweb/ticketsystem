<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * A canned reply or macro (doc/canned-replies.md): a text with placeholders and the changes it makes when it is sent (status, priority, assign to the sender). owner_id 0 = shared by every staff member.
 */
class ticketsystemCannedReply extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemCannedReply', 'ticketsystem_canned', array( 'id' => 'int', 'title' => 'string', 'body' => 'string', 'category' => 'string', 'set_status' => 'string', 'set_priority' => 'string', 'assign_self' => 'int', 'owner_id' => 'int', 'created_by' => 'int', 'created' => 'int', 'modified' => 'int', 'use_count' => 'int' ) );
    }
}
