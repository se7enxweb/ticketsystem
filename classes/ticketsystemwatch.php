<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Watchers and CC of a ticket (doc/watchers.md, [WatchSettings]).
 *
 * - **Watchers** are users following the ticket: an Exponential notification subscription on the ticket node, as the
 *   opener and every poster already get. Anyone who may read a ticket can follow or stop following it; staff can add
 *   and remove other staff members as watchers. A watcher who may no longer read the ticket gets no mail
 *   (ticketsystemMailer::recipients() checks every reader).
 * - **CC** addresses get a copy of every post of the ticket (never a note: notes are never mailed). Customers who may
 *   reply and staff manage them, at most [WatchSettings] MaxCC, optionally only in CCDomains[]. A CC address is only an
 *   address: it is never matched to a user, grants no access, and its mails carry no reply reference, so an answer from
 *   it never becomes a post.
 */
class ticketsystemWatch
{
    /** @var ticketsystemAccess */
    protected $access;
    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ticketsystemAccess $access, ?ticketsystemSettings $settings = null )
    {
        $this->access = $access;
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    protected static function fail( $message )
    {
        return array( 'ok' => false, 'message' => $message, 'errors' => array( $message ) );
    }

    protected static function ok( $message )
    {
        return array( 'ok' => true, 'message' => $message, 'errors' => array() );
    }

    /** The user ids subscribed to the ticket node. */
    public static function subscriberIds( ticketsystemTicket $t )
    {
        $ids = array();
        foreach ( (array)eZPersistentObject::fetchObjectList( eZSubtreeNotificationRule::definition(), array( 'user_id' ),
                                                                array( 'node_id' => (int)$t->attribute( 'node_id' ) ), null, null, false ) as $row )
            $ids[] = (int)$row['user_id'];
        return array_values( array_unique( $ids ) );
    }

    public function isFollowing( ticketsystemTicket $t, $userId = null )
    {
        return in_array( $userId === null ? $this->access->userId() : (int)$userId, self::subscriberIds( $t ), true );
    }

    /**
     * The watchers for staff: user_id, name, is_staff, may_read (a watcher who lost access gets no mail). Customers get
     * an empty list (they see only whether they follow, and the CC list).
     */
    public function watchers( ticketsystemTicket $t )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return array();
        $out = array();
        foreach ( self::subscriberIds( $t ) as $id )
        {
            $u = eZUser::fetch( $id );
            if ( !$u instanceof eZUser )
                continue;
            $a = new ticketsystemAccess( $u );
            $o = $u->contentObject();
            $out[] = array( 'user_id' => $id, 'name' => $o ? (string)$o->attribute( 'name' ) : '#' . $id, 'is_staff' => $a->isStaff(),
                            'may_read' => $u->isEnabled() && $a->canRead( $t ), 'is_opener' => $id === (int)$t->attribute( 'opener_id' ) );
        }
        usort( $out, function ( $a, $b ) { return ( $b['is_opener'] - $a['is_opener'] ) ?: strcmp( $a['name'], $b['name'] ); } );
        return $out;
    }

    /** The reader follows the ticket (gets every post by e-mail). */
    public function follow( ticketsystemTicket $t )
    {
        if ( !$this->access->canRead( $t ) )
            return self::fail( 'You cannot follow this ticket.' );
        if ( !ticketsystemService::subscribe( (int)$t->attribute( 'node_id' ), $this->access->userId() ) )
            return self::ok( 'You follow this ticket already.' );
        ticketsystemService::event( $t, $this->access->userId(), 'watch', '', (string)$this->access->userId() );
        return self::ok( 'You follow this ticket now: every post reaches you by e-mail.' );
    }

    /** The reader stops following the ticket. */
    public function unfollow( ticketsystemTicket $t )
    {
        if ( !$this->access->canRead( $t ) )
            return self::fail( 'You cannot change this ticket.' );
        if ( !$this->isFollowing( $t ) )
            return self::ok( 'You do not follow this ticket.' );
        eZSubtreeNotificationRule::removeByNodeAndUserID( $this->access->userId(), (int)$t->attribute( 'node_id' ) );
        ticketsystemService::event( $t, $this->access->userId(), 'unwatch', (string)$this->access->userId(), '' );
        return self::ok( 'You no longer get the posts of this ticket by e-mail.' );
    }

    /** Staff add a staff member as a watcher (the person must be staff and able to read the ticket). */
    public function addWatcher( ticketsystemTicket $t, $userId )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return self::fail( 'Only support staff can add watchers.' );
        $u = eZUser::fetch( (int)$userId );
        $a = $u instanceof eZUser ? new ticketsystemAccess( $u ) : null;
        if ( !$a || !$u->isEnabled() || !$a->isStaff() || !$a->canRead( $t ) )
            return self::fail( 'Only members of the support staff who can read the ticket can watch it.' );
        if ( !ticketsystemService::subscribe( (int)$t->attribute( 'node_id' ), (int)$userId ) )
            return self::ok( 'This person watches the ticket already.' );
        ticketsystemService::event( $t, $this->access->userId(), 'watch', '', (string)(int)$userId );
        return self::ok( 'The watcher was added.' );
    }

    /** Staff remove a watcher who is staff (customers decide for themselves; the opener is never removed here). */
    public function removeWatcher( ticketsystemTicket $t, $userId )
    {
        if ( !$this->access->isStaff() || !$this->access->canRead( $t ) )
            return self::fail( 'Only support staff can remove watchers.' );
        $u = eZUser::fetch( (int)$userId );
        if ( (int)$userId === (int)$t->attribute( 'opener_id' ) || !$u instanceof eZUser || !( new ticketsystemAccess( $u ) )->isStaff() )
            return self::fail( 'Customers decide themselves whether they follow a ticket.' );
        if ( !$this->isFollowing( $t, (int)$userId ) )
            return self::ok( 'Nothing changed.' );
        eZSubtreeNotificationRule::removeByNodeAndUserID( (int)$userId, (int)$t->attribute( 'node_id' ) );
        ticketsystemService::event( $t, $this->access->userId(), 'unwatch', (string)(int)$userId, '' );
        return self::ok( 'The watcher was removed.' );
    }

    // ---------------------------------------------------------------------------------------------- CC

    public function maxCc()
    {
        return max( 0, (int)$this->settings->value( 'WatchSettings', 'MaxCC', 5 ) );
    }

    /** Whether the reader may add or remove CC addresses: everyone who may reply, while the ticket is not closed. */
    public function mayManageCc( ticketsystemTicket $t )
    {
        return $this->maxCc() > 0 && $this->access->canReply( $t ) && !$this->settings->statusSet()->isClosed( (string)$t->attribute( 'status' ) );
    }

    /**
     * Checks a CC address (pure): '' when it can be added, else the reason.
     *
     * @param string[] $existing the addresses of the ticket now (lower case)
     * @param string[] $domains allowed domains (empty: any)
     * @param string[] $refused the help desk's own addresses (never CC)
     */
    public static function checkCc( $email, array $existing, $max, array $domains, array $refused )
    {
        $email = strtolower( trim( (string)$email ) );
        if ( $email === '' || strlen( $email ) > 254 || !preg_match( '/^[^@\s<>"\',;]+@([a-z0-9-]+\.)+[a-z]{2,63}$/D', $email ) )
            return 'Please give one valid e-mail address.';
        if ( in_array( $email, $existing, true ) )
            return 'This address gets copies already.';
        if ( count( $existing ) >= (int)$max )
            return sprintf( 'A ticket can have at most %d CC addresses.', (int)$max );
        foreach ( $refused as $r )
        {
            $r = strtolower( trim( (string)$r ) );
            if ( $r !== '' && ( $email === $r || ( $r[0] === '@' && substr( $email, -strlen( $r ) ) === $r ) ) )
                return 'This address cannot be added.';
        }
        if ( $domains )
        {
            $domain = substr( strrchr( $email, '@' ), 1 );
            foreach ( $domains as $d )
            {
                $d = strtolower( trim( (string)$d, " .@\t" ) );
                if ( $d !== '' && ( $domain === $d || substr( $domain, -strlen( $d ) - 1 ) === '.' . $d ) )
                    return '';
            }
            return 'Only addresses of the allowed domains can be added.';
        }
        return '';
    }

    /** The CC rows of a ticket (ticketsystemCC), oldest first. */
    public static function ccRows( ticketsystemTicket $t )
    {
        return ticketsystemCC::fetchBy( array( 'ticket_id' => (int)$t->attribute( 'id' ) ), array( 'id' => 'asc' ), 0, 100 );
    }

    /** The CC addresses for the templates (readers of the ticket only): id, email. */
    public function ccList( ticketsystemTicket $t )
    {
        if ( !$this->access->canRead( $t ) )
            return array();
        $out = array();
        foreach ( self::ccRows( $t ) as $c )
            $out[] = array( 'id' => (int)$c->attribute( 'id' ), 'email' => (string)$c->attribute( 'email' ) );
        return $out;
    }

    /** The addresses never accepted as CC: the help desk's sender and reply addresses, [WatchSettings] RefusedCC[]. */
    protected function refused()
    {
        $out = (array)$this->settings->value( 'WatchSettings', 'RefusedCC', array() );
        $mailer = new ticketsystemMailer( $this->settings );
        $out[] = $mailer->sender();
        $out[] = (string)$this->settings->value( 'MailSettings', 'ReplyTo', '' );
        $reply = (string)$this->settings->value( 'MailInSettings', 'ReplyAddress', '' );
        if ( $reply !== '' && strpos( $reply, '@' ) !== false )
            $out[] = '@' . substr( strrchr( $reply, '@' ), 1 );
        return array_values( array_filter( $out ) );
    }

    public function addCc( ticketsystemTicket $t, $email )
    {
        if ( !$this->mayManageCc( $t ) )
            return self::fail( 'You cannot change the copies of this ticket.' );
        $existing = array();
        foreach ( self::ccRows( $t ) as $c )
            $existing[] = strtolower( (string)$c->attribute( 'email' ) );
        $why = self::checkCc( $email, $existing, $this->maxCc(), (array)$this->settings->value( 'WatchSettings', 'CCDomains', array() ), $this->refused() );
        if ( $why !== '' )
            return self::fail( $why );
        $c = ticketsystemCC::make( array( 'ticket_id' => (int)$t->attribute( 'id' ), 'email' => strtolower( trim( (string)$email ) ),
                                          'added_by' => $this->access->userId(), 'created' => time() ) );
        $c->store();
        // the history names the row, never the address
        ticketsystemService::event( $t, $this->access->userId(), 'cc', '', (string)$c->attribute( 'id' ) );
        return self::ok( 'The address gets a copy of every new post of this ticket.' );
    }

    public function removeCc( ticketsystemTicket $t, $id )
    {
        if ( !$this->mayManageCc( $t ) )
            return self::fail( 'You cannot change the copies of this ticket.' );
        $c = ticketsystemCC::fetchByID( (int)$id );
        if ( !$c || (int)$c->attribute( 'ticket_id' ) !== (int)$t->attribute( 'id' ) )
            return self::fail( 'This address cannot be found.' );
        $c->remove();
        ticketsystemService::event( $t, $this->access->userId(), 'uncc', (string)(int)$id, '' );
        return self::ok( 'The address no longer gets copies of this ticket.' );
    }
}
