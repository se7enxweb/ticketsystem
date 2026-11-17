<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * Who may do what with a ticket (doc/permissions.md). The policy functions of the module ticketsystem:
 *
 * - read, reply: limitation Scope = 1 Own (the tickets the user opened), 2 Account (every ticket of the user's
 *   account, ticketsystemAccount), 3 All (support staff); without a limitation: All;
 * - create: open a ticket; manage: staff (set any status, post as staff);
 * - use, deligate: the original functions of ticketsystem 1.x, kept for existing roles.
 *
 * Ticket objects live below user nodes in the Users section, so content/read does not reach them; the views of
 * the module read them for the user after these checks.
 */
class ticketsystemAccess
{
    const OWN = 1;
    const ACCOUNT = 2;
    const ALL = 3;

    /** @var eZUser */
    protected $user;
    /** @var ticketsystemAccount */
    protected $account;
    /** @var array */
    protected $cache = array();

    public function __construct( eZUser $user, ?ticketsystemAccount $account = null )
    {
        $this->user = $user;
        $this->account = $account ?: new ticketsystemAccount();
    }

    public function user()
    {
        return $this->user;
    }

    public function userId()
    {
        return (int)$this->user->attribute( 'contentobject_id' );
    }

    public function account()
    {
        return $this->account;
    }

    public function isSignedIn()
    {
        return $this->user->isRegistered();
    }

    /**
     * The widest scope a policy result grants (pure): 0 none, else OWN, ACCOUNT or ALL.
     *
     * @param array $result eZUser::hasAccessTo()
     */
    public static function scopeOf( array $result )
    {
        $word = isset( $result['accessWord'] ) ? $result['accessWord'] : 'no';
        if ( $word === 'yes' )
            return self::ALL;
        if ( $word !== 'limited' )
            return 0;
        $best = 0;
        foreach ( isset( $result['policies'] ) ? (array)$result['policies'] : array() as $limits )
        {
            if ( !isset( $limits['Scope'] ) )
                return self::ALL;
            foreach ( (array)$limits['Scope'] as $v )
                $best = max( $best, min( self::ALL, max( 0, (int)$v ) ) );
        }
        return $best;
    }

    /** The scope of read or reply for this user. */
    public function scope( $function )
    {
        if ( !isset( $this->cache[$function] ) )
            $this->cache[$function] = $this->isSignedIn() ? self::scopeOf( $this->user->hasAccessTo( 'ticketsystem', $function ) ) : 0;
        return $this->cache[$function];
    }

    public function may( $function )
    {
        if ( !$this->isSignedIn() )
            return false;
        $r = $this->user->hasAccessTo( 'ticketsystem', $function );
        return isset( $r['accessWord'] ) && $r['accessWord'] !== 'no';
    }

    public function isStaff()
    {
        return $this->may( 'manage' );
    }

    /** Whether a scope reaches a ticket (pure). */
    public static function reaches( $scope, array $ticket, $userId, array $accountKeys )
    {
        if ( $scope >= self::ALL )
            return true;
        if ( $scope <= 0 )
            return false;
        if ( (int)$ticket['opener_id'] === (int)$userId )
            return true;
        return $scope >= self::ACCOUNT && in_array( (string)$ticket['account_key'], $accountKeys, true );
    }

    /** The values of a ticket the scope check needs. */
    protected static function facts( ticketsystemTicket $t )
    {
        return array( 'opener_id' => (int)$t->attribute( 'opener_id' ), 'account_key' => (string)$t->attribute( 'account_key' ) );
    }

    public function canRead( ticketsystemTicket $t )
    {
        return self::reaches( $this->scope( 'read' ), self::facts( $t ), $this->userId(), $this->keys() );
    }

    public function canReply( ticketsystemTicket $t )
    {
        return $this->canRead( $t ) && self::reaches( $this->scope( 'reply' ), self::facts( $t ), $this->userId(), $this->keys() );
    }

    public function keys()
    {
        if ( !isset( $this->cache['keys'] ) )
            $this->cache['keys'] = $this->account->keysFor( $this->userId() );
        return $this->cache['keys'];
    }

    /**
     * The SQL condition of the tickets this user may read, or null for none. Every value is cast or escaped here.
     *
     * @param string[] $statuses only these statuses (empty: all)
     */
    public function listConditions( array $statuses = array() )
    {
        $scope = $this->scope( 'read' );
        if ( $scope <= 0 )
            return null;
        $db = eZDB::instance();
        $parts = array();
        if ( $scope < self::ALL )
        {
            $or = array( 'opener_id=' . $this->userId() );
            if ( $scope >= self::ACCOUNT && $this->keys() )
                $or[] = 'account_key IN (' . implode( ',', array_map( function ( $k ) use ( $db ) { return "'" . $db->escapeString( $k ) . "'"; }, $this->keys() ) ) . ')';
            $parts[] = '(' . implode( ' OR ', $or ) . ')';
        }
        if ( $statuses )
            $parts[] = 'status IN (' . implode( ',', array_map( function ( $s ) use ( $db ) { return "'" . $db->escapeString( (string)$s ) . "'"; }, $statuses ) ) . ')';
        return $parts ? implode( ' AND ', $parts ) : '1=1';
    }
}
