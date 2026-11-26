<?php
/**
 * PHP 8.4 and later deprecate a typed parameter made nullable only by its default (array $x = null): the extension
 * writes ?array $x = null, which PHP 8.0 reads as well. A source scan, DB-less.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x; All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemPhpCompatTest extends TestCase
{
    public function testNoImplicitlyNullableParameters()
    {
        $bad = array();
        $root = realpath( __DIR__ . '/..' );
        foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $f )
        {
            $path = $f->getPathname();
            if ( substr( $path, -4 ) !== '.php' || strpos( $path, '/vendor/' ) !== false || strpos( $path, '/.git/' ) !== false )
                continue;
            foreach ( file( $path ) as $n => $line )
                if ( preg_match( '/function\s+\w+\s*\(.*(?<![?\w\\\\])(?:array|[A-Z]\w*)\s+\$\w+\s*=\s*null/i', $line )
                     && preg_match_all( '/(\??)(?:array|[A-Za-z_]\w*)\s+\$\w+\s*=\s*null/i', $line, $m ) )
                    foreach ( $m[1] as $q )
                        if ( $q === '' )
                        {
                            $bad[] = substr( $path, strlen( $root ) + 1 ) . ':' . ( $n + 1 );
                            break;
                        }
        }
        $this->assertSame( array(), $bad, 'parameters made nullable only by their default' );
    }
}
