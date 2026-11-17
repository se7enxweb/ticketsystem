<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Mails every post of a ticket (doc/mail.md): the opening post and every reply, staff's and the customer's own, to
 * each follower of the ticket (an Exponential subscription on the ticket node) who may still read it, and to the
 * poster, so everyone keeps an external record of the whole conversation. One mail per reader (no reader sees
 * another's address), multipart: a text part and an HTML part (the kernel's mail layout), both with the full post.
 * Threaded by Message-ID / References per ticket. Sender: [MailSettings] Sender.
 *
 * Mails to [MailSettings] CaptureDomains (e.g. *.invalid test users) are never sent: they are written to
 * CaptureDirectory. The log names the ticket and the counts, never an address.
 */
class ticketsystemMailer
{
    /** @var ticketsystemSettings */
    protected $settings;
    /** @var array[]|null captured mails (tests) */
    public static $capture = null;

    public function __construct( ?ticketsystemSettings $settings = null )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    public function sender()
    {
        $s = trim( (string)$this->settings->value( 'MailSettings', 'Sender', '' ) );
        if ( $s === '' )
        {
            $ini = eZINI::instance();
            $s = (string)$ini->variable( 'MailSettings', 'EmailSender' );
            if ( $s === '' )
                $s = (string)$ini->variable( 'MailSettings', 'AdminEmail' );
        }
        return $s;
    }

    public function siteUrl()
    {
        $u = trim( (string)$this->settings->value( 'MailSettings', 'SiteURL', '' ) );
        if ( $u === '' )
            $u = (string)eZINI::instance()->variable( 'SiteSettings', 'SiteURL' );
        $u = rtrim( $u, '/' );
        if ( $u !== '' && !preg_match( '#^https?://#i', $u ) )
            $u = 'https://' . $u;
        return $u;
    }

    public function brand()
    {
        $b = trim( (string)$this->settings->value( 'MailSettings', 'Brand', '' ) );
        return $b !== '' ? $b : (string)eZINI::instance()->variable( 'SiteSettings', 'SiteName' );
    }

    public function ticketUrl( ticketsystemTicket $t )
    {
        return $this->siteUrl() . '/ticketsystem/view/' . rawurlencode( (string)$t->attribute( 'number' ) );
    }

    /** Whether an address is captured instead of sent (pure on the given domains). */
    public static function capturedBy( $address, array $domains )
    {
        $address = strtolower( trim( (string)$address ) );
        foreach ( $domains as $d )
        {
            $d = strtolower( trim( (string)$d, " .\t" ) );
            if ( $d !== '' && ( substr( $address, -strlen( $d ) - 1 ) === '.' . $d || substr( $address, -strlen( $d ) - 1 ) === '@' . $d ) )
                return true;
        }
        return false;
    }

    public function captured( $address )
    {
        return self::$capture !== null || self::capturedBy( $address, (array)$this->settings->value( 'MailSettings', 'CaptureDomains', array() ) );
    }

