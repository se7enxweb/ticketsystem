<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Automatic tickets (doc/auto-tickets.md, [AutoTicketSettings]): another extension, a cronjob or a script reports a
 * problem with a key, and the help desk opens a ticket for it or adds to the open one of the same key, so a problem
 * that repeats is one ticket, not a ticket per occurrence.
 *
 * - **Internal** (raise()): opened by the help desk user ([AutoTicketSettings] OpenerUserID) in the account
 *   "internal:auto", which no customer has: only staff read it. Source "auto", external_id the key. While a ticket of
 *   the key is not resolved, a repeat is added to it as a post (the ticket goes back to "waiting on staff"); after it
 *   was resolved, the next occurrence opens a new ticket.
 * - **Customer-visible** (raiseForCustomer()): opened for a customer by the help desk user as staff (the customer is
 *   the opener, the ticket in their account's list, the opening post mailed to them), once per key.
 *
 * Runs without a signed-in user (the opener's own policies apply). Never throws: array( ok, message, ticket, created,
 * appended ).
 */
class ticketsystemAutoTicket
{
    const ACCOUNT = 'internal:auto';
    const SOURCE = 'auto';

    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ?ticketsystemSettings $settings = null )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    public function enabled()
    {
        return $this->settings->enabled( 'AutoTicketSettings', 'Enabled', 'enabled' );
    }

    /** A key as stored (pure): lower case, [a-z0-9:._-], at most 64 characters; '' when nothing is left. */
    public static function cleanKey( $key )
    {
        $key = strtolower( preg_replace( '/[^A-Za-z0-9:._-]+/', '-', trim( (string)$key ) ) );
        return substr( trim( $key, '-' ), 0, 64 );
    }

    /** The help desk user who opens the automatic tickets. */
    public function opener()
    {
        $id = (int)$this->settings->value( 'AutoTicketSettings', 'OpenerUserID', 14 );
        $u = $id > 0 ? eZUser::fetch( $id ) : null;
        return $u instanceof eZUser && $u->isEnabled() ? $u : null;
    }

    protected static function result( $ok, $message, $ticket = null, $created = false, $appended = false )
    {
        return array( 'ok' => (bool)$ok, 'message' => (string)$message, 'ticket' => $ticket, 'created' => (bool)$created, 'appended' => (bool)$appended );
    }

    /** The ticket of a key that is still being worked on (not resolved, not merged), or null. */
    public static function activeFor( $key, $source = self::SOURCE, ?ticketsystemSettings $settings = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        $db = eZDB::instance();
        $q = ticketsystemDesk::quoter();
        $active = ticketsystemDesk::statusCondition( $settings->statusSet()->active(), $q );
        $where = "source = '" . $db->escapeString( (string)$source ) . "' AND external_id = '" . $db->escapeString( self::cleanKey( $key ) ) . "' AND merged_into = 0"
               . ( $active !== '' ? ' AND ' . $active : '' );
        $rows = ticketsystemTicket::fetchWhere( $where, 0, 1, 'id DESC' );
        return $rows ? $rows[0] : null;
    }

    /** Any ticket of a key and source (the once-per-key customer tickets), or null. */
    public static function anyFor( $key, $source )
    {
        $db = eZDB::instance();
        $rows = ticketsystemTicket::fetchWhere( "source = '" . $db->escapeString( (string)$source ) . "' AND external_id = '" . $db->escapeString( self::cleanKey( $key ) ) . "'", 0, 1, 'id DESC' );
        return $rows ? $rows[0] : null;
    }

    /**
     * Opens an internal ticket for a key, or adds to the open one.
     *
     * @param string $key e.g. katalyst-job:provisioning:3 (the problem and where: one ticket per key while open)
     * @param string $subject the subject of a new ticket
     * @param string $body what happened (plain text; never secrets, never customer data beyond ids)
     * @param array $o category, priority, subscription_id
     */
    public function raise( $key, $subject, $body, array $o = array() )
    {
        $key = self::cleanKey( $key );
        if ( !$this->enabled() )
            return self::result( false, 'automatic tickets are disabled ([AutoTicketSettings] Enabled)' );
        if ( $key === '' )
            return self::result( false, 'no key' );
        $opener = $this->opener();
        if ( !$opener )
            return self::result( false, 'no help desk user ([AutoTicketSettings] OpenerUserID)' );
        try
        {
            $service = new ticketsystemService( new ticketsystemAccess( $opener ), $this->settings );
            $open = self::activeFor( $key, self::SOURCE, $this->settings );
            if ( $open )
            {
                $r = $service->reply( $open, (string)$body );
                return self::result( $r['ok'], $r['ok'] ? sprintf( 'added to ticket %s', $open->attribute( 'number' ) ) : $r['message'], $open, false, $r['ok'] );
            }
            $r = $this->open( $service, $opener, self::ACCOUNT, $subject, $body, $o, array(
                'creator_id' => (int)$opener->attribute( 'contentobject_id' ), 'notify' => $this->settings->enabled( 'AutoTicketSettings', 'Notify', 'enabled' ),
                'staff_post' => false, 'source' => self::SOURCE, 'external_id' => $key, 'message' => 'opened ticket %s' ) );
            return self::result( $r['ok'], $r['message'], isset( $r['ticket'] ) ? $r['ticket'] : null, $r['ok'] );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeError( 'automatic ticket ' . $key . ': ' . $e->getMessage(), __METHOD__ );
            return self::result( false, 'failed: ' . get_class( $e ) );
        }
    }

    /**
     * Opens a ticket for a customer once per key (the customer is the opener; the help desk user writes as staff; the
     * opening post is mailed to the customer). A second call with the same key changes nothing.
     *
     * @param int $customerUserId the customer's user
     * @param string $accountKey the account the ticket belongs to (e.g. katalyst:<customer id>); must be one of the user's
     */
    public function raiseForCustomer( $key, $customerUserId, $accountKey, $subject, $body, array $o = array() )
    {
        $key = self::cleanKey( $key );
        if ( !$this->enabled() || !$this->settings->enabled( 'AutoTicketSettings', 'CustomerTickets', 'enabled' ) )
            return self::result( false, 'customer tickets are disabled ([AutoTicketSettings] CustomerTickets)' );
        $opener = $this->opener();
        $customer = eZUser::fetch( (int)$customerUserId );
        if ( $key === '' || !$opener || !$customer instanceof eZUser || !$customer->isEnabled() )
            return self::result( false, 'no key, no help desk user or no customer user' );
        if ( !in_array( (string)$accountKey, ( new ticketsystemAccount() )->keysFor( (int)$customerUserId ), true ) )
            return self::result( false, 'the account is not one of the customer user\'s' );
        $existing = self::anyFor( $key, 'autocustomer' );
        if ( $existing )
            return self::result( true, sprintf( 'ticket %s exists already', $existing->attribute( 'number' ) ), $existing );
        try
        {
            $service = new ticketsystemService( new ticketsystemAccess( $opener ), $this->settings );
            $r = $this->open( $service, $customer, (string)$accountKey, $subject, $body, $o, array(
                'creator_id' => (int)$opener->attribute( 'contentobject_id' ), 'notify' => true, 'staff_post' => true,
                'source' => 'autocustomer', 'external_id' => $key, 'message' => 'opened ticket %s for the customer' ) );
            return self::result( $r['ok'], $r['message'], isset( $r['ticket'] ) ? $r['ticket'] : null, $r['ok'] );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeError( 'automatic customer ticket ' . $key . ': ' . $e->getMessage(), __METHOD__ );
            return self::result( false, 'failed: ' . get_class( $e ) );
        }
    }

    protected function open( ticketsystemService $service, eZUser $opener, $accountKey, $subject, $body, array $o, array $create )
    {
        $cats = $this->settings->categories();
        $prios = $this->settings->priorities();
        $cat = isset( $o['category'] ) && isset( $cats[$o['category']] ) ? $o['category'] : (string)$this->settings->value( 'AutoTicketSettings', 'Category', '' );
        $prio = isset( $o['priority'] ) && isset( $prios[$o['priority']] ) ? $o['priority'] : (string)$this->settings->value( 'AutoTicketSettings', 'Priority', '' );
        list( $errors, $v ) = $service->validateNew( array( 'subject' => mb_substr( (string)$subject, 0, 200 ), 'body' => (string)$body,
                                                            'category' => $cat, 'priority' => $prio,
                                                            'subscription_id' => isset( $o['subscription_id'] ) ? (int)$o['subscription_id'] : 0 ) );
        if ( $errors )
            return array( 'ok' => false, 'message' => implode( ' ', $errors ) );
        return ticketsystemAutoTicketOpener::createWith( $service, $opener, $accountKey, $v, $create );
    }
}

/** Gives ticketsystemAutoTicket the service's create() (protected: callers check access themselves). */
class ticketsystemAutoTicketOpener extends ticketsystemService
{
    public static function createWith( ticketsystemService $service, eZUser $opener, $accountKey, array $v, array $o )
    {
        return $service->create( $opener, $accountKey, $v, $o );
    }
}
