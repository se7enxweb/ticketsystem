<?php
/**
 * Cronjob part "ticketsystem" (settings/cronjob.ini.append.php): closes resolved tickets after
 * [StatusSettings] AutoCloseDays without a new post.
 *
 *   php runcronjobs.php -s <siteaccess> ticketsystem
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZCLI $cli */
$days = (int)ticketsystemSettings::instance()->value( 'StatusSettings', 'AutoCloseDays', 7 );
$n = ticketsystemService::autoClose( $days );
if ( !$isQuiet )
    $cli->output( "ticketsystem: closed $n resolved ticket(s) older than $days day(s)" );