    /**
     * The content of one post's mail (pure apart from the settings): subject, text, the layout's inputs.
     *
     * @param array $d number, subject, status, category, priority, author, author_is_staff, is_opening, own_copy,
     *                 paragraphs (string[]), url, brand, created
     */
    public static function content( array $d )
    {
        $number = (string)$d['number'];
        $subject = ( $d['is_opening'] ? '' : 'Re: ' ) . '[' . $number . '] ' . (string)$d['subject'];
        $who = (string)$d['author'] . ( $d['author_is_staff'] ? ' (' . $d['brand'] . ' support)' : '' );
        $intro = $d['is_opening'] ? sprintf( '%s opened ticket %s.', $who, $number ) : sprintf( '%s replied to ticket %s.', $who, $number );
        $notice = $d['own_copy'] ? 'This is a copy of your own message, for your records.' : '';
        $cc = !empty( $d['cc'] );
        if ( $cc )
            $notice = sprintf( 'You were added as CC to ticket %s, so you get a copy of every post.', $number );
        $rows = array(
            array( 'label' => 'Ticket', 'value' => $number ),
            array( 'label' => 'Subject', 'value' => (string)$d['subject'] ),
            array( 'label' => 'Status', 'value' => (string)$d['status'] ),
        );
        if ( (string)$d['category'] !== '' )
            $rows[] = array( 'label' => 'Category', 'value' => (string)$d['category'] );
        if ( (string)$d['priority'] !== '' )
            $rows[] = array( 'label' => 'Priority', 'value' => (string)$d['priority'] );
        $footer = sprintf( 'You get this mail because you follow ticket %s. To answer, open the ticket: %s', $number, (string)$d['url'] );
        $reference = isset( $d['reference'] ) && !$cc ? (string)$d['reference'] : '';
        if ( $cc )
            $footer = sprintf( 'You get this copy as a CC of ticket %s. To stop the copies, ask the person who added you, or the support team.', $number );
        else if ( $reference !== '' )
            $footer = sprintf( 'You get this mail because you follow ticket %s. Answer this mail, or open the ticket: %s', $number, (string)$d['url'] );
        $rate = isset( $d['rate_url'] ) ? (string)$d['rate_url'] : '';

        $text = ( $reference !== '' ? ticketsystemReplyToken::MARKER . "\n\n" : '' ) . $intro . "\n\n";
        if ( $notice !== '' )
            $text .= $notice . "\n\n";
        $text .= str_repeat( '-', 60 ) . "\n";
        $text .= implode( "\n\n", (array)$d['paragraphs'] ) . "\n";
        $text .= str_repeat( '-', 60 ) . "\n\n";
        foreach ( $rows as $r )
            $text .= str_pad( $r['label'] . ':', 10 ) . ' ' . $r['value'] . "\n";
        if ( $rate !== '' )
            $text .= "\nHow did we do? Rate this ticket: " . $rate . "\n";
        $text .= "\nView the ticket: " . (string)$d['url'] . "\n\n" . $footer . "\n";
        if ( $reference !== '' )
            $text .= 'Ticket reference: ' . $reference . "\n";

        $paragraphs = array();
        foreach ( (array)$d['paragraphs'] as $p )
        {
            $parts = array();
            foreach ( explode( "\n", (string)$p ) as $i => $line )
            {
                if ( $i > 0 )
                    $parts[] = array( 'break' => true );
                $parts[] = array( 'text' => $line );
            }
            $paragraphs[] = $parts;
        }
        return array( 'subject' => $subject, 'text' => $text, 'title' => $subject, 'intro' => $intro, 'notice' => $notice,
                      'body' => $paragraphs, 'rows' => $rows, 'url' => (string)$d['url'], 'footer' => $footer
                                . ( $reference !== '' ? ' Ticket reference: ' . $reference : '' ), 'rate_url' => $rate );
    }

    /** The HTML part: design:mail/layout.tpl when the kernel has it, else a plain page. */
    public function html( array $c )
    {
        try
        {
            $tpl = eZTemplate::factory();
            $tpl->setVariable( 'mail_title', $c['title'] );
            $tpl->setVariable( 'mail_preheader', $c['intro'] );
            $tpl->setVariable( 'mail_intro', $c['intro'] );
            $tpl->setVariable( 'mail_notice', $c['notice'] );
            $tpl->setVariable( 'mail_paragraphs', $c['body'] );
            $tpl->setVariable( 'mail_rows', $c['rows'] );
            $buttons = array( array( 'label' => 'View the ticket', 'url' => $c['url'], 'kind' => 'primary' ) );
            if ( !empty( $c['rate_url'] ) )
                $buttons[] = array( 'label' => 'Rate this ticket', 'url' => $c['rate_url'], 'kind' => 'secondary' );
            $tpl->setVariable( 'mail_buttons', $buttons );
            $tpl->setVariable( 'mail_links_note', $c['footer'] );
            $tpl->setVariable( 'mail_site_name', $this->brand() );
            $html = (string)$tpl->fetch( 'design:mail/layout.tpl' );
            if ( trim( $html ) !== '' && stripos( $html, '<html' ) !== false )
                return $html;
        }
        catch ( Throwable $e )
        {
        }
        $h = function ( $s ) { return htmlspecialchars( (string)$s, ENT_QUOTES, 'UTF-8' ); };
        $out = '<!DOCTYPE html><html><body><h1>' . $h( $c['title'] ) . '</h1><p>' . $h( $c['intro'] ) . '</p>';
        if ( $c['notice'] !== '' )
            $out .= '<p><strong>' . $h( $c['notice'] ) . '</strong></p>';
        foreach ( $c['body'] as $parts )
        {
            $out .= '<p>';
            foreach ( $parts as $part )
                $out .= isset( $part['break'] ) ? '<br>' : $h( $part['text'] );
            $out .= '</p>';
        }
        $out .= '<table role="presentation">';
        foreach ( $c['rows'] as $r )
            $out .= '<tr><th align="left">' . $h( $r['label'] ) . '</th><td>' . $h( $r['value'] ) . '</td></tr>';
        $out .= '</table><p><a href="' . $h( $c['url'] ) . '">View the ticket</a></p><p>' . $h( $c['footer'] ) . '</p></body></html>';
        return $out;
    }

