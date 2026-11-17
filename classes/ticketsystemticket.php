<?php
/**
 * @copyright Copyright (C) 2006 - 2007 xrow GbR, Hannover (Germany); Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * One row of ticketsystem_ticket: the index of a ticket object (its readable number, the account that shares it,
 * its status and the times the lists sort by). The ticket itself and its posts are content objects
 * (doc/architecture.md); the index is written only by ticketsystemService and the workflow event.
 */
class ticketsystemTicket extends eZPersistentObject
{
    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        $str = function ( $name ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => '', 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'contentobject_id' => $int( 'ContentObjectID' ),
                'node_id' => $int( 'NodeID' ),
                'number' => $str( 'Number' ),
                'prefix' => $str( 'Prefix' ),
                'year' => $int( 'Year' ),
                'seq' => $int( 'Seq' ),
                'account_key' => $str( 'AccountKey' ),
                'opener_id' => $int( 'OpenerID' ),
                'subject' => $str( 'Subject' ),
                'status' => $str( 'Status' ),
                'category' => $str( 'Category' ),
                'priority' => $str( 'Priority' ),
                'subscription_id' => $int( 'SubscriptionID' ),
                'post_count' => $int( 'PostCount' ),
                'last_post' => $int( 'LastPost' ),
                'last_post_by' => $int( 'LastPostBy' ),
                'last_post_staff' => $int( 'LastPostStaff' ),
                'created' => $int( 'Created' ),
                'modified' => $int( 'Modified' ),
                'resolved' => $int( 'Resolved' ),
                'closed' => $int( 'Closed' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'ticketsystemTicket',
            'sort' => array( 'modified' => 'desc' ),
            'name' => 'ticketsystem_ticket',
        );
    }

    public static function create( array $row )
    {
        return new self( $row + array( 'id' => null ) );
    }

    public static function fetch( $id )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int)$id ) );
    }

    public static function fetchByNumber( $number )
    {
        $number = strtoupper( trim( (string)$number ) );
        if ( $number === '' || !preg_match( '/^[A-Z0-9][A-Z0-9-]{2,31}$/D', $number ) )
            return null;
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'number' => $number ) );
    }

    public static function fetchByObjectID( $objectId )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'contentobject_id' => (int)$objectId ) );
    }

    /**
     * Tickets by conditions built from trusted values only (ticketsystemAccess::listConditions()).
     *
     * @param string $where SQL condition, every value already escaped or cast
     * @param int $offset
     * @param int $limit
     * @return ticketsystemTicket[]
     */
    public static function fetchWhere( $where, $offset = 0, $limit = 25 )
    {
        $db = eZDB::instance();
        $rows = $db->arrayQuery( 'SELECT * FROM ticketsystem_ticket WHERE ' . $where . ' ORDER BY modified DESC, id DESC',
                                 array( 'offset' => max( 0, (int)$offset ), 'limit' => max( 1, (int)$limit ) ) );
        return eZPersistentObject::handleRows( is_array( $rows ) ? $rows : array(), 'ticketsystemTicket', true );
    }

    public static function countWhere( $where )
    {
        $rows = eZDB::instance()->arrayQuery( 'SELECT COUNT(*) AS n FROM ticketsystem_ticket WHERE ' . $where );
        return is_array( $rows ) && $rows ? (int)$rows[0]['n'] : 0;
    }

    /** Tickets a user opened since a time (the rate limit). */
    public static function countOpenedSince( $userId, $since )
    {
        return (int)eZPersistentObject::count( self::definition(),
                                               array( 'opener_id' => (int)$userId, 'created' => array( '>=', (int)$since ) ) );
    }

    /** @return eZContentObject|null */
    public function contentObject()
    {
        $o = eZContentObject::fetch( (int)$this->attribute( 'contentobject_id' ) );
        return $o instanceof eZContentObject ? $o : null;
    }
}
