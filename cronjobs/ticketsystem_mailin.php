<?php
/**
 * Cronjob part "ticketsystem_mailin" (settings/cronjob.ini.append.php): reply by e-mail from a mailbox. Fetches the
 * waiting mail of the IMAP or POP3 mailbox of [MailInSettings] and posts each on its ticket (doc/mail-in.md). Does
 * nothing while [MailInSettings] Enabled is disabled or no Host is set. Run it every minute or two:
 *
 *   php runcronjobs.php -s <siteaccess> ticketsystem_mailin
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZCLI $cli */
$in = new ticketsystemMailIn( null, 'poll' );
if ( !$in->enabled( 'Enabled' ) || (string)ticketsystemSettings::instance()->value( 'MailInSettings', 'Host', '' ) === '' )
{
    if ( !$isQuiet )
        $cli->output( 'ticketsystem_mailin: reply by e-mail is not set up ([MailInSettings] Enabled, Host)' );
    return;
}
try
{
    $results = $in->poll( 50 );
}
catch ( Throwable $e )
{
    $cli->error( 'ticketsystem_mailin: ' . $e->getMessage() );
    return;
}
$by = array();
foreach ( $results as $r )
    $by[$r['result']] = ( isset( $by[$r['result']] ) ? $by[$r['result']] : 0 ) + 1;
if ( !$isQuiet )
    $cli->output( 'ticketsystem_mailin: ' . count( $results ) . ' mail(s)' . ( $by ? ' (' . http_build_query( $by, '', ', ' ) . ')' : '' ) );