    /**
     * Mails one post.
     *
     * @param ticketsystemTicket $t
     * @param eZContentObject $post the ticket object (opening post) or a post object
     * @param int $posterId
     * @param bool $isOpening
     * @param bool $posterIsStaff
     * @return array sent, captured, failed, skipped (counts)
     */
    public function sendPost( ticketsystemTicket $t, eZContentObject $post, $posterId, $isOpening, $posterIsStaff )
    {
        $result = array( 'sent' => 0, 'captured' => 0, 'failed' => 0, 'skipped' => 0 );
        $set = $this->settings->statusSet();
        $cats = $this->settings->categories();
        $prios = $this->settings->priorities();
        $bodyAttr = $isOpening ? 'description' : 'comment';
        $map = $post->dataMap();
        $paragraphs = isset( $map[$bodyAttr] ) ? ticketsystemText::fromXml( $map[$bodyAttr]->attribute( 'data_text' ) ) : array();
        $author = eZContentObject::fetch( (int)$posterId );
        $base = array(
            'number' => (string)$t->attribute( 'number' ), 'subject' => (string)$t->attribute( 'subject' ),
            'status' => $set->label( $t->attribute( 'status' ) ),
            'category' => isset( $cats[$t->attribute( 'category' )] ) ? $cats[$t->attribute( 'category' )] : '',
            'priority' => isset( $prios[$t->attribute( 'priority' )] ) ? $prios[$t->attribute( 'priority' )] : '',
            'author' => $author ? (string)$author->attribute( 'name' ) : 'Someone', 'author_is_staff' => (bool)$posterIsStaff,
            'is_opening' => (bool)$isOpening, 'paragraphs' => $paragraphs, 'url' => $this->ticketUrl( $t ), 'brand' => $this->brand(),
        );
        $host = parse_url( $this->siteUrl(), PHP_URL_HOST ) ?: 'ticketsystem.invalid';
        $root = '<ticketsystem.' . strtolower( $base['number'] ) . '@' . $host . '>';
        $messageId = '<ticketsystem.' . strtolower( $base['number'] ) . '.' . (int)$post->attribute( 'id' ) . '@' . $host . '>';

        $seen = array();
        foreach ( $this->recipients( $t, $posterId ) as $r )
        {
            $address = strtolower( trim( $r['email'] ) );
            if ( $address === '' || isset( $seen[$address] ) )
            {
                $result['skipped']++;
                continue;
            }
            $seen[$address] = true;
            $reference = $r['user_id'] > 0 && $this->mailInEnabled() && $this->secret() !== '' ? ticketsystemReplyToken::reference( $base['number'], (int)$r['user_id'], $this->secret() ) : '';
            $rate = '';
            if ( $posterIsStaff && $r['user_id'] > 0 && (int)$r['user_id'] !== (int)$posterId && $set->isDone( $t->attribute( 'status' ) )
                 && $this->settings->enabled( 'CSATSettings', 'Enabled' ) && $this->settings->enabled( 'CSATSettings', 'AskInMail' )
                 && $this->isCustomer( $t, (int)$r['user_id'] ) )
                $rate = $base['url'] . '#ts-rate';
            $c = self::content( $base + array( 'own_copy' => (int)$r['user_id'] === (int)$posterId, 'reference' => $reference, 'rate_url' => $rate ) );
            $html = $this->html( $c );
            // reply by e-mail (doc/mail-in.md): the reader's mail carries the signed reference in its Message-ID too, which
            // every mail program names in In-Reply-To when the reader answers
            $id = $reference !== '' ? ticketsystemReplyToken::messageId( $base['number'], (int)$post->attribute( 'id' ), (int)$r['user_id'], $this->secret(), $host ) : $messageId;
            if ( $this->captured( $address ) )
            {
                $this->capture( $address, $c, $html, $id, $root, $reference !== '' ? ticketsystemReplyToken::replyAddress( $this->settings->value( 'MailInSettings', 'ReplyAddress', '' ), $base['number'], (int)$r['user_id'], $this->secret() ) : '' );
                $result['captured']++;
                continue;
            }
            $mail = new eZMail();
            $mail->setSender( $this->sender(), (string)$this->settings->value( 'MailSettings', 'SenderName', '' ) ?: false );
            $mail->setReceiver( $r['email'], $r['name'] !== '' ? $r['name'] : false );
            $reply = trim( (string)$this->settings->value( 'MailSettings', 'ReplyTo', '' ) );
            if ( $reference !== '' && ( $tokenAddress = ticketsystemReplyToken::replyAddress( $this->settings->value( 'MailInSettings', 'ReplyAddress', '' ), $base['number'], (int)$r['user_id'], $this->secret() ) ) !== '' )
                $reply = $tokenAddress;
            if ( $reply !== '' )
                $mail->setReplyTo( $reply );
            $mail->setSubject( $c['subject'] );
            $mail->setMessageID( $id );
            if ( !$isOpening )
            {
                $mail->setReferences( $root );
                $mail->addExtraHeader( 'In-Reply-To', $root );
            }
            $mail->addExtraHeader( 'X-Ticket-Number', $base['number'] );
            if ( class_exists( 'expMailTemplateBody' ) )
                expMailTemplateBody::setAlternative( $mail, $c['text'], $html );
            else
                $mail->setBody( $c['text'] );
            if ( eZMailTransport::send( $mail ) )
                $result['sent']++;
            else
                $result['failed']++;
        }
        // 1.2: a copy to every CC address (doc/watchers.md): no reply reference, no rating link, never an identity
        $result['cc'] = 0;
        foreach ( class_exists( 'ticketsystemWatch' ) ? ticketsystemWatch::ccRows( $t ) : array() as $ccRow )
        {
            $address = strtolower( trim( (string)$ccRow->attribute( 'email' ) ) );
            if ( $address === '' || isset( $seen[$address] ) || !eZMail::validate( $address ) )
            {
                $result['skipped']++;
                continue;
            }
            $seen[$address] = true;
            $c = self::content( $base + array( 'own_copy' => false, 'reference' => '', 'rate_url' => '', 'cc' => true ) );
            $html = $this->html( $c );
            if ( $this->captured( $address ) )
            {
                $this->capture( $address, $c, $html, $messageId, $root );
                $result['captured']++;
                $result['cc']++;
                continue;
            }
            $mail = new eZMail();
            $mail->setSender( $this->sender(), (string)$this->settings->value( 'MailSettings', 'SenderName', '' ) ?: false );
            $mail->setReceiver( $address );
            $reply = trim( (string)$this->settings->value( 'MailSettings', 'ReplyTo', '' ) );
            if ( $reply !== '' )
                $mail->setReplyTo( $reply );
            $mail->setSubject( $c['subject'] );
            $mail->setMessageID( $messageId );
            if ( !$isOpening )
            {
                $mail->setReferences( $root );
                $mail->addExtraHeader( 'In-Reply-To', $root );
            }
            $mail->addExtraHeader( 'X-Ticket-Number', $base['number'] );
            if ( class_exists( 'expMailTemplateBody' ) )
                expMailTemplateBody::setAlternative( $mail, $c['text'], $html );
            else
                $mail->setBody( $c['text'] );
            if ( eZMailTransport::send( $mail ) )
            {
                $result['sent']++;
                $result['cc']++;
            }
            else
                $result['failed']++;
        }
        eZLog::write( sprintf( 'ticket %s post %d: %d sent, %d captured, %d failed, %d skipped, %d cc', $base['number'], (int)$post->attribute( 'id' ),
                               $result['sent'], $result['captured'], $result['failed'], $result['skipped'], $result['cc'] ), 'ticketsystem.log' );
        return $result;
    }

