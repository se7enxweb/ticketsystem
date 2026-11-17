<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * A small MIME parser for inbound mail (reply by e-mail, doc/mail.md): headers (unfolded, RFC 2047 decoded), the
 * sender's address, the plain text of the message (text/plain preferred, else HTML turned into text), and the
 * attached files. Pure: no database, no extension of PHP beyond iconv/mbstring.
 */
class ticketsystemMailParser
{
    /**
     * @param string $raw the whole message as received
     * @return array headers (lower-case name => string; repeated headers joined by "\n"), from (address, lower case),
     *               from_name, subject, message_id, in_reply_to, references (string[]), to (string[] addresses),
     *               text, attachments (array of name, mime, data)
     */
    public static function parse( $raw )
    {
        $raw = str_replace( array( "\r\n", "\r" ), "\n", (string)$raw );
        list( $head, $body ) = self::split( $raw );
        $headers = self::headers( $head );
        $parts = array( 'text' => array(), 'html' => array(), 'attachments' => array() );
        self::walk( $headers, $body, $parts, 0 );
        $text = $parts['text'] ? implode( "\n\n", $parts['text'] ) : ( $parts['html'] ? self::htmlToText( implode( "\n", $parts['html'] ) ) : '' );
        list( $from, $fromName ) = self::address( isset( $headers['from'] ) ? $headers['from'] : '' );
        $to = array();
        foreach ( array( 'to', 'cc', 'delivered-to', 'x-original-to', 'envelope-to' ) as $h )
            if ( isset( $headers[$h] ) )
                foreach ( preg_split( '/[,\n]/', $headers[$h] ) as $one )
                {
                    list( $a ) = self::address( $one );
                    if ( $a !== '' )
                        $to[] = $a;
                }
        $refs = array();
        foreach ( array( 'in-reply-to', 'references' ) as $h )
            if ( isset( $headers[$h] ) && preg_match_all( '/<[^<>\s]+>/', $headers[$h], $m ) )
                $refs = array_merge( $refs, $m[0] );
        return array(
            'headers' => $headers, 'from' => $from, 'from_name' => $fromName,
            'subject' => isset( $headers['subject'] ) ? trim( $headers['subject'] ) : '',
            'message_id' => isset( $headers['message-id'] ) && preg_match( '/<[^<>\s]+>/', $headers['message-id'], $mm ) ? $mm[0] : '',
            'in_reply_to' => isset( $headers['in-reply-to'] ) ? trim( $headers['in-reply-to'] ) : '',
            'references' => array_values( array_unique( $refs ) ), 'to' => array_values( array_unique( $to ) ),
            'text' => trim( $text ), 'attachments' => $parts['attachments'],
        );
    }

    protected static function split( $raw )
    {
        $p = strpos( $raw, "\n\n" );
        return $p === false ? array( $raw, '' ) : array( substr( $raw, 0, $p ), substr( $raw, $p + 2 ) );
    }

