<?php
/**
 * Cronjob part "ticketsystem" (settings/cronjob.ini.append.php): the SLA check (doc/sla.md). Runs the escalation
 * steps ([SLASettings] EscalationSteps[]) that are due on the active tickets, each once per ticket: alerts to the
 * assignee, the queue leads or the support group, a higher priority, the mark "escalated". Run it every 5 minutes.
 *
 *   php runcronjobs.php -s <siteaccess> ticketsystem
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZCLI $cli */
$r = ticketsystemService::slaRun();
if ( !$isQuiet )
{
    if ( $r['locked'] )
        $cli->output( 'ticketsystem: SLA check skipped, another run is busy' );
    else
        $cli->output( sprintf( 'ticketsystem: SLA checked %d ticket(s): %d escalation step(s), %d staff mail(s); %d ticket(s) reached the warning level, %d breached',
                               $r['checked'], $r['steps'], $r['mails'], $r['warned'], $r['breached'] ) );
}