    /**
     * The readers of a post: every user subscribed to the ticket node who may read the ticket, the poster (always),
     * then [MailSettings] StaffRecipients.
     *
     * @return array[] user_id, email, name
     */
    public function recipients( ticketsystemTicket $t, $posterId )
    {
        $ids = array( (int)$posterId );
        foreach ( (array)eZPersistentObject::fetchObjectList( eZSubtreeNotificationRule::definition(), array( 'user_id' ),
                                                                array( 'node_id' => (int)$t->attribute( 'node_id' ) ), null, null, false ) as $row )
            $ids[] = (int)$row['user_id'];
        $out = array();
        foreach ( array_unique( $ids ) as $id )
        {
            $user = eZUser::fetch( $id );
            if ( !$user instanceof eZUser || !$user->isEnabled() )
                continue;
            if ( $id !== (int)$posterId && !( new ticketsystemAccess( $user ) )->canRead( $t ) )
                continue;
            $object = $user->contentObject();
            $out[] = array( 'user_id' => $id, 'email' => (string)$user->attribute( 'email' ),
                            'name' => $object ? (string)$object->attribute( 'name' ) : '' );
        }
        foreach ( (array)$this->settings->value( 'MailSettings', 'StaffRecipients', array() ) as $address )
            if ( eZMail::validate( (string)$address ) )
                $out[] = array( 'user_id' => 0, 'email' => (string)$address, 'name' => '' );
        return $out;
    }