    /** name (lower case) => decoded value */
    public static function headers( $head )
    {
        $out = array();
        $head = preg_replace( '/\n[ \t]+/', ' ', (string)$head );
        foreach ( explode( "\n", $head ) as $line )
        {
            if ( !preg_match( '/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $m ) )
                continue;
            $name = strtolower( $m[1] );
            $value = self::decodeHeader( $m[2] );
            $out[$name] = isset( $out[$name] ) ? $out[$name] . "\n" . $value : $value;
        }
        return $out;
    }

    public static function decodeHeader( $value )
    {
        $value = (string)$value;
        if ( strpos( $value, '=?' ) === false )
            return $value;
        if ( function_exists( 'iconv_mime_decode' ) )
        {
            $d = @iconv_mime_decode( $value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8' );
            if ( $d !== false )
                return $d;
        }
        return function_exists( 'mb_decode_mimeheader' ) ? mb_decode_mimeheader( $value ) : $value;
    }

    /** array( address (lower case), display name ) of "Name <a@b>" or "a@b" */
    public static function address( $value )
    {
        $value = trim( (string)$value );
        if ( preg_match( '/<([^<>\s]+@[^<>\s]+)>/', $value, $m ) )
            return array( strtolower( $m[1] ), trim( trim( substr( $value, 0, strpos( $value, '<' ) ) ), "\" " ) );
        if ( preg_match( '/([^\s<>"]+@[^\s<>"]+)/', $value, $m ) )
            return array( strtolower( trim( $m[1], '.,;' ) ), '' );
        return array( '', '' );
    }

    /** A header parameter such as boundary, charset, name or filename. */
    public static function param( $header, $name )
    {
        if ( preg_match( '/' . preg_quote( $name, '/' ) . '\*?\s*=\s*(?:"([^"]*)"|([^;\s]+))/i', (string)$header, $m ) )
        {
            $v = isset( $m[2] ) && $m[2] !== '' ? $m[2] : $m[1];
            if ( preg_match( "/^([A-Za-z0-9_-]+)'[^']*'(.*)$/", $v, $x ) )
                $v = self::charset( rawurldecode( $x[2] ), $x[1] );
            return self::decodeHeader( $v );
        }
        return '';
    }

    protected static function walk( array $headers, $body, array &$parts, $depth )
    {
        if ( $depth > 8 )
            return;
        $type = isset( $headers['content-type'] ) ? $headers['content-type'] : 'text/plain';
        $mime = strtolower( trim( explode( ';', $type )[0] ) );
        if ( strpos( $mime, 'multipart/' ) === 0 )
        {
            $boundary = self::param( $type, 'boundary' );
            if ( $boundary === '' )
                return;
            $chunks = preg_split( '/^--' . preg_quote( $boundary, '/' ) . '(?:--)?[ \t]*$/m', $body );
            array_shift( $chunks );
            foreach ( $chunks as $chunk )
            {
                $chunk = ltrim( $chunk, "\n" );
                if ( trim( $chunk ) === '' )
                    continue;
                list( $h, $b ) = self::split( $chunk );
                self::walk( self::headers( $h ), $b, $parts, $depth + 1 );
            }
            return;
        }
        $data = self::decodeBody( $body, isset( $headers['content-transfer-encoding'] ) ? $headers['content-transfer-encoding'] : '' );
        $disposition = isset( $headers['content-disposition'] ) ? $headers['content-disposition'] : '';
        $name = self::param( $disposition, 'filename' );
        if ( $name === '' )
            $name = self::param( $type, 'name' );
        $isAttachment = stripos( $disposition, 'attachment' ) === 0 || ( $name !== '' && strpos( $mime, 'text/' ) !== 0 ) || $mime === 'message/rfc822';
        if ( !$isAttachment && $mime === 'text/plain' )
            $parts['text'][] = rtrim( self::charset( $data, self::param( $type, 'charset' ) ) );
        else if ( !$isAttachment && $mime === 'text/html' )
            $parts['html'][] = self::charset( $data, self::param( $type, 'charset' ) );
        else if ( $data !== '' )
            $parts['attachments'][] = array( 'name' => $name !== '' ? $name : 'attachment', 'mime' => $mime, 'data' => $data );
    }

    public static function decodeBody( $body, $encoding )
    {
        $encoding = strtolower( trim( (string)$encoding ) );
        if ( $encoding === 'base64' )
            return (string)base64_decode( preg_replace( '/\s+/', '', $body ) );
        if ( $encoding === 'quoted-printable' )
            return quoted_printable_decode( $body );
        return $body;
    }

    public static function charset( $text, $charset )
    {
        $charset = strtoupper( trim( (string)$charset ) );
        if ( $charset === '' || $charset === 'UTF-8' || $charset === 'US-ASCII' )
            return mb_check_encoding( $text, 'UTF-8' ) ? $text : mb_convert_encoding( $text, 'UTF-8', 'ISO-8859-1' );
        $out = @iconv( $charset, 'UTF-8//TRANSLIT', $text );
        return $out === false ? mb_convert_encoding( $text, 'UTF-8', 'ISO-8859-1' ) : $out;
    }

    public static function htmlToText( $html )
    {
        $html = preg_replace( '#<(script|style|head)\b.*?</\1>#is', '', (string)$html );
        $html = preg_replace( '#<blockquote\b#i', "\n<blockquote", $html );
        $html = preg_replace( '#<br\s*/?>#i', "\n", $html );
        $html = preg_replace( '#</(p|div|li|tr|h[1-6]|blockquote)>#i', "\n\n", $html );
        $text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = preg_replace( "/[ \t]+\n/", "\n", $text );
        return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
    }
}
