<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * One escalation step that ran for a ticket (ticketsystem_escalation; doc/sla.md): the step's key (unique per ticket,
 * so a step runs once), its action and target, the escalation level, who ran it (0 = the cronjob) and how many staff
 * mails went out. Never an address.
 */
class ticketsystemEscalationLog extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemEscalationLog', 'ticketsystem_escalation', array(
            'id' => 'int', 'ticket_id' => 'int', 'step_key' => 'string', 'action' => 'string', 'target' => 'string', 'percent' => 'int',
            'level' => 'int', 'actor_id' => 'int', 'recipients' => 'int', 'sent' => 'int', 'failed' => 'int', 'created' => 'int' ) );
    }

    /** The step keys that ran for a ticket. @return string[] */
    public static function keysFor( $ticketId )
    {
        $out = array();
        foreach ( self::fetchBy( array( 'ticket_id' => (int)$ticketId ) ) as $row )
            $out[] = (string)$row->attribute( 'step_key' );
        return $out;
    }
}
