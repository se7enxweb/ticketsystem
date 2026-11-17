<?php
/**
 * The administration's styling rules for the templates of ticketsystem (AGENTS.md "Admin design and template styling
 * rules"; the kernel's tests/tests/design/AdminStylingRulesTest.php): no <style> block and no style="" attribute in
 * any template, and the admin stylesheets of the extension use tokens only (no colour or radius of their own).
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemAdminStylingTest extends TestCase
{
    public static function inlineCssCount( $source )
    {
        $source = preg_replace( '/\{\*.*?\*\}/s', '', $source );
        return preg_match_all( '/<style\b/i', $source ) + preg_match_all( '/(?<![\w.\-])style\s*=\s*["\'{]/i', $source );
    }

    public function testNoInlineCssInAnyTemplate()
    {
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../design', FilesystemIterator::SKIP_DOTS ) );
        $bad = array();
        $n = 0;
        foreach ( $files as $f )
        {
            if ( substr( $f->getFilename(), -4 ) !== '.tpl' )
                continue;
            $n++;
            if ( self::inlineCssCount( file_get_contents( $f->getPathname() ) ) )
                $bad[] = substr( $f->getPathname(), strlen( __DIR__ . '/../' ) );
        }
        $this->assertGreaterThan( 10, $n );
        $this->assertSame( array(), $bad, 'templates with a <style> block or a style="" attribute' );
    }

    /** @return string[] path => source of every template of the admin designs (comments removed) */
    protected static function adminTemplates()
    {
        $out = array();
        foreach ( glob( __DIR__ . '/../design/admin*', GLOB_ONLYDIR ) ?: array() as $design )
            foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $design, FilesystemIterator::SKIP_DOTS ) ) as $f )
                if ( substr( $f->getFilename(), -4 ) === '.tpl' )
                    $out[substr( $f->getPathname(), strlen( __DIR__ . '/../' ) )] = preg_replace( '/\{\*.*?\*\}/s', '', file_get_contents( $f->getPathname() ) );
        ksort( $out );
        return $out;
    }

    /**
     * The admin templates draw with the shared components only: no colour written out, no version query on an asset
     * link, none of the customer frame's classes (ts-*, ktl-*), no row header in a table (an .a4-table th is a column
     * header: upper case and unbroken, so a subject in it ran out of the card) and every fact of an .a4-facts list as
     * a <div> with its dt and dd (the card's "Label: value" layout reads the pairs, bare dt/dd ran into one line).
     */
    public function testAdminTemplatesUseTheSharedComponentsOnly()
    {
        $bad = array();
        foreach ( self::adminTemplates() as $path => $src )
        {
            if ( preg_match( '/(?<![&\w])#[0-9a-f]{3}(?:[0-9a-f]{3})?\b(?![\w-])|\b(?:rgba?|hsla?)\s*\(/i', $src, $m ) )
                $bad[] = "$path: a colour of its own ({$m[0]})";
            if ( preg_match( '/\.(?:css|js)\?v=/i', $src ) )
                $bad[] = "$path: a version query on an asset link";
            if ( preg_match( '/class="[^"]*\b(?:ts|ktl)-[a-z]/', $src, $m ) )
                $bad[] = "$path: a class of the customer frame";
            if ( preg_match( '/<th\s+scope="row"/i', $src ) )
                $bad[] = "$path: a row header in a table";
            if ( preg_match( '/<dl\b[^>]*a4-facts[^>]*>\s*<dt|<\/dd>\s*<dt/i', $src ) )
                $bad[] = "$path: a fact without its <div>";
        }
        $this->assertGreaterThan( 10, count( self::adminTemplates() ) );
        $this->assertSame( array(), $bad );
    }

    public function testListNamesFollowTheLeftMenu()
    {
        $this->assertSame( 'Not resolved', ticketsystemDesk::listName( array() ) );
        $this->assertSame( 'Not resolved', ticketsystemDesk::listName( array( 'status' => 'active' ) ) );
        $this->assertSame( 'Needs a staff reply', ticketsystemDesk::listName( array( 'reply' => 'staff' ) ) );
        $this->assertSame( 'All tickets', ticketsystemDesk::listName( array( 'status' => 'all' ) ) );
        $this->assertSame( 'Tickets', ticketsystemDesk::listName( array( 'status' => 'all', 'q' => 'x' ) ) );
        $this->assertSame( 'ticketsystem/list/(status)/active', ticketsystemDesk::menuUri( array() ) );
        $this->assertSame( 'ticketsystem/list/(reply)/staff', ticketsystemDesk::menuUri( array( 'reply' => 'staff', 'q' => 'x' ) ) );
        $this->assertSame( 'ticketsystem/list/(status)/done/(priority)/urgent', ticketsystemDesk::menuUri( array( 'priority' => 'urgent', 'status' => 'done' ) ) );
    }

    public function testAdminStylesheetsUseTokensOnly()
    {
        foreach ( glob( __DIR__ . '/../design/admin*/stylesheets/modules/*.css' ) ?: array() as $file )
        {
            $css = preg_replace( '#/\*.*?\*/#s', '', file_get_contents( $file ) );
            $this->assertDoesNotMatchRegularExpression( '/#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i', $css, basename( $file ) . ': a colour of its own' );
            $this->assertDoesNotMatchRegularExpression( '/border-radius\s*:\s*\d/i', $css, basename( $file ) . ': a radius of its own' );
        }
    }
}
