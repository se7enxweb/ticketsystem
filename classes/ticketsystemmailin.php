<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Reply by e-mail (doc/mail.md, bin/php/mailin.php): one inbound mail becomes a post on its ticket.
 *
 * - The ticket: the signed reference of a ticket mail (ticketsystemReplyToken: in the address it was sent to, the
 *   subject or the text), else the number in the subject ("[7X-2026-00042]") when [MailInSettings] RequireToken is
 *   disabled. Without either, a new ticket is opened when OpenNewTickets is enabled.
 * - The sender: the From address must be the e-mail of an enabled user; with a reference it must be the user the
 *   reference was made for. That user must be allowed to reply to the ticket (its policies, as on the site).
 * - The text: quoted text, the reply marker and the signature are cut (ticketsystemReplyToken::stripQuoted()); the
 *   files are checked like uploads ([AttachmentSettings]).
 * - Never twice: the Message-ID is kept (ticketsystem_mailin). Auto-replies, lists and bounces are ignored, and mails
 *   from IgnoreSenders and the help desk's own sender.
 *
 * Every result is one of: posted, opened, duplicate, ignored, refused; the log names the result and the ticket, never
 * an address.
 */
class ticketsystemMailIn
{
    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ?ticketsystemSettings $settings = null )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    protected function result( $result, $why, $ticket = '', array $extra = array() )
    {
        eZLog::write( "mail-in: $result" . ( $ticket !== '' ? " ticket $ticket" : '' ) . " ($why)", 'ticketsystem.log' );
        return array( 'result' => $result, 'reason' => $why, 'ticket' => $ticket ) + $extra;
    }

    /** Whether a parsed mail is an automatic one that must never become a post (pure). */
    public static function isAutomatic( array $mail )
    {
        $h = $mail['headers'];
        if ( isset( $h['auto-submitted'] ) && strtolower( trim( $h['auto-submitted'] ) ) !== 'no' )
            return true;
        if ( isset( $h['precedence'] ) && in_array( strtolower( trim( $h['precedence'] ) ), array( 'bulk', 'junk', 'list', 'auto_reply' ), true ) )
            return true;
        foreach ( array( 'x-autoreply', 'x-autorespond', 'list-id', 'list-unsubscribe', 'x-ticket-number' ) as $k )
            if ( isset( $h[$k] ) )
                return true;
        if ( preg_match( '/^(mailer-daemon|postmaster|no-?reply|bounce)/i', (string)$mail['from'] ) )
            return true;
        return false;
    }

    /**
     * Handles one raw mail.
     *
     * @return array result, reason, ticket (number), and post/ticket objects when done
     */
    public function handle( $raw )
    {
        if ( !$this->settings->enabled( 'MailInSettings', 'Enabled', 'disabled' ) )
            return $this->result( 'refused', 'reply by e-mail is disabled' );
        $max = max( 1, (int)$this->settings->value( 'MailInSettings', 'MaxMessageSize', 30 ) ) * 1048576;
        if ( strlen( (string)$raw ) > $max )
            return $this->result( 'refused', 'the mail is too large' );
        $mail = ticketsystemMailParser::parse( $raw );
        if ( $mail['from'] === '' || !eZMail::validate( $mail['from'] ) )
            return $this->result( 'ignored', 'no sender' );
        $mailer = new ticketsystemMailer( $this->settings );
        $ignore = array_map( 'strtolower', array_merge( (array)$this->settings->value( 'MailInSettings', 'IgnoreSenders', array() ), array( $mailer->sender() ) ) );
        if ( in_array( $mail['from'], $ignore, true ) || self::isAutomatic( $mail ) )
            return $this->result( 'ignored', 'an automatic mail or our own' );
        $key = hash( 'sha256', $mail['message_id'] !== '' ? $mail['message_id'] : $raw );
        if ( ticketsystemMailInLog::countBy( array( 'message_key' => $key ) ) )
            return $this->result( 'duplicate', 'this mail was handled before' );

        $secret = $mailer->secret();
        $refs = ticketsystemReplyToken::find( array_merge( $mail['to'], array( $mail['subject'], $mail['text'] ) ), $secret );
        $users = eZUser::fetchByEmail( $mail['from'] );
        $sender = $users instanceof eZUser ? $users : ( is_array( $users ) && $users ? $users[0] : null );
        if ( !$sender instanceof eZUser || !$sender->isEnabled() )
            return $this->log( $key, 0, 0, $this->result( 'refused', 'the sender is no user of the site' ) );
        $uid = (int)$sender->attribute( 'contentobject_id' );
        $ticket = null;
        foreach ( $refs as $r )
            if ( $r['user_id'] === $uid )
                $ticket = ticketsystemTicket::fetchByNumber( $r['number'] );
        if ( !$ticket && $refs )
            return $this->log( $key, 0, $uid, $this->result( 'refused', 'the reference was made for another person' ) );
        if ( !$ticket && !$this->settings->enabled( 'MailInSettings', 'RequireToken' ) )
        {
            $n = ticketsystemReplyToken::subjectNumber( $mail['subject'] );
            if ( $n !== '' )
                $ticket = ticketsystemTicket::fetchByNumber( $n );
        }
        $access = new ticketsystemAccess( $sender );
        $service = new ticketsystemService( $access, $this->settings );
        $text = ticketsystemReplyToken::stripQuoted( $mail['text'] );
        list( $files, $fileErrors ) = $this->files( $mail['attachments'] );

        $previous = eZUser::currentUser();
        eZUser::setCurrentlyLoggedInUser( $sender, $uid );
        try
        {
            if ( $ticket )
            {
                if ( !$access->canReply( $ticket ) )
                    return $this->log( $key, (int)$ticket->attribute( 'id' ), $uid, $this->result( 'refused', 'the sender may not reply to this ticket', (string)$ticket->attribute( 'number' ) ) );
                if ( $text === '' )
                    return $this->log( $key, (int)$ticket->attribute( 'id' ), $uid, $this->result( 'refused', 'the mail has no new text', (string)$ticket->attribute( 'number' ) ) );
                $r = $service->reply( $ticket, $text, '', $files );
                if ( $r['ok'] )
                    ticketsystemService::event( $ticket, $uid, 'mailin', '', 'reply' );
                return $this->log( $key, (int)$ticket->attribute( 'id' ), $uid,
                                   $this->result( $r['ok'] ? 'posted' : 'refused', $r['ok'] ? 'reply added' . ( $fileErrors ? ', some files refused' : '' ) : $r['message'],
                                                  (string)$ticket->attribute( 'number' ), array( 'file_errors' => count( $fileErrors ) ) ) );
            }
            if ( !$this->settings->enabled( 'MailInSettings', 'OpenNewTickets' ) )
                return $this->log( $key, 0, $uid, $this->result( 'refused', 'the mail names no ticket' ) );
            $subject = trim( preg_replace( '/^((re|aw|fwd?|wg)\s*:\s*)+/i', '', $mail['subject'] ) );
            $r = $service->open( array( 'subject' => $subject !== '' ? $subject : 'Mail without a subject', 'body' => $text, 'files' => $files, 'source' => 'mail' ) );
            if ( !$r['ok'] )
                return $this->log( $key, 0, $uid, $this->result( 'refused', $r['message'] ) );
            return $this->log( $key, (int)$r['ticket']->attribute( 'id' ), $uid, $this->result( 'opened', 'new ticket', (string)$r['ticket']->attribute( 'number' ) ) );
        }
        finally
        {
            eZUser::setCurrentlyLoggedInUser( $previous, (int)$previous->attribute( 'contentobject_id' ) );
            foreach ( $files as $f )
                @unlink( $f['path'] );
        }
    }

    protected function log( $key, $ticketId, $userId, array $result )
    {
        $row = ticketsystemMailInLog::make( array( 'message_key' => $key, 'ticket_id' => (int)$ticketId, 'user_id' => (int)$userId,
                                                   'result' => $result['result'], 'created' => time() ) );
        $row->store();
        return $result;
    }

    /** The attachments of the mail as checked files in temporary files: array( files, errors ). */
    protected function files( array $attachments )
    {
        $f = new ticketsystemFiles( $this->settings );
        $files = array();
        $errors = array();
        if ( !$f->enabled() )
            return array( $files, $errors );
        $dir = eZSys::rootDir() . '/var/ticketsystem/tmp';
        if ( !is_dir( $dir ) )
            @mkdir( $dir, 0750, true );
        foreach ( array_slice( $attachments, 0, $f->maxFiles() ) as $a )
        {
            $path = $dir . '/' . bin2hex( random_bytes( 12 ) );
            file_put_contents( $path, $a['data'] );
            $name = ticketsystemFiles::cleanName( $a['name'] );
            $why = $f->check( $name, $path, strlen( $a['data'] ) );
            if ( $why !== '' )
            {
                $errors[] = $why;
                @unlink( $path );
                continue;
            }
            $files[] = array( 'name' => $name, 'path' => $path, 'size' => strlen( $a['data'] ) );
        }
        return array( $files, $errors );
    }
}
