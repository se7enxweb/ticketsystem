<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The form token of the help desk's POST forms: one random value per session, compared in constant time. It works
 * with or without the kernel's ezformtoken extension.
 */
class ticketsystemFormToken
{
    const FIELD = 'TicketsystemToken';
    const SESSION = 'ticketsystem_form_token';

    public static function token()
    {
        $t = eZSession::get( self::SESSION, '' );
        if ( !is_string( $t ) || strlen( $t ) !== 48 )
        {
            $t = bin2hex( random_bytes( 24 ) );
            eZSession::set( self::SESSION, $t );
        }
        return $t;
    }

    public static function valid( $value )
    {
        $t = eZSession::get( self::SESSION, '' );
        return is_string( $t ) && strlen( $t ) === 48 && is_string( $value ) && hash_equals( $t, $value );
    }
}
