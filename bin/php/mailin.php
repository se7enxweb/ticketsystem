#!/usr/bin/env php
<?php
/**
 * Reply by e-mail (doc/mail.md): hands inbound mail to ticketsystemMailIn, which posts it on its ticket (or opens a
 * new ticket) as the sending user. One line per mail with the result; the exit code is 0 unless the run failed.
 *
 *   php extension/ticketsystem/bin/php/mailin.php -s <siteaccess> < message.eml          (a pipe: one mail on stdin)
 *   php extension/ticketsystem/bin/php/mailin.php -s <siteaccess> --maildir=/path/Maildir  (every mail of new/;
 *        each handled mail is moved to cur/, so a cron line can run it every minute)
 *   php extension/ticketsystem/bin/php/mailin.php -s <siteaccess> --file=message.eml
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
$options = $script->getOptions( '[maildir:][file:]', '', array( 'maildir' => 'A Maildir: every mail of new/ is handled and moved to cur/',
                                                               'file' => 'One mail from a file' ) );
$script->initialize();

$in = new ticketsystemMailIn();
$say = function ( array $r, $name ) use ( $cli ) {
    $cli->output( sprintf( '%-9s %s%s (%s)', strtoupper( $r['result'] ), $name, $r['ticket'] !== '' ? ' ' . $r['ticket'] : '', $r['reason'] ) );
};
$ok = true;
if ( $options['maildir'] )
{
    $dir = rtrim( (string)$options['maildir'], '/' );
    if ( !is_dir( "$dir/new" ) || !is_dir( "$dir/cur" ) )
    {
        $cli->output( "FAIL no Maildir at $dir" );
        $script->shutdown( 1 );
    }
    foreach ( (array)glob( "$dir/new/*" ) as $file )
    {
        $raw = (string)file_get_contents( $file );
        $r = $in->handle( $raw );
        $say( $r, basename( $file ) );
        rename( $file, "$dir/cur/" . basename( $file ) . ':2,S' );
    }
}
else
{
    $raw = $options['file'] ? (string)@file_get_contents( (string)$options['file'] ) : (string)stream_get_contents( STDIN );
    if ( trim( $raw ) === '' )
    {
        $cli->output( 'FAIL no mail' );
        $script->shutdown( 1 );
    }
    $say( $in->handle( $raw ), $options['file'] ? basename( (string)$options['file'] ) : 'stdin' );
}
$script->shutdown( $ok ? 0 : 1 );
