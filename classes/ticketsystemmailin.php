<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Reply by e-mail (doc/mail-in.md): one inbound mail becomes a post on its ticket, as the user who sent it.
 *
 * - The ticket: the signed reference of a ticket mail (ticketsystemReplyToken) in the address the mail went to, in
 *   In-Reply-To/References (the Message-ID of our mail), the subject or the text. Else, unless
 *   [MailInSettings] RequireToken is enabled, the ticket number in the subject (7X-2026-00042).
 * - The sender: the From address must be the e-mail of an enabled user; with a reference it must be the user the
 *   reference was made for. With only a number in the subject the receiving server's Authentication-Results must
 *   not report a failed SPF, DKIM or DMARC check. The user must be allowed to reply to the ticket on the site (the
 *   ticket's customer and the users of its account, staff; followers once their policy lets them reply).
 * - The text: quoted text and the signature are cut (ticketsystemReplyToken::stripQuoted()). Files: handed to the
 *   help desk's attachment storage and checked like uploads ([AttachmentSettings]); with the storage off they are
 *   held for staff in HoldDirectory.
 * - Never twice: the Message-ID is kept (ticketsystem_mailin). Automatic mails (auto-replies, lists, bounces, our own
 *   ticket mails) are ignored.
 * - Refused mail: quarantined (RefusedAction=quarantine, a copy in QuarantineDirectory) or bounced by the mail server
 *   (RefusedAction=bounce, pipe only: exit code 77 with the reason). A sender who is a user of the site gets a short
 *   notice; an unknown sender never gets mail (no backscatter).
 * - New tickets by mail ([MailInSettings] OpenNewTickets, off by default): a mail without a ticket from a user who
 *   may open tickets opens one.
 *
 * Results: posted, opened, duplicate, ignored, quarantined, bounced, disabled. The log names the result, the reason and
 * the ticket number, never an address or a text.
 */
class ticketsystemMailIn
{
    /** sysexits(3): the mail server bounces (77) or retries later (75) */
    const EXIT_BOUNCE = 77;
    const EXIT_TEMPFAIL = 75;

    /** @var ticketsystemSettings */
    protected $settings;
    /** @var string 'pipe' (a bounce is possible) or 'poll' */
    protected $mode;

    public function __construct( ?ticketsystemSettings $settings = null, $mode = 'pipe' )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
        $this->mode = $mode === 'poll' ? 'poll' : 'pipe';
    }

    public function enabled( $name, $default = 'disabled' )
    {
        return in_array( strtolower( (string)$this->settings->value( 'MailInSettings', $name, $default ) ), array( 'enabled', 'true', '1', 'yes' ), true );
    }

    /** A path of the settings, relative to the installation unless absolute. */
    protected function path( $name, $default )
    {
        $p = trim( (string)$this->settings->value( 'MailInSettings', $name, $default ) );
        if ( $p === '' )
            return '';
        return $p[0] === '/' ? $p : eZSys::rootDir() . '/' . $p;
    }

    /** Whether a parsed mail is an automatic one that must never become a post (pure). */
    public static function isAutomatic( array $mail )
    {
        $h = $mail['headers'];
        // the rule every mailbox reader of the kernel shares (Exponential 6.0.15 and later): Auto-Submitted,
        // Precedence and the X-Autoreply headers; this desk adds lists, its own mails and mail-system senders below
        if ( class_exists( 'expMailReceive' ) && expMailReceive::isAutomaticHeaders( (array)$h ) )
            return true;
        if ( isset( $h['auto-submitted'] ) && strtolower( trim( $h['auto-submitted'] ) ) !== 'no' )
            return true;
        if ( isset( $h['precedence'] ) && in_array( strtolower( trim( $h['precedence'] ) ), array( 'bulk', 'junk', 'list', 'auto_reply' ), true ) )
            return true;
        foreach ( array( 'x-autoreply', 'x-autorespond', 'x-auto-response-suppress', 'list-id', 'list-unsubscribe', 'x-ticket-number' ) as $k )
            if ( isset( $h[$k] ) && ( $k !== 'x-auto-response-suppress' || stripos( $h[$k], 'all' ) !== false || stripos( $h[$k], 'autoreply' ) !== false ) )
                return true;
        if ( preg_match( '/^(mailer-daemon|postmaster|no-?reply|bounces?)[@+-]/i', (string)$mail['from'] ) )
            return true;
        if ( isset( $h['content-type'] ) && stripos( $h['content-type'], 'multipart/report' ) === 0 )
            return true;
        return false;
    }

    /** Whether the receiving server reported a failed SPF, DKIM or DMARC check (pure). */
    public static function authenticationFailed( array $mail )
    {
        $h = isset( $mail['headers']['authentication-results'] ) ? strtolower( (string)$mail['headers']['authentication-results'] ) : '';
        return (bool)preg_match( '/\b(dmarc|spf|dkim)=(fail|softfail|permerror)\b/', $h );
    }

    /**
     * Handles one raw mail.
     *
     * @return array result, reason, ticket (number or ''), exit (the pipe's exit code), files_held
     */
    public function handle( $raw )
    {
        if ( !$this->enabled( 'Enabled' ) )
            return $this->result( 'disabled', 'reply by e-mail is disabled ([MailInSettings] Enabled)', '', self::EXIT_TEMPFAIL );
        $raw = (string)$raw;
        $max = max( 1, (int)$this->settings->value( 'MailInSettings', 'MaxMessageSize', 30 ) ) * 1048576;
        $key = '';
        if ( strlen( $raw ) > $max )
            return $this->refuse( $raw, hash( 'sha256', $raw ), null, null, 'the mail is too large' );
        $mail = ticketsystemMailParser::parse( $raw );
        $key = hash( 'sha256', $mail['message_id'] !== '' ? $mail['message_id'] : $raw );
        if ( ticketsystemMailInLog::seen( $key ) )
            return $this->result( 'duplicate', 'this mail was handled before' );
        if ( $mail['from'] === '' || !eZMail::validate( $mail['from'] ) )
            return $this->log( $key, 0, 0, $this->result( 'ignored', 'no sender address' ) );
        $mailer = new ticketsystemMailer( $this->settings );
        $ignore = array_map( 'strtolower', array_merge( (array)$this->settings->value( 'MailInSettings', 'IgnoreSenders', array() ), array( $mailer->sender() ) ) );
        if ( in_array( $mail['from'], $ignore, true ) || self::isAutomatic( $mail ) )
            return $this->log( $key, 0, 0, $this->result( 'ignored', 'an automatic mail or one of our own' ) );

        $secret = ticketsystemReplyToken::secret( $this->settings );
        $refs = $secret === '' ? array() : ticketsystemReplyToken::find(
            array_merge( $mail['to'], $mail['references'], array( $mail['in_reply_to'], $mail['subject'], $mail['text'] ) ), $secret );
        $sender = eZUser::fetchByEmail( $mail['from'] );
        if ( is_array( $sender ) )
            $sender = $sender ? reset( $sender ) : null;
        if ( !$sender instanceof eZUser || !$sender->isEnabled() )
            return $this->refuse( $raw, $key, null, null, 'the sender is no user of the site' );
        $uid = (int)$sender->attribute( 'contentobject_id' );

        $ticket = null;
        $byToken = false;
        foreach ( $refs as $r )
            if ( $r['user_id'] === $uid && ( $t = ticketsystemTicket::fetchByNumber( $r['number'] ) ) )
            {
                $ticket = $t;
                $byToken = true;
            }
        if ( !$ticket && $refs )
            return $this->refuse( $raw, $key, $sender, null, 'the reply reference was made for another person' );
        if ( !$ticket && !$this->enabled( 'RequireToken' ) )
        {
            $prefix = ticketsystemNumber::cleanPrefix( $this->settings->value( 'NumberSettings', 'Prefix', 'TS' ) );
            $n = ticketsystemReplyToken::subjectNumber( $mail['subject'], $prefix );
            if ( $n !== '' )
            {
                $ticket = ticketsystemTicket::fetchByNumber( $n );
                if ( !$ticket )
                    return $this->refuse( $raw, $key, $sender, null, 'the ticket number in the subject is unknown' );
                if ( self::authenticationFailed( $mail ) )
                    return $this->refuse( $raw, $key, $sender, $ticket, 'the sender address failed the mail server\'s check (SPF, DKIM or DMARC)' );
            }
        }

        $access = new ticketsystemAccess( $sender );
        $service = new ticketsystemService( $access, $this->settings );
        $text = ticketsystemReplyToken::stripQuoted( $mail['text'] );
        $previous = eZUser::currentUser();
        eZUser::setCurrentlyLoggedInUser( $sender, $uid );
        try
        {
            if ( $ticket )
            {
                $number = (string)$ticket->attribute( 'number' );
                if ( !$access->canReply( $ticket ) )
                    return $this->refuse( $raw, $key, $sender, $ticket, 'the sender may not reply to this ticket' );
                if ( $text === '' )
                    return $this->refuse( $raw, $key, $sender, $ticket, 'the mail has no new text' );
                list( $files, $held, $refusedFiles ) = $this->files( $mail['attachments'] );
                $r = $files ? $service->reply( $ticket, $text, '', $files ) : $service->reply( $ticket, $text );
                if ( !$r['ok'] )
                    return $this->refuse( $raw, $key, $sender, $ticket, $r['message'] );
                if ( $held )
                    $this->hold( $mail['attachments'], $number, (int)$r['post']->attribute( 'id' ) );
                if ( method_exists( 'ticketsystemService', 'event' ) )
                    ticketsystemService::event( $ticket, $uid, 'mailin', '', $byToken ? 'reference' : 'subject' );
                return $this->log( $key, (int)$ticket->attribute( 'id' ), $uid,
                                   $this->result( 'posted', 'reply added' . ( $byToken ? ' (signed reference)' : ' (number in the subject)' )
                                                  . self::fileNote( count( $files ), $held, $refusedFiles ), $number, 0, $held ) );
            }
            if ( !$this->enabled( 'OpenNewTickets' ) )
                return $this->refuse( $raw, $key, $sender, null, 'the mail names no ticket' );
            if ( $text === '' )
                return $this->refuse( $raw, $key, $sender, null, 'the mail has no text' );
            $subject = trim( preg_replace( '/^((re|aw|fwd?|wg)\s*:\s*)+/i', '', $mail['subject'] ) );
            list( $files, $held, $refusedFiles ) = $this->files( $mail['attachments'] );
            $r = $service->open( array( 'subject' => $subject !== '' ? $subject : 'Mail without a subject', 'body' => $text, 'files' => $files, 'source' => 'mail' ) );
            if ( !$r['ok'] )
                return $this->refuse( $raw, $key, $sender, null, $r['message'] );
            $number = (string)$r['ticket']->attribute( 'number' );
            if ( $held )
                $this->hold( $mail['attachments'], $number, (int)$r['ticket']->attribute( 'contentobject_id' ) );
            return $this->log( $key, (int)$r['ticket']->attribute( 'id' ), $uid,
                               $this->result( 'opened', 'new ticket by mail' . self::fileNote( count( $files ), $held, $refusedFiles ), $number, 0, $held ) );
        }
        finally
        {
            if ( $previous instanceof eZUser )
                eZUser::setCurrentlyLoggedInUser( $previous, (int)$previous->attribute( 'contentobject_id' ) );
        }
    }

    protected function result( $result, $why, $ticket = '', $exit = 0, $held = 0 )
    {
        eZLog::write( "mail-in: $result" . ( $ticket !== '' ? " ticket $ticket" : '' ) . " ($why)", 'ticketsystem.log' );
        return array( 'result' => $result, 'reason' => $why, 'ticket' => $ticket, 'exit' => (int)$exit, 'files_held' => (int)$held );
    }

    protected function log( $key, $ticketId, $userId, array $result )
    {
        if ( $key !== '' )
            ticketsystemMailInLog::add( $key, $ticketId, $userId, $result['result'] );
        return $result;
    }

    /** A refused mail: bounced (pipe and RefusedAction=bounce) or quarantined; a known sender gets a notice. */
    protected function refuse( $raw, $key, ?eZUser $sender, ?ticketsystemTicket $ticket, $why )
    {
        $number = $ticket ? (string)$ticket->attribute( 'number' ) : '';
        $bounce = $this->mode === 'pipe' && strtolower( (string)$this->settings->value( 'MailInSettings', 'RefusedAction', 'quarantine' ) ) === 'bounce';
        if ( !$bounce )
            $this->quarantine( $raw, $key );
        if ( $sender && !$bounce && $this->enabled( 'NoticeToSender', 'enabled' ) )
            $this->notice( $sender, $number, $why );
        return $this->log( $key, $ticket ? (int)$ticket->attribute( 'id' ) : 0, $sender ? (int)$sender->attribute( 'contentobject_id' ) : 0,
                           $this->result( $bounce ? 'bounced' : 'quarantined', $why, $number, $bounce ? self::EXIT_BOUNCE : 0 ) );
    }

    protected function quarantine( $raw, $key )
    {
        $dir = $this->path( 'QuarantineDirectory', 'var/ticketsystem/mailin-quarantine' );
        if ( $dir === '' )
            return;
        if ( !is_dir( $dir ) )
            eZDir::mkdir( $dir, 0750, true );
        $file = $dir . '/' . date( 'Ymd-His' ) . '-' . substr( (string)$key, 0, 12 ) . '.eml';
        if ( @file_put_contents( $file, $raw ) !== false )
            @chmod( $file, 0600 );
    }

    /** The short notice to a known sender whose mail was not added (no quote of the mail). */
    protected function notice( eZUser $sender, $number, $why )
    {
        $mailer = new ticketsystemMailer( $this->settings );
        $address = (string)$sender->attribute( 'email' );
        $subject = ( $number !== '' ? "[$number] " : '' ) . 'Your e-mail was not added to a ticket';
        $text = "Hello,\n\nwe received your e-mail" . ( $number !== '' ? " about ticket $number" : '' ) . ", but could not add it to the help desk: $why.\n\n"
              . "Please reply to one of the ticket mails from the address of your account, or write on the help desk:\n"
              . $mailer->siteUrl() . '/ticketsystem/' . ( $number !== '' ? 'view/' . rawurlencode( $number ) : 'list' ) . "\n\n" . $mailer->brand() . "\n";
        if ( $mailer->captured( $address ) )
        {
            $mailer->captureText( $address, $subject, $text );
            return;
        }
        $mail = new eZMail();
        $mail->setSender( $mailer->sender(), (string)$this->settings->value( 'MailSettings', 'SenderName', '' ) ?: false );
        $mail->setReceiver( $address );
        $mail->setSubject( $subject );
        $mail->addExtraHeader( 'Auto-Submitted', 'auto-replied' );
        $mail->setBody( $text );
        eZMailTransport::send( $mail );
    }

    /** The part of a result's reason about the files (pure). */
    public static function fileNote( $stored, $held, $refused )
    {
        $parts = array();
        if ( $stored )
            $parts[] = "$stored file(s) attached";
        if ( $held )
            $parts[] = "$held file(s) held for staff";
        if ( $refused )
            $parts[] = "$refused file(s) refused";
        return $parts ? ', ' . implode( ', ', $parts ) : '';
    }

    /**
     * The attachments of a mail. With the help desk's attachment storage on ([AttachmentSettings] Enabled), each file
     * is checked like an upload (type, size, number, virus scan; ticketsystemFiles::check()) and handed over as a
     * temporary file (name, path, size); a refused file is dropped and counted. With the storage off, the files are
     * held for staff in HoldDirectory.
     *
     * @return array( files, held count, refused count )
     */
    protected function files( array $attachments )
    {
        if ( !$attachments )
            return array( array(), 0, 0 );
        $storage = class_exists( 'ticketsystemFiles' ) ? new ticketsystemFiles( $this->settings ) : null;
        if ( !$storage || !$storage->enabled() )
            return array( array(), count( $attachments ), 0 );
        $dir = eZSys::rootDir() . '/var/ticketsystem/tmp';
        if ( !is_dir( $dir ) )
            eZDir::mkdir( $dir, 0750, true );
        $files = array();
        $refused = 0;
        foreach ( array_values( $attachments ) as $i => $a )
        {
            if ( $i >= $storage->maxFiles() )
            {
                $refused++;
                continue;
            }
            $path = $dir . '/' . bin2hex( random_bytes( 12 ) );
            file_put_contents( $path, $a['data'] );
            @chmod( $path, 0600 );
            register_shutdown_function( function () use ( $path ) { if ( is_file( $path ) ) unlink( $path ); } );
            $name = self::cleanName( $a['name'] );
            if ( $storage->check( $name, $path, strlen( $a['data'] ) ) !== '' )
            {
                $refused++;
                continue;
            }
            $files[] = array( 'name' => $name, 'path' => $path, 'size' => strlen( $a['data'] ) );
        }
        return array( $files, 0, $refused );
    }

    /** Keeps the files of a mail for staff while the help desk has no attachment storage (HoldDirectory, private). */
    protected function hold( array $attachments, $number, $postId )
    {
        $base = $this->path( 'HoldDirectory', 'var/ticketsystem/mailin-files' );
        if ( $base === '' || !$attachments )
            return;
        $dir = $base . '/' . preg_replace( '/[^A-Za-z0-9-]/', '', $number ) . '/' . (int)$postId;
        if ( !is_dir( $dir ) )
            eZDir::mkdir( $dir, 0750, true );
        foreach ( array_values( $attachments ) as $i => $a )
        {
            $file = $dir . '/' . ( $i + 1 ) . '-' . self::cleanName( $a['name'] );
            if ( @file_put_contents( $file, $a['data'] ) !== false )
                @chmod( $file, 0600 );
        }
    }

    /** A file name without paths or odd characters (pure). */
    public static function cleanName( $name )
    {
        $name = basename( str_replace( '\\', '/', (string)$name ) );
        $name = preg_replace( '/[^\pL\pN._ -]+/u', '_', $name );
        $name = trim( $name, ' ._' );
        return $name === '' ? 'attachment' : mb_substr( $name, 0, 120 );
    }

    // ------------------------------------------------------------------------------------------- the poll

    /** The mailbox settings of the poll ([MailInSettings] Protocol ... Folder). */
    public function mailboxConfig()
    {
        $v = function ( $n, $d = '' ) { return (string)$this->settings->value( 'MailInSettings', $n, $d ); };
        return array( 'protocol' => $v( 'Protocol', 'imap' ), 'host' => $v( 'Host' ), 'port' => (int)$v( 'Port', '0' ),
                      'encryption' => $v( 'Encryption', 'ssl' ), 'user' => $v( 'User' ), 'password' => $v( 'Password' ),
                      'folder' => $v( 'Folder', 'INBOX' ), 'timeout' => 30,
                      'verify_peer' => strtolower( $v( 'VerifyPeer', 'enabled' ) ) !== 'disabled' );
    }

    /**
     * The IMAP search keys of [MailInSettings] TicketMailOnly (enabled by default): only mail that looks like a reply
     * to a ticket mail is fetched (a reference in In-Reply-To or in a "+" address, or a ticket number in the subject),
     * so a shared support mailbox keeps every other mail untouched and unread. '' when disabled.
     */
    public function ticketMailSearch()
    {
        if ( !$this->enabled( 'TicketMailOnly', 'enabled' ) || $this->enabled( 'OpenNewTickets' ) )
            return '';
        $prefix = ticketsystemNumber::cleanPrefix( $this->settings->value( 'NumberSettings', 'Prefix', 'TS' ) );
        return 'OR OR HEADER In-Reply-To "<ts." TO "+ts." SUBJECT ' . ticketsystemMailbox::quote( $prefix . '-' );
    }

    /**
     * Fetches and handles the waiting mail of the mailbox (the cronjob part ticketsystem_mailin).
     *
     * @return array[] one result per mail
     * @throws RuntimeException when the mailbox cannot be read
     */
    public function poll( $limit = 50, ?ticketsystemMailbox $box = null )
    {
        $config = $this->mailboxConfig();
        if ( !$box && $config['host'] === '' )
            throw new RuntimeException( 'no mailbox ([MailInSettings] Host)' );
        $box = $box ?: new ticketsystemMailbox( $config );
        $done = (string)$this->settings->value( 'MailInSettings', 'DoneFolder', '' );
        $out = array();
        try
        {
            foreach ( $box->waiting( $limit, $this->ticketMailSearch() ) as $id )
            {
                // one message that fails stops only itself: it stays unread (fetched with PEEK, not marked done)
                // and is read again at the next run; the others are read now
                try
                {
                    $r = $this->handle( $box->fetch( $id ) );
                }
                catch ( Throwable $e )
                {
                    eZDebug::writeError( 'Mail-in message ' . (int)$id . ': ' . $e->getMessage(), __METHOD__ );
                    $out[] = $this->result( 'failed', substr( $e->getMessage(), 0, 300 ) );
                    continue;
                }
                if ( $r['result'] !== 'disabled' )
                    $box->done( $id, $done );
                $out[] = $r;
            }
        }
        finally
        {
            $box->close();
        }
        return $out;
    }
}
