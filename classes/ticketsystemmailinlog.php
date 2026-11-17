<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Every inbound mail once (doc/mail-in.md): the hash of its Message-ID, the ticket and user it was matched to and the
 * result (posted, opened, ignored, quarantined, bounced). The reason is in var/log/ticketsystem.log. Never an address
 * or a text.
 */
class ticketsystemMailInLog extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemMailInLog', 'ticketsystem_mailin', array( 'id' => 'int', 'message_key' => 'string', 'ticket_id' => 'int', 'user_id' => 'int', 'result' => 'string', 'created' => 'int' ) );
    }

    public static function seen( $messageKey )
    {
        return self::countBy( array( 'message_key' => (string)$messageKey ) ) > 0;
    }

    public static function add( $messageKey, $ticketId, $userId, $result )
    {
        $row = self::make( array( 'message_key' => (string)$messageKey, 'ticket_id' => (int)$ticketId, 'user_id' => (int)$userId,
                                  'result' => (string)$result, 'created' => time() ) );
        $row->store();
        return $row;
    }

    /** The latest rows, newest first (the staff page ticketsystem/mailin). */
    public static function latest( $limit = 25 )
    {
        return array_map( function ( $r ) { return $r->values(); }, self::fetchBy( array(), array( 'id' => 'desc' ), 0, (int)$limit ) );
    }
}
