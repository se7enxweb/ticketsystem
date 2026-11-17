<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * A file of a ticket (doc/attachments.md): stored by ticketsystemFiles outside the public storage, served only by ticketsystem/file to readers of the ticket (a file of an internal note to staff only).
 */
class ticketsystemAttachment extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemAttachment', 'ticketsystem_attachment', array( 'id' => 'int', 'ticket_id' => 'int', 'post_id' => 'int', 'note_id' => 'int', 'uploader_id' => 'int', 'name' => 'string', 'mime' => 'string', 'filesize' => 'int', 'sha256' => 'string', 'storage_key' => 'string', 'created' => 'int' ) );
    }
}
