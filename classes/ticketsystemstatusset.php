<?php
/**
 * @copyright Copyright (C) 2006 - 2007 xrow GbR, Hannover (Germany); Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * A status set ([StatusSet_<name>] in ticketsystem.ini): the statuses in order, their tones and the roles that say
 * which status a ticket gets when something happens (doc/statuses.md). Pure: no database.
 */
class ticketsystemStatusSet
{
    const ROLES = array( 'Default', 'CustomerReply', 'StaffReply', 'Resolved', 'Closed', 'Reopen' );
    const TONES = array( 'ok', 'warn', 'bad', 'info', 'muted' );

    /** @var string */
    public $name;
    /** @var array identifier => label */
    public $statuses;
    /** @var array identifier => tone */
    public $tones;
    /** @var array role => identifier */
    public $roles;
    /** @var string[] */
    public $done;
    /** @var string[] */
    public $closed;
    /** @var string[] */
    public $staffChoices;

    public function __construct( $name, array $statuses, array $tones, array $roles, array $done, array $closed, array $staffChoices )
    {
        if ( !$statuses )
            throw new InvalidArgumentException( "status set $name has no statuses" );
        $this->name = (string)$name;
        $this->statuses = $statuses;
        $first = (string)key( $statuses );
        $this->tones = array();
        foreach ( $statuses as $id => $label )
            $this->tones[$id] = isset( $tones[$id] ) && in_array( $tones[$id], self::TONES, true ) ? $tones[$id] : 'info';
        $this->roles = array();
        foreach ( self::ROLES as $role )
            $this->roles[$role] = isset( $roles[$role] ) && isset( $statuses[$roles[$role]] ) ? (string)$roles[$role] : $first;
        $known = function ( array $list ) use ( $statuses ) {
            return array_values( array_filter( array_map( 'strval', $list ), function ( $s ) use ( $statuses ) { return isset( $statuses[$s] ); } ) );
        };
        $this->closed = $known( $closed );
        $this->done = array_values( array_unique( array_merge( $known( $done ), $this->closed ) ) );
        $this->staffChoices = $staffChoices ? $known( $staffChoices ) : array_keys( $statuses );
    }

    /** The 7x set as shipped (also the fallback when a set is missing from the settings). */
    public static function builtIn()
    {
        return new self( '7x',
            array( 'open' => 'Open', 'waiting_customer' => 'Waiting on customer', 'waiting_staff' => 'Waiting on staff',
                   'on_hold' => 'On hold', 'resolved' => 'Resolved', 'closed' => 'Closed' ),
            array( 'open' => 'info', 'waiting_customer' => 'warn', 'waiting_staff' => 'info', 'on_hold' => 'muted',
                   'resolved' => 'ok', 'closed' => 'muted' ),
            array( 'Default' => 'open', 'CustomerReply' => 'waiting_staff', 'StaffReply' => 'waiting_customer',
                   'Resolved' => 'resolved', 'Closed' => 'closed', 'Reopen' => 'waiting_staff' ),
            array( 'resolved', 'closed' ), array( 'closed' ), array() );
    }

    public static function fromSettings( ticketsystemSettings $settings, $name )
    {
        $block = 'StatusSet_' . $name;
        $statuses = (array)$settings->value( $block, 'Statuses', array() );
        if ( !$statuses )
            return self::builtIn();
        $roles = array();
        foreach ( self::ROLES as $role )
            $roles[$role] = (string)$settings->value( $block, $role, '' );
        return new self( $name, $statuses, (array)$settings->value( $block, 'Tone', array() ), $roles,
                         (array)$settings->value( $block, 'DoneStatuses', array() ), (array)$settings->value( $block, 'ClosedStatuses', array() ),
                         (array)$settings->value( $block, 'StaffChoices', array() ) );
    }

    public function has( $status )
    {
        return isset( $this->statuses[(string)$status] );
    }

    public function label( $status )
    {
        return isset( $this->statuses[(string)$status] ) ? (string)$this->statuses[(string)$status] : (string)$status;
    }

    public function tone( $status )
    {
        return isset( $this->tones[(string)$status] ) ? $this->tones[(string)$status] : 'muted';
    }

    public function role( $role )
    {
        return $this->roles[$role];
    }

    /** Nobody can post any more. */
    public function isClosed( $status )
    {
        return in_array( (string)$status, $this->closed, true );
    }

    /** Not counted as open in the lists (resolved or closed). */
    public function isDone( $status )
    {
        return in_array( (string)$status, $this->done, true );
    }

    /** The statuses that are not done. */
    public function active()
    {
        return array_values( array_diff( array_keys( $this->statuses ), $this->done ) );
    }

    /**
     * The status after a post: a customer's post wakes a resolved ticket (Reopen) or hands it to staff
     * (CustomerReply); staff's post goes to their chosen status or StaffReply. Null when nobody may post.
     *
     * @param string $current
     * @param bool $byStaff
     * @param string $chosen a status staff picked with their reply ('' = the default)
     * @return string|null
     */
    public function afterPost( $current, $byStaff, $chosen = '' )
    {
        if ( $this->isClosed( $current ) )
            return null;
        if ( $byStaff )
            return $chosen !== '' && in_array( (string)$chosen, $this->staffChoices, true ) ? (string)$chosen : $this->roles['StaffReply'];
        return $this->isDone( $current ) ? $this->roles['Reopen'] : $this->roles['CustomerReply'];
    }

    /** Whether the customer can mark the ticket solved now. */
    public function customerMayResolve( $current )
    {
        return !$this->isDone( $current );
    }

    /** Whether the customer can open a resolved ticket again (a closed one stays closed). */
    public function customerMayReopen( $current )
    {
        return $this->isDone( $current ) && !$this->isClosed( $current );
    }

    /** The options of the class attribute "state": label by position. */
    public function optionLabels()
    {
        return array_values( array_map( 'strval', $this->statuses ) );
    }

    /** identifier, label, tone of every status (for templates). */
    public function rows()
    {
        $out = array();
        foreach ( $this->statuses as $id => $label )
            $out[] = array( 'id' => (string)$id, 'label' => (string)$label, 'tone' => $this->tone( $id ), 'done' => $this->isDone( $id ) );
        return $out;
    }
}
