<?php
/**
 * Cronjob part "ticketsystem" (settings/cronjob.ini.append.php): the SLA check (doc/sla.md). Escalates the active
 * tickets at [SLASettings] WarnPercent of a target and when a target is missed, keeps the breach flags and mails the
 * alerts.
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
    $cli->output( sprintf( 'ticketsystem: SLA checked %d ticket(s), %d escalated at the warning level, %d breached', $r['checked'], $r['warned'], $r['breached'] ) );