    protected function capture( $address, array $c, $html, $messageId, $root, $replyTo = '' )
    {
        $mail = array( 'to' => $address, 'from' => $this->sender(), 'subject' => $c['subject'], 'text' => $c['text'], 'html' => $html,
                       'message_id' => $messageId, 'references' => $root, 'reply_to' => $replyTo );
        if ( self::$capture !== null )
        {
            self::$capture[] = $mail;
            return;
        }
        $dir = trim( (string)$this->settings->value( 'MailSettings', 'CaptureDirectory', '' ) );
        if ( $dir === '' )
            return;
        if ( !is_dir( $dir ) )
            eZDir::mkdir( $dir, false, true );
        if ( !is_dir( $dir ) || !is_writable( $dir ) )
            return;
        $boundary = 'ts-' . bin2hex( random_bytes( 8 ) );
        $eml = "From: {$mail['from']}\nTo: $address\nSubject: {$c['subject']}\nMessage-ID: $messageId\nReferences: $root\n"
             . ( $replyTo !== '' ? "Reply-To: $replyTo\n" : '' )
             . "MIME-Version: 1.0\nContent-Type: multipart/alternative; boundary=\"$boundary\"\n\n"
             . "--$boundary\nContent-Type: text/plain; charset=utf-8\n\n{$c['text']}\n--$boundary\nContent-Type: text/html; charset=utf-8\n\n$html\n--$boundary--\n";
        file_put_contents( rtrim( $dir, '/' ) . '/' . date( 'Ymd-His' ) . '-' . preg_replace( '/[^A-Za-z0-9-]/', '', $c['subject'] === '' ? 'mail' : substr( md5( $messageId . $address ), 0, 10 ) ) . '.eml', $eml );
    }

    // ------------------------------------------------------------------------------------------- 1.1

    public function mailInEnabled()
    {
        return $this->settings->enabled( 'MailInSettings', 'Enabled', 'disabled' );
    }

    /** @var string|null */
    protected $secret = null;

    /**
     * The secret of the signed ticket references: [MailInSettings] TokenSecret, else a random one kept in the database
     * (ticketsystemReplyToken::secret()), the same in the web server and the mail-in process.
     */
    public function secret()
    {
        if ( $this->secret === null )
            $this->secret = ticketsystemReplyToken::secret( $this->settings );
        return $this->secret;
    }

    /** Captures a plain text mail (the mail-in notice) like a post's mail. */
    public function captureText( $address, $subject, $text )
    {
        $this->capture( $address, array( 'subject' => $subject, 'text' => $text ), '', '<ticketsystem.notice.' . bin2hex( random_bytes( 6 ) ) . '@ticketsystem.invalid>', '' );
    }

    /** Whether a user belongs to the ticket's customer side (the opener or a user of its account), not support staff. */
    protected function isCustomer( ticketsystemTicket $t, $userId )
    {
        if ( (int)$userId === (int)$t->attribute( 'opener_id' ) )
            return true;
        $u = eZUser::fetch( (int)$userId );
        if ( !$u instanceof eZUser )
            return false;
        $a = new ticketsystemAccess( $u );
        return !$a->isStaff() && in_array( (string)$t->attribute( 'account_key' ), $a->keys(), true );
    }

