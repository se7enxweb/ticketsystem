<?php
/**
 * ticketsystem/mailin (staff, policy manage): reply by e-mail (doc/mail-in.md): whether it is on, how it receives
 * mail, and the latest inbound mails with their result (time, result, ticket; the reasons are in
 * var/log/ticketsystem.log; never an address or a text).
 * design:ticketsystem/mailin.tpl.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'manage', '/ticketsystem/mailin' ) ) !== null )
    return $denied;
$settings = ticketsystemSettings::instance();
$in = new ticketsystemMailIn( $settings );
$rows = array();
foreach ( ticketsystemMailInLog::latest( 50 ) as $r )
{
    $t = (int)$r['ticket_id'] ? ticketsystemTicket::fetch( (int)$r['ticket_id'] ) : null;
    $rows[] = array( 'created' => (int)$r['created'], 'result' => (string)$r['result'],
                     'number' => $t ? (string)$t->attribute( 'number' ) : '' );
}
return ticketsystemView::render( eZUser::currentUser(), 'mailin', 'mailin', array(
    'enabled' => $in->enabled( 'Enabled' ),
    'poll' => (string)$settings->value( 'MailInSettings', 'Host', '' ) !== '',
    'protocol' => strtoupper( (string)$settings->value( 'MailInSettings', 'Protocol', 'imap' ) ),
    'require_token' => $in->enabled( 'RequireToken' ),
    'open_new' => $in->enabled( 'OpenNewTickets' ),
    'refused_action' => (string)$settings->value( 'MailInSettings', 'RefusedAction', 'quarantine' ),
    'reply_address' => strpos( (string)$settings->value( 'MailInSettings', 'ReplyAddress', '' ), '%token' ) !== false,
    'rows' => $rows,
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Reply by e-mail' ) ) );
