<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The mails of the SLA and the satisfaction rating (doc/sla.md, doc/csat.md), on the sender, the layout and the
 * capture of ticketsystemMailer:
 *
 * - staff(): an escalation alert, to staff only (users who are support staff and may read the ticket, and the
 *   configured staff addresses), one mail per reader;
 * - rating(): the "How did we do?" mail to a customer, with one signed link per score.
 *
 * Both answer counts (recipients, sent, captured, failed) and log them without an address.
 */
class ticketsystemSlaMailer extends ticketsystemMailer
{
    /**
     * An escalation alert to staff.
     *
     * @param int[] $userIds staff users (others are left out)
     * @param string[] $addresses further staff addresses ([SLASettings] AlertRecipients)
     * @param string[] $lines the text, first line is the intro
     * @param array[] $rows label, value
     * @return array recipients, sent, captured, failed
     */
    public function staff( ticketsystemTicket $t, array $userIds, array $addresses, $subject, array $lines, array $rows = array() )
    {
        $to = array();
        foreach ( array_unique( array_map( 'intval', $userIds ) ) as $id )
        {
            $u = eZUser::fetch( $id );
            if ( !$u instanceof eZUser || !$u->isEnabled() )
                continue;
            $a = new ticketsystemAccess( $u );
            if ( $a->isStaff() && $a->canRead( $t ) )
                $to[] = strtolower( trim( (string)$u->attribute( 'email' ) ) );
        }
        foreach ( $addresses as $address )
            if ( eZMail::validate( trim( (string)$address ) ) )
                $to[] = strtolower( trim( (string)$address ) );
        $url = $this->ticketUrl( $t );
        $rows = array_merge( array( array( 'label' => 'Ticket', 'value' => (string)$t->attribute( 'number' ) ),
                                    array( 'label' => 'Subject', 'value' => (string)$t->attribute( 'subject' ) ) ), $rows );
        $text = implode( "\n\n", $lines ) . "\n\n";
        foreach ( $rows as $r )
            $text .= str_pad( $r['label'] . ':', 14 ) . ' ' . $r['value'] . "\n";
        $text .= "\nOpen the ticket: $url\n\nYou get this mail as support staff: the SLA escalation of this ticket.\n";
        $c = array( 'subject' => $subject, 'text' => $text, 'title' => $subject, 'intro' => (string)reset( $lines ), 'notice' => '',
                    'body' => array_map( function ( $l ) { return array( array( 'text' => $l ) ); }, array_slice( $lines, 1 ) ),
                    'rows' => $rows, 'url' => $url, 'footer' => 'You get this mail as support staff: the SLA escalation of this ticket.',
                    'rate_url' => '' );
        return $this->deliver( $t, array_values( array_unique( array_filter( $to ) ) ), $c, 'alert', 'escalation alert' );
    }

    /**
     * The rating mail to a customer.
     *
     * @param array[] $links score, label, url (best first)
     * @return array recipients, sent, captured, failed
     */
    public function rating( ticketsystemTicket $t, eZUser $user, array $links, $days )
    {
        $n = (string)$t->attribute( 'number' );
        $subject = sprintf( '[%s] How did we do? %s', $n, (string)$t->attribute( 'subject' ) );
        $intro = sprintf( 'Your ticket %s is solved. How happy are you with our help?', $n );
        $note = sprintf( 'One click is enough; you can add a comment on the next page. The links work once, for %d days, without signing in.', (int)$days );
        $text = $intro . "\n\n";
        foreach ( $links as $l )
            $text .= str_pad( $l['label'] . ':', 18 ) . ' ' . $l['url'] . "\n";
        $text .= "\n" . $note . "\n\nView the ticket: " . $this->ticketUrl( $t ) . "\n";
        $c = array( 'subject' => $subject, 'text' => $text, 'title' => $subject, 'intro' => $intro, 'notice' => '',
                    'body' => array( array( array( 'text' => $note ) ) ),
                    'rows' => array( array( 'label' => 'Ticket', 'value' => $n ), array( 'label' => 'Subject', 'value' => (string)$t->attribute( 'subject' ) ) ),
                    'url' => $this->ticketUrl( $t ), 'footer' => 'You get this mail once, because your ticket was solved.', 'rate_url' => '',
                    'buttons' => array_map( function ( $l ) { return array( 'label' => $l['label'], 'url' => $l['url'], 'kind' => $l['score'] >= 4 ? 'primary' : 'secondary' ); }, $links ) );
        $address = strtolower( trim( (string)$user->attribute( 'email' ) ) );
        return $this->deliver( $t, $address !== '' ? array( $address ) : array(), $c, 'rating', 'rating request' );
    }