    /** One plain mail to users and addresses (notices and alerts to staff); captured like the posts. */
    protected function sendPlain( ticketsystemTicket $t, array $userIds, array $addresses, $subject, array $lines )
    {
        $sent = 0;
        $to = array();
        foreach ( array_unique( array_map( 'intval', $userIds ) ) as $id )
        {
            $u = eZUser::fetch( $id );
            if ( $u instanceof eZUser && $u->isEnabled() && ( new ticketsystemAccess( $u ) )->canRead( $t ) )
                $to[] = (string)$u->attribute( 'email' );
        }
        foreach ( $addresses as $a )
            if ( eZMail::validate( (string)$a ) )
                $to[] = (string)$a;
        $url = $this->ticketUrl( $t );
        $c = array( 'subject' => $subject, 'text' => implode( "\n", $lines ) . "\n\nView the ticket: $url\n", 'title' => $subject,
                    'intro' => (string)reset( $lines ), 'notice' => '', 'body' => array_map( function ( $l ) { return array( array( 'text' => $l ) ); }, $lines ),
                    'rows' => array( array( 'label' => 'Ticket', 'value' => (string)$t->attribute( 'number' ) ) ), 'url' => $url,
                    'footer' => 'You get this mail as support staff of this ticket.', 'rate_url' => '' );
        $host = parse_url( $this->siteUrl(), PHP_URL_HOST ) ?: 'ticketsystem.invalid';
        $id = '<ticketsystem.' . strtolower( (string)$t->attribute( 'number' ) ) . '.notice.' . bin2hex( random_bytes( 4 ) ) . '@' . $host . '>';
        foreach ( array_unique( array_map( 'strtolower', $to ) ) as $address )
        {
            $html = $this->html( $c );
            if ( $this->captured( $address ) )
            {
                $this->capture( $address, $c, $html, $id, '<ticketsystem.' . strtolower( (string)$t->attribute( 'number' ) ) . '@' . $host . '>' );
                $sent++;
                continue;
            }
            $mail = new eZMail();
            $mail->setSender( $this->sender(), (string)$this->settings->value( 'MailSettings', 'SenderName', '' ) ?: false );
            $mail->setReceiver( $address );
            $mail->setSubject( $subject );
            $mail->setMessageID( $id );
            $mail->addExtraHeader( 'X-Ticket-Number', (string)$t->attribute( 'number' ) );
            $mail->addExtraHeader( 'Auto-Submitted', 'auto-generated' );
            if ( class_exists( 'expMailTemplateBody' ) )
                expMailTemplateBody::setAlternative( $mail, $c['text'], $html );
            else
                $mail->setBody( $c['text'] );
            if ( eZMailTransport::send( $mail ) )
                $sent++;
        }
        eZLog::write( sprintf( 'ticket %s notice "%s": %d mail(s)', $t->attribute( 'number' ), $subject, $sent ), 'ticketsystem.log' );
        return $sent;
    }

    /** "Ticket ... was assigned to you" to the new assignee. */
    public function sendNotice( ticketsystemTicket $t, $userId, $kind )
    {
        $n = (string)$t->attribute( 'number' );
        return $this->sendPlain( $t, array( (int)$userId ), array(), sprintf( '[%s] Assigned to you: %s', $n, (string)$t->attribute( 'subject' ) ),
                                 array( sprintf( 'Ticket %s was assigned to you.', $n ) ) );
    }

    /** The escalation alert to the assignee (or the staff group) and [SLASettings] AlertRecipients. */
    public function sendAlert( ticketsystemTicket $t, $level, $why, array $userIds )
    {
        $n = (string)$t->attribute( 'number' );
        $what = $why === 'manual' ? 'was escalated by staff' : ( $level >= 2 ? 'missed its SLA target' : 'reached ' . ( new ticketsystemSla( $this->settings ) )->warnPercent() . ' % of its SLA time' );
        return $this->sendPlain( $t, $userIds, (array)$this->settings->value( 'SLASettings', 'AlertRecipients', array() ),
                                 sprintf( '[%s] Escalated: %s', $n, (string)$t->attribute( 'subject' ) ),
                                 array( sprintf( 'Ticket %s %s.', $n, $what ), sprintf( 'Escalation level: %d.', (int)$level ) ) );
    }
}
