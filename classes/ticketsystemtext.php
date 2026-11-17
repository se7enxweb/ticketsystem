<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The text of a post: what a customer types (plain text) to the XML of an ezxmltext attribute, and the stored XML
 * back to paragraphs of plain text for the mails. Pure.
 */
class ticketsystemText
{
    /** Line endings to \n, control characters out (tabs and new lines kept), trimmed. */
    public static function clean( $text )
    {
        $text = str_replace( array( "\r\n", "\r" ), "\n", (string)$text );
        $text = preg_replace( '/[^\P{C}\n\t]/u', '', $text );
        if ( $text === null )
            $text = '';
        return trim( $text );
    }

    /** Paragraphs (split at blank lines), each a list of lines. */
    public static function paragraphs( $text )
    {
        $out = array();
        foreach ( preg_split( '/\n[ \t]*\n+/', self::clean( $text ) ) as $p )
        {
            $lines = array_map( 'rtrim', explode( "\n", trim( $p, "\n" ) ) );
            if ( implode( '', $lines ) !== '' )
                $out[] = $lines;
        }
        return $out;
    }

    /** The ezxmltext XML of a plain text: one paragraph per block, a line element per line of a multi-line block. */
    public static function toXml( $text )
    {
        $doc = new DOMDocument( '1.0', 'utf-8' );
        $section = $doc->createElement( 'section' );
        $section->setAttribute( 'xmlns:image', 'http://ez.no/namespaces/ezpublish3/image/' );
        $section->setAttribute( 'xmlns:xhtml', 'http://ez.no/namespaces/ezpublish3/xhtml/' );
        $section->setAttribute( 'xmlns:custom', 'http://ez.no/namespaces/ezpublish3/custom/' );
        $doc->appendChild( $section );
        foreach ( self::paragraphs( $text ) as $lines )
        {
            $p = $doc->createElement( 'paragraph' );
            if ( count( $lines ) === 1 )
                $p->appendChild( $doc->createTextNode( $lines[0] ) );
            else
                foreach ( $lines as $line )
                {
                    $l = $doc->createElement( 'line' );
                    $l->appendChild( $doc->createTextNode( $line ) );
                    $p->appendChild( $l );
                }
            $section->appendChild( $p );
        }
        return $doc->saveXML();
    }

    /**
     * The paragraphs of stored ezxmltext XML as plain text (headings, list items, table cells and literal blocks
     * each a paragraph; line elements a line break; a link "text (url)" when the text is not the url).
     *
     * @return string[]
     */
    public static function fromXml( $xml )
    {
        $xml = (string)$xml;
        if ( trim( $xml ) === '' )
            return array();
        $doc = new DOMDocument();
        $old = libxml_use_internal_errors( true );
        $ok = $doc->loadXML( $xml, LIBXML_NONET );
        libxml_use_internal_errors( $old );
        if ( !$ok || !$doc->documentElement )
            return array( self::clean( strip_tags( $xml ) ) );
        $out = array();
        self::walk( $doc->documentElement, $out );
        return array_values( array_filter( array_map( 'trim', $out ), 'strlen' ) );
    }

    protected static function walk( DOMNode $node, array &$out )
    {
        foreach ( $node->childNodes as $child )
        {
            if ( !$child instanceof DOMElement )
                continue;
            if ( in_array( $child->nodeName, array( 'paragraph', 'header', 'li', 'td', 'th', 'literal' ), true ) && !self::hasBlocks( $child ) )
                $out[] = self::inline( $child );
            else
                self::walk( $child, $out );
        }
    }

    protected static function hasBlocks( DOMElement $e )
    {
        foreach ( $e->childNodes as $c )
            if ( $c instanceof DOMElement && in_array( $c->nodeName, array( 'paragraph', 'ul', 'ol', 'table', 'section', 'header' ), true ) )
                return true;
        return false;
    }

    protected static function inline( DOMNode $node )
    {
        $s = '';
        foreach ( $node->childNodes as $c )
        {
            if ( $c instanceof DOMText )
                $s .= $c->nodeValue;
            else if ( $c instanceof DOMElement && $c->nodeName === 'line' )
                $s .= ( $s !== '' ? "\n" : '' ) . self::inline( $c );
            else if ( $c instanceof DOMElement && $c->nodeName === 'link' )
            {
                $text = self::inline( $c );
                $url = $c->getAttribute( 'href' );
                if ( $url === '' && $c->getAttribute( 'url_id' ) !== '' && class_exists( 'eZURL' ) )
                    $url = (string)eZURL::url( (int)$c->getAttribute( 'url_id' ) );
                $s .= $url !== '' && $url !== $text ? $text . ' (' . $url . ')' : $text;
            }
            else if ( $c instanceof DOMElement )
                $s .= self::inline( $c );
        }
        return $s;
    }
}