    /** The HTML part with the content's own buttons (the rating links) when it has them. */
    public function html( array $c )
    {
        if ( empty( $c['buttons'] ) )
            return parent::html( $c );
        try
        {
            $tpl = eZTemplate::factory();
            $tpl->setVariable( 'mail_title', $c['title'] );
            $tpl->setVariable( 'mail_preheader', $c['intro'] );
            $tpl->setVariable( 'mail_intro', $c['intro'] );
            $tpl->setVariable( 'mail_notice', $c['notice'] );
            $tpl->setVariable( 'mail_paragraphs', $c['body'] );
            $tpl->setVariable( 'mail_rows', $c['rows'] );
            $tpl->setVariable( 'mail_buttons', $c['buttons'] );
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
        $out = '<!DOCTYPE html><html><body><h1>' . $h( $c['title'] ) . '</h1><p>' . $h( $c['intro'] ) . '</p><p>';
        foreach ( $c['buttons'] as $b )
            $out .= '<a href="' . $h( $b['url'] ) . '">' . $h( $b['label'] ) . '</a><br>';
        return $out . '</p><p>' . $h( $c['footer'] ) . '</p></body></html>';
    }

    /** Sends (or captures) one mail per address. */
    protected function deliver( ticketsystemTicket $t, array $to, array $c, $kind, $what )
    {
        $out = array( 'recipients' => count( $to ), 'sent' => 0, 'captured' => 0, 'failed' => 0 );
        $host = parse_url( $this->siteUrl(), PHP_URL_HOST ) ?: 'ticketsystem.invalid';
        $number = strtolower( (string)$t->attribute( 'number' ) );
        $root = '<ticketsystem.' . $number . '@' . $host . '>';
        foreach ( $to as $address )
        {
            $id = '<ticketsystem.' . $number . '.' . $kind . '.' . bin2hex( random_bytes( 4 ) ) . '@' . $host . '>';
            $html = $this->html( $c );
            if ( $this->captured( $address ) )
            {
                $this->capture( $address, $c, $html, $id, $root );
                $out['captured']++;
                continue;
            }
            $mail = new eZMail();
            $mail->setSender( $this->sender(), (string)$this->settings->value( 'MailSettings', 'SenderName', '' ) ?: false );
            $mail->setReceiver( $address );
            $mail->setSubject( $c['subject'] );
            $mail->setMessageID( $id );
            $mail->setReferences( $root );
            $mail->addExtraHeader( 'X-Ticket-Number', (string)$t->attribute( 'number' ) );
            $mail->addExtraHeader( 'Auto-Submitted', 'auto-generated' );
            if ( class_exists( 'expMailTemplateBody' ) )
                expMailTemplateBody::setAlternative( $mail, $c['text'], $html );
            else
                $mail->setBody( $c['text'] );
            if ( eZMailTransport::send( $mail ) )
                $out['sent']++;
            else
                $out['failed']++;
        }
        eZLog::write( sprintf( 'ticket %s %s: %d recipient(s), %d sent, %d captured, %d failed', $t->attribute( 'number' ), $what,
                               $out['recipients'], $out['sent'], $out['captured'], $out['failed'] ), 'ticketsystem.log' );
        return $out;
    }
}
