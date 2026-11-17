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
