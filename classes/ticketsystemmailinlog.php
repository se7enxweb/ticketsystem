<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * One inbound mail that was handled (doc/mail.md): the hash of its Message-ID, so the same mail is never posted twice.
 */
class ticketsystemMailInLog extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemMailInLog', 'ticketsystem_mailin', array( 'id' => 'int', 'message_key' => 'string', 'ticket_id' => 'int', 'user_id' => 'int', 'result' => 'string', 'created' => 'int' ) );
    }
}
