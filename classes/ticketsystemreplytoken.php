<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The signed reference of a ticket in its mails (reply by e-mail, doc/mail.md): "ts.<number>.<user id>.<signature>",
 * where the signature is an HMAC of the number and the reader's user id with [MailInSettings] TokenSecret. It stands
 * in the Reply-To address (support+<reference>@example.com, [MailInSettings] ReplyAddress) and in the text of every
 * mail, so a reply names its ticket and the person it was sent to. Also the stripping of quoted text from a reply.
 * Pure.
 */
class ticketsystemReplyToken
{
    const PATTERN = '/\bts\.([A-Za-z0-9]{1,16}-\d{4}-\d{1,9})\.(\d{1,10})\.([a-f0-9]{20})\b/i';
    const MARKER = '## Reply above this line ##';

    public static function signature( $number, $userId, $secret )
    {
        return substr( hash_hmac( 'sha256', strtoupper( trim( (string)$number ) ) . '|' . (int)$userId, (string)$secret ), 0, 20 );
    }

    public static function reference( $number, $userId, $secret )
    {
        return 'ts.' . strtoupper( (string)$number ) . '.' . (int)$userId . '.' . self::signature( $number, $userId, $secret );
    }

    /** The Reply-To address of a reader: %token in the setting is replaced by the reference ('' when no setting). */
    public static function replyAddress( $pattern, $number, $userId, $secret )
    {
        $pattern = trim( (string)$pattern );
        if ( $pattern === '' || strpos( $pattern, '%token' ) === false )
            return $pattern;
        return str_replace( '%token', self::reference( $number, $userId, $secret ), $pattern );
    }

    /**
     * The references found in the addresses and the text of a mail whose signature is valid.
     *
     * @param string[] $haystacks addresses, subject, text
     * @return array[] number, user_id
     */
    public static function find( array $haystacks, $secret )
    {
        $out = array();
        foreach ( $haystacks as $h )
            if ( preg_match_all( self::PATTERN, (string)$h, $m, PREG_SET_ORDER ) )
                foreach ( $m as $x )
                    if ( hash_equals( self::signature( $x[1], (int)$x[2], $secret ), strtolower( $x[3] ) ) )
                        $out[strtoupper( $x[1] ) . '|' . (int)$x[2]] = array( 'number' => strtoupper( $x[1] ), 'user_id' => (int)$x[2] );
        return array_values( $out );
    }

    /** The ticket number in a subject like "Re: [7X-2026-00042] ...", or ''. */
    public static function subjectNumber( $subject )
    {
        return preg_match( '/\[([A-Za-z0-9]{1,16}-\d{4}-\d{1,9})\]/', (string)$subject, $m ) ? strtoupper( $m[1] ) : '';
    }

    /**
     * The new text of a reply without what it quotes: everything from the reply marker, an attribution line ("On ...
     * wrote:", "Am ... schrieb ...:"), an "Original Message" or "From: ... Sent:" block, or a signature ("-- ") on, and
     * every line quoted with ">".
     */
    public static function stripQuoted( $text )
    {
        $text = str_replace( array( "\r\n", "\r" ), "\n", (string)$text );
        $lines = explode( "\n", $text );
        $out = array();
        $n = count( $lines );
        for ( $i = 0; $i < $n; $i++ )
        {
            $line = $lines[$i];
            $t = trim( $line );
            $two = $t . ' ' . ( $i + 1 < $n ? trim( $lines[$i + 1] ) : '' );
            if ( stripos( $t, trim( self::MARKER, '# ' ) ) !== false )
                break;
            $attribution = '/^(On|Am|Le|El|Il|Op)\s.{4,300}(wrote|schrieb|a écrit|escribió|ha scritto|schreef)[^:]{0,40}:\s*$/iu';
            if ( preg_match( $attribution, $t ) || preg_match( $attribution, $two ) )
                break;
            if ( preg_match( '/^-{2,}\s*(Original Message|Ursprüngliche Nachricht|Forwarded message)\s*-{2,}$/iu', $t ) )
                break;
            if ( preg_match( '/^(From|Von):\s.+/i', $t ) && $i + 1 < $n && preg_match( '/^(Sent|Gesendet|Date|Datum):\s/i', trim( $lines[$i + 1] ) ) )
                break;
            if ( $line === '-- ' || $t === '--' )
                break;
            if ( preg_match( '/^\s*>/', $line ) )
                continue;
            $out[] = rtrim( $line );
        }
        $text = trim( implode( "\n", $out ) );
        // the reference of our own mail, when the reply kept it above the marker
        $text = trim( preg_replace( '/^.*' . trim( self::PATTERN, '/i' ) . '.*$/mi', '', $text ) );
        return preg_replace( "/\n{3,}/", "\n\n", $text );
    }
}
