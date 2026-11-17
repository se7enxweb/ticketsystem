<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Readable ticket numbers like 7X-2026-00042 ([NumberSettings]): a gapless counter per prefix and year in
 * ticketsystem_counter, taken under a row lock so two tickets opened at the same moment never share a number.
 */
class ticketsystemNumber
{
    /**
     * The number for a counter value (pure).
     *
     * @param string $format with %prefix, %year and %seq
     * @param string $prefix
     * @param int $year
     * @param int $seq
     * @param int $digits
     * @return string
     */
    public static function format( $format, $prefix, $year, $seq, $digits = 5 )
    {
        $format = (string)$format !== '' ? (string)$format : '%prefix-%year-%seq';
        $out = strtr( $format, array( '%prefix' => self::cleanPrefix( $prefix ), '%year' => sprintf( '%04d', (int)$year ),
                                      '%seq' => str_pad( (string)(int)$seq, max( 1, min( 9, (int)$digits ) ), '0', STR_PAD_LEFT ) ) );
        return strtoupper( trim( $out, '-' ) );
    }

    /** A prefix of letters and digits only, at most 16 characters ('TS' when nothing is left). */
    public static function cleanPrefix( $prefix )
    {
        $p = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string)$prefix ) );
        return $p !== '' ? substr( $p, 0, 16 ) : 'TS';
    }

    /**
     * Takes the next value of the counter of $prefix and $year. Call inside the caller's transaction.
     *
     * @return int
     */
    public static function next( $prefix, $year )
    {
        $db = eZDB::instance();
        $p = $db->escapeString( self::cleanPrefix( $prefix ) );
        $y = (int)$year;
        $lock = $db->databaseName() === 'sqlite' ? '' : ' FOR UPDATE';
        $rows = $db->arrayQuery( "SELECT last_value FROM ticketsystem_counter WHERE prefix='$p' AND year=$y$lock" );
        if ( !$rows )
        {
            // the first ticket of the year; a ticket numbered by hand before the counter existed is respected
            $max = $db->arrayQuery( "SELECT MAX(seq) AS m FROM ticketsystem_ticket WHERE prefix='$p' AND year=$y" );
            $start = $max && $max[0]['m'] !== null ? (int)$max[0]['m'] : 0;
            $db->query( "INSERT INTO ticketsystem_counter ( prefix, year, last_value ) VALUES ( '$p', $y, $start )" );
            $rows = $db->arrayQuery( "SELECT last_value FROM ticketsystem_counter WHERE prefix='$p' AND year=$y$lock" );
        }
        $next = (int)$rows[0]['last_value'] + 1;
        $db->query( "UPDATE ticketsystem_counter SET last_value=$next WHERE prefix='$p' AND year=$y" );
        return $next;
    }

    /**
     * The next number from the settings: array( number, prefix, year, seq ).
     */
    public static function take( ?ticketsystemSettings $settings = null, $time = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        $prefix = self::cleanPrefix( $settings->value( 'NumberSettings', 'Prefix', 'TS' ) );
        $year = (int)date( 'Y', $time === null ? time() : (int)$time );
        $seq = self::next( $prefix, $year );
        return array( self::format( $settings->value( 'NumberSettings', 'Format', '%prefix-%year-%seq' ), $prefix, $year, $seq,
                                    (int)$settings->value( 'NumberSettings', 'Digits', 5 ) ), $prefix, $year, $seq );
    }
}
