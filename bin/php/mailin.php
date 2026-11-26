#!/usr/bin/env php
<?php
/**
 * Reply by e-mail (doc/mail-in.md): hands inbound mail to ticketsystemMailIn, which posts it on its ticket as the
 * sending user (or quarantines/bounces it). One line per mail with its result, never an address.
 *
 *   ... | php extension/ticketsystem/bin/php/mailin.php -s <siteaccess>            a pipe: one mail on stdin (Postfix,
 *                                                                                   Plesk mail handler, procmail)
 *   php extension/ticketsystem/bin/php/mailin.php -s <siteaccess> --file=<message.eml>
 *   php extension/ticketsystem/bin/php/mailin.php -s <siteaccess> --maildir=<path/Maildir>   every mail of new/,
 *                                                                                   each moved to cur/ when handled
 *   php extension/ticketsystem/bin/php/mailin.php -s <siteaccess> --poll             the IMAP/POP3 mailbox of
 *                                                                                   [MailInSettings] (as the cronjob)
 *
 * Exit code (pipe and --file): 0 handled (posted, opened, quarantined, ignored, duplicate); 77 bounced
 * ([MailInSettings] RefusedAction=bounce: the mail server returns the mail with the reason); 75 reply by e-mail is
 * disabled (the mail server keeps the mail and tries again later); 1 failure.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => 'ticketsystem: post inbound mail on its ticket',
                                     'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions( '[file:][maildir:][poll][limit:]', '',
    array( 'file' => 'One mail from a file', 'maildir' => 'A Maildir: every mail of new/ is handled and moved to cur/',
           'poll' => 'Fetch the mail of the IMAP/POP3 mailbox of [MailInSettings]', 'limit' => 'At most this many mails (default 50)' ) );
$script->initialize();

$say = function ( array $r, $name ) use ( $cli ) {
    $cli->output( sprintf( '%-11s %s%s (%s)', strtoupper( $r['result'] ), $name, $r['ticket'] !== '' ? ' ' . $r['ticket'] : '', $r['reason'] ) );
};
$limit = $options['limit'] ? max( 1, (int)$options['limit'] ) : 50;

if ( $options['poll'] )
{
    $in = new ticketsystemMailIn( null, 'poll' );
    try
    {
        $results = $in->poll( $limit );
    }
    catch ( Throwable $e )
    {
        $cli->output( 'FAIL ' . $e->getMessage() );
        $script->shutdown( 1 );
    }
    foreach ( $results as $i => $r )
        $say( $r, 'mail ' . ( $i + 1 ) );
    $cli->output( 'PASS ' . count( $results ) . ' mail(s)' );
    $script->shutdown( 0 );
}
if ( $options['maildir'] )
{
    $in = new ticketsystemMailIn( null, 'poll' );
    $dir = rtrim( (string)$options['maildir'], '/' );
    if ( !is_dir( "$dir/new" ) || !is_dir( "$dir/cur" ) )
    {
        $cli->output( 'FAIL no Maildir there' );
        $script->shutdown( 1 );
    }
    $n = 0;
    foreach ( array_slice( (array)glob( "$dir/new/*" ), 0, $limit ) as $file )
    {
        // one mail that fails stops only itself: it stays in new/ and is read again
        try
        {
            $r = $in->handle( (string)file_get_contents( $file ) );
        }
        catch ( Throwable $e )
        {
            $say( array( 'result' => 'failed', 'reason' => substr( $e->getMessage(), 0, 300 ), 'ticket' => '' ), basename( $file ) );
            continue;
        }
        $say( $r, basename( $file ) );
        if ( $r['result'] !== 'disabled' )
            rename( $file, "$dir/cur/" . basename( $file ) . ':2,S' );
        $n++;
    }
    $cli->output( "PASS $n mail(s)" );
    $script->shutdown( 0 );
}

$in = new ticketsystemMailIn( null, 'pipe' );
$raw = $options['file'] ? (string)@file_get_contents( (string)$options['file'] ) : (string)stream_get_contents( STDIN );
if ( trim( $raw ) === '' )
{
    $cli->output( 'FAIL no mail' );
    $script->shutdown( 1 );
}
$r = $in->handle( $raw );
$say( $r, $options['file'] ? basename( (string)$options['file'] ) : 'stdin' );
if ( $r['exit'] )
    fwrite( STDERR, 'The help desk did not accept this mail: ' . $r['reason'] . "\n" );
$script->shutdown( $r['exit'] );
