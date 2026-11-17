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
     * Makes sure the counter row of $prefix and $year exists, outside any transaction (call it before the caller's
     * begin()). On MySQL/MariaDB two transactions that lock a missing row with SELECT ... FOR UPDATE and then insert it
     * deadlock each other (InnoDB's gap locks); with the row there, next() only locks and raises it. A row inserted by
     * another process at the same moment is no error (the second insert is refused by the primary key and ignored).
     * MongoDB makes its counter in next().
     */
    public static function prepare( $prefix, $year )
    {
        $db = eZDB::instance();
        if ( strpos( strtolower( (string)$db->databaseName() ), 'mongo' ) === 0 || $db->transactionCounter() > 0 )
            return;
        $p = $db->escapeString( self::cleanPrefix( $prefix ) );
        $y = (int)$year;
        if ( $db->arrayQuery( "SELECT last_value FROM ticketsystem_counter WHERE prefix='$p' AND year=$y" ) )
            return;
        $max = $db->arrayQuery( "SELECT MAX(seq) AS m FROM ticketsystem_ticket WHERE prefix='$p' AND year=$y" );
        $start = $max && $max[0]['m'] !== null ? (int)$max[0]['m'] : 0;
        ticketsystemSchema::tryQuery( $db, "INSERT INTO ticketsystem_counter ( prefix, year, last_value ) VALUES ( '$p', $y, $start )" );
    }

    /** prepare() for the counter take() will use. */
    public static function prepareTake( ?ticketsystemSettings $settings = null, $time = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        self::prepare( self::cleanPrefix( $settings->value( 'NumberSettings', 'Prefix', 'TS' ) ), (int)date( 'Y', $time === null ? time() : (int)$time ) );
    }

    /**
     * Takes the next value of the counter of $prefix and $year. Call inside the caller's transaction, after prepare().
     *
     * @return int
     */
    public static function next( $prefix, $year )
    {
        $db = eZDB::instance();
        if ( strpos( strtolower( (string)$db->databaseName() ), 'mongo' ) === 0 )
            return self::nextOnMongo( $db, self::cleanPrefix( $prefix ), (int)$year );
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
     * next() on MongoDB, which has no row lock (the driver drops FOR UPDATE): the counter document of $prefix and
     * $year is created once (from its highest ticket, as on the other databases) and then raised by an atomic $inc,
     * so two tickets opened at the same time never get the same number. The unique PRIMARY index (prefix, year) lets
     * only one of two first tickets create the document; the other one then raises it.
     *
     * @param expMongoDB $db
     * @param string $prefix a cleanPrefix()
     * @param int $year
     * @return int
     */
    protected static function nextOnMongo( $db, $prefix, $year )
    {
        $collection = $db->getClient()->selectCollection( $db->DB, 'ticketsystem_counter' );
        $filter = array( 'prefix' => (string)$prefix, 'year' => (int)$year );
        if ( $collection->findOne( $filter ) === null )
        {
            $max = $db->arrayQuery( "SELECT MAX(seq) AS m FROM ticketsystem_ticket WHERE prefix='" . $db->escapeString( $prefix ) . "' AND year=" . (int)$year );
            $start = $max && isset( $max[0]['m'] ) ? (int)$max[0]['m'] : 0;
            try
            {
                $collection->updateOne( $filter, array( '$setOnInsert' => $filter + array( 'last_value' => $start ) ), array( 'upsert' => true ) );
            }
            catch ( \MongoDB\Driver\Exception\ServerException $e )
            {
                // another process created it first (duplicate key on the PRIMARY index): its value is used; any
                // other error is one
                if ( (int)$e->getCode() !== 11000 && strpos( $e->getMessage(), 'E11000' ) === false )
                    throw $e;
            }
        }
        $result = $collection->findOneAndUpdate( $filter, array( '$inc' => array( 'last_value' => 1 ) ),
                                                 array( 'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER ) );
        if ( !isset( $result['last_value'] ) )
            throw new RuntimeException( "ticketsystem: the counter $prefix/$year could not be raised" );
        return (int)$result['last_value'];
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
