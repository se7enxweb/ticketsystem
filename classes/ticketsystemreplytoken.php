<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The signed reference of a ticket in its mails (reply by e-mail, doc/mail-in.md): "ts.<number>.<user id>.<signature>",
 * the signature an HMAC of the number and the reader's user id. Every ticket mail carries it in its Message-ID, which
 * a reply names in In-Reply-To and References, and, when [MailInSettings] ReplyAddress has %token, in its Reply-To
 * address (support+%token@example.com). So a reply names its ticket and the person the mail was sent to, and nobody
 * can make one up for another ticket or person.
 *
 * The text of every ticket mail starts with MARKER when reply by e-mail is on. Also the stripping of quoted text and
 * signatures from a reply. Pure apart from secret().
 */
class ticketsystemReplyToken
{
    const PATTERN = '/\bts\.([A-Za-z0-9]{1,16}-\d{4}-\d{1,9})\.(\d{1,10})\.([a-f0-9]{20})\b/i';
    const SITE_DATA = 'ticketsystem_reply_secret';
    /** The first line of the text of every ticket mail when reply by e-mail is on: a reply's new text stands above it */
    const MARKER = '## Reply above this line ##';

    public static function signature( $number, $userId, $secret )
    {
        return substr( hash_hmac( 'sha256', strtoupper( trim( (string)$number ) ) . '|' . (int)$userId, (string)$secret ), 0, 20 );
    }

    public static function reference( $number, $userId, $secret )
    {
        return 'ts.' . strtoupper( trim( (string)$number ) ) . '.' . (int)$userId . '.' . self::signature( $number, $userId, $secret );
    }

    /** The Message-ID of the mail of a post to one reader. */
    public static function messageId( $number, $postId, $userId, $secret, $host )
    {
        return '<' . self::reference( $number, $userId, $secret ) . '.' . (int)$postId . '@' . $host . '>';
    }

    /** The Reply-To address of a reader: %token in the pattern is replaced by the reference ('' when no %token). */
    public static function replyAddress( $pattern, $number, $userId, $secret )
    {
        $pattern = trim( (string)$pattern );
        if ( $pattern === '' || strpos( $pattern, '%token' ) === false )
            return '';
        return str_replace( '%token', self::reference( $number, $userId, $secret ), $pattern );
    }

    /**
     * The references with a valid signature found in the given strings (addresses, In-Reply-To, References,
     * subject, text).
     *
     * @param string[] $haystacks
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

    /** The ticket number in a subject ("Re: [7X-2026-00042] ..." or "Re: Ticket 7X-2026-00042 ..."), or ''. */
    public static function subjectNumber( $subject, $prefix = '' )
    {
        $p = $prefix !== '' ? preg_quote( (string)$prefix, '/' ) : '[A-Za-z0-9]{1,16}';
        return preg_match( '/(?<![A-Za-z0-9-])(' . $p . '-\d{4}-\d{1,9})(?![0-9])/i', (string)$subject, $m ) ? strtoupper( $m[1] ) : '';
    }

    /**
     * The new text of a reply without what it quotes: everything from an attribution line ("On ... wrote:",
     * "Am ... schrieb ...:"), an "Original Message" or "From: ... Sent:" block, or a signature ("-- ") on, and every
     * line quoted with ">"; a reference left in the text is taken out.
     */
    public static function stripQuoted( $text )
    {
        $text = str_replace( array( "\r\n", "\r" ), "\n", (string)$text );
        $lines = explode( "\n", $text );
        $out = array();
        $n = count( $lines );
        $attribution = '/^(On|Am|Le|El|Il|Op|Den)\s.{4,300}(wrote|schrieb|a écrit|escribió|ha scritto|schreef|skrev)[^:]{0,40}:\s*$/iu';
        for ( $i = 0; $i < $n; $i++ )
        {
            $line = $lines[$i];
            $t = trim( $line );
            $two = $t . ' ' . ( $i + 1 < $n ? trim( $lines[$i + 1] ) : '' );
            if ( stripos( $t, trim( self::MARKER, '# ' ) ) !== false )
                break;
            if ( preg_match( $attribution, $t ) || ( $t !== '' && preg_match( $attribution, $two ) ) )
                break;
            if ( preg_match( '/^-{2,}\s*(Original Message|Ursprüngliche Nachricht|Forwarded message|Weitergeleitete Nachricht)\s*-{2,}$/iu', $t ) )
                break;
            if ( preg_match( '/^_{10,}$/', $t ) )
                break;
            if ( preg_match( '/^\**(From|Von):\**\s.+/i', $t ) && $i + 1 < $n
                 && preg_match( '/^\**(Sent|Gesendet|Date|Datum|To|An):\**\s/i', trim( $lines[$i + 1] ) ) )
                break;
            if ( $line === '-- ' || $t === '--' )
                break;
            if ( preg_match( '/^(Sent from my |Von meinem .* gesendet|Get Outlook for )/i', $t ) )
                break;
            if ( preg_match( '/^\s*>/', $line ) )
                continue;
            $out[] = rtrim( $line );
        }
        $text = trim( implode( "\n", $out ) );
        $text = trim( preg_replace( '/^.*' . trim( self::PATTERN, '/i' ) . '.*$/mi', '', $text ) );
        return preg_replace( "/\n{3,}/", "\n\n", $text );
    }

    /**
     * The secret of the signatures: [MailInSettings] TokenSecret, else a random one made once and kept in the
     * database (ezsite_data ticketsystem_reply_secret), so it is the same in every process.
     */
    public static function secret( ?ticketsystemSettings $settings = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        $s = trim( (string)$settings->value( 'MailInSettings', 'TokenSecret', '' ) );
        if ( $s !== '' )
            return $s;
        if ( !class_exists( 'eZSiteData' ) )
            return '';
        $row = eZSiteData::fetchByName( self::SITE_DATA );
        if ( $row && strlen( (string)$row->attribute( 'value' ) ) >= 32 )
            return (string)$row->attribute( 'value' );
        $s = bin2hex( random_bytes( 32 ) );
        eZSiteData::createNew( self::SITE_DATA, $s );
        // another process may have made one at the same moment: the stored one wins
        $row = eZSiteData::fetchByName( self::SITE_DATA );
        return $row ? (string)$row->attribute( 'value' ) : $s;
    }
}
