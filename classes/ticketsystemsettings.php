<?php
/**
 * @copyright Copyright (C) 2006 - 2007 xrow GbR, Hannover (Germany); Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The settings of ticketsystem.ini, with the defaults of the shipped file when a value is missing. Tests pass an array
 * (block => name => value) instead of reading the INI.
 */
class ticketsystemSettings
{
    /** @var ticketsystemSettings|null */
    protected static $instance = null;

    /** @var array|null block => name => value, or null to read ticketsystem.ini */
    protected $values;

    public function __construct( ?array $values = null )
    {
        $this->values = $values;
    }

    public static function instance()
    {
        if ( self::$instance === null )
            self::$instance = new self();
        return self::$instance;
    }

    /** Replaces the shared instance (tests); null goes back to the INI. */
    public static function setInstance( ?ticketsystemSettings $settings = null )
    {
        self::$instance = $settings;
    }

    /**
     * @param string $block
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function value( $block, $name, $default = null )
    {
        if ( $this->values !== null )
            return isset( $this->values[$block][$name] ) ? $this->values[$block][$name] : $default;
        $ini = eZINI::instance( 'ticketsystem.ini' );
        if ( !$ini->hasVariable( $block, $name ) )
            return $default;
        $v = $ini->variable( $block, $name );
        return $v === '' && $default !== null && !is_array( $default ) ? $default : $v;
    }

    public function hasBlock( $block )
    {
        if ( $this->values !== null )
            return isset( $this->values[$block] );
        return eZINI::instance( 'ticketsystem.ini' )->hasGroup( $block );
    }

    public function ticketClass()
    {
        return (string)$this->value( 'TicketSettings', 'TicketClass', 'ticket' );
    }

    public function postClass()
    {
        return (string)$this->value( 'TicketSettings', 'PostClass', 'ticket_comment' );
    }

    /** The status set in use. */
    public function statusSet()
    {
        $name = (string)$this->value( 'StatusSettings', 'StatusSet', '7x' );
        return ticketsystemStatusSet::fromSettings( $this, $name );
    }

    /** identifier => label */
    public function categories()
    {
        $c = (array)$this->value( 'CategorySettings', 'Categories', array() );
        return $c ? $c : array( 'general' => 'General question' );
    }

    /** identifier => label */
    public function priorities()
    {
        $p = (array)$this->value( 'PrioritySettings', 'Priorities', array() );
        return $p ? $p : array( 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High' );
    }

    public function defaultCategory()
    {
        $c = $this->categories();
        $d = (string)$this->value( 'CategorySettings', 'Default', '' );
        return isset( $c[$d] ) ? $d : (string)key( $c );
    }

    public function defaultPriority()
    {
        $p = $this->priorities();
        $d = (string)$this->value( 'PrioritySettings', 'Default', '' );
        return isset( $p[$d] ) ? $d : (string)key( $p );
    }

    public function limit( $name, $default )
    {
        return max( 0, (int)$this->value( 'LimitSettings', $name, $default ) );
    }
}
