<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The account a ticket belongs to and is shared in ([AccountSettings] Resolver, doc/permissions.md).
 *
 * - owner: the opener alone, key user:<user id>;
 * - katalyst: the Katalyst customer of the user (the account the customer console acts for), key
 *   katalyst:<customer id>; every user linked to that customer reads its tickets with the policy limitation
 *   Scope "Account". A user without a customer falls back to user:<id>.
 * - auto: katalyst when the Katalyst extension is installed, else owner.
 *
 * The keys are taken from the signed-in user, never from the request.
 */
class ticketsystemAccount
{
    /** @var string */
    protected $mode;

    public function __construct( $mode = null )
    {
        $mode = $mode === null ? (string)ticketsystemSettings::instance()->value( 'AccountSettings', 'Resolver', 'auto' ) : (string)$mode;
        if ( $mode === 'auto' )
            $mode = self::katalystAvailable() ? 'katalyst' : 'owner';
        $this->mode = in_array( $mode, array( 'owner', 'katalyst' ), true ) ? $mode : 'owner';
        if ( $this->mode === 'katalyst' && !self::katalystAvailable() )
            $this->mode = 'owner';
    }

    /** Whether the Katalyst extension is active here (its classes alone can be in the autoload of any site). */
    public static function katalystAvailable()
    {
        static $active = null;
        if ( $active === null )
            $active = in_array( 'sevenx_katalyst', (array)eZExtension::activeExtensions(), true )
                   && class_exists( 'expKatalystAccessService' ) && class_exists( 'expKatalystCustomer' );
        return $active;
    }

    public function mode()
    {
        return $this->mode;
    }

    public static function userKey( $userId )
    {
        return 'user:' . (int)$userId;
    }

    /** The Katalyst customer the user acts for now (the console's account switcher), or null. */
    public function customerFor( $userId )
    {
        if ( $this->mode !== 'katalyst' || (int)$userId <= 0 )
            return null;
        $chosen = 0;
        if ( class_exists( 'eZSession' ) && class_exists( '\Exponential\View\Extension\SevenxKatalyst\Katalyst\CustomerView' ) )
            $chosen = \Exponential\View\Extension\SevenxKatalyst\Katalyst\CustomerView::chosenAccount();
        $c = expKatalystAccessService::customerForUser( (int)$userId, $chosen );
        return $c ? $c : null;
    }

    /** The key a new ticket of this user gets. */
    public function keyFor( $userId )
    {
        $c = $this->customerFor( $userId );
        return $c ? 'katalyst:' . (int)$c->attribute( 'id' ) : self::userKey( $userId );
    }

    /**
     * Every key whose tickets the user shares: its own and, with Katalyst, every customer it is linked to.
     *
     * @return string[]
     */
    public function keysFor( $userId )
    {
        $keys = array( self::userKey( $userId ) );
        if ( $this->mode === 'katalyst' && (int)$userId > 0 )
            foreach ( expKatalystAccessService::customerRowsForUser( (int)$userId ) as $row )
                $keys[] = 'katalyst:' . (int)$row['id'];
        return array_values( array_unique( $keys ) );
    }

    /** A short label of an account key for staff ("Customer 12", "User 34"). */
    public static function label( $key )
    {
        if ( preg_match( '/^katalyst:(\d+)$/', (string)$key, $m ) )
            return 'Customer ' . $m[1];
        if ( preg_match( '/^user:(\d+)$/', (string)$key, $m ) )
            return 'User ' . $m[1];
        return (string)$key;
    }
}
