<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Time a staff member spent on a ticket, recorded with a post or an internal note (doc/time-tracking.md, ticketsystemTime):
 * minutes, billable or not, the SLA or warranty level that covered the account when it was recorded ('' none), the day
 * of the work, and, once billed, when and by what (billing_ref, e.g. the Katalyst job).
 */
class ticketsystemTimeEntry extends ticketsystemRow
{
    public static function definition()
    {
        return self::makeDefinition( 'ticketsystemTimeEntry', 'ticketsystem_time', array(
            'id' => 'int', 'ticket_id' => 'int', 'post_id' => 'int', 'note_id' => 'int', 'user_id' => 'int', 'minutes' => 'int',
            'billable' => 'int', 'covered' => 'string', 'work_date' => 'int', 'remark' => 'string', 'account_key' => 'string',
            'created' => 'int', 'billed' => 'int', 'billing_ref' => 'string' ) );
    }
}
