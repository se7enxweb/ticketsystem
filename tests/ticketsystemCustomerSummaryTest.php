<?php
/**
 * The customer's support summary (doc/views.md): the fetch function and the preselected site of ticketsystem/new.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

use PHPUnit\Framework\TestCase;

class ticketsystemCustomerSummaryTest extends TestCase
{
    public function testFetchFunctionIsDefined()
    {
        $FunctionList = array();
        include __DIR__ . '/../modules/ticketsystem/function_definition.php';
        $this->assertArrayHasKey( 'customer_summary', $FunctionList );
        $f = $FunctionList['customer_summary'];
        $this->assertSame( array( 'class' => 'ticketsystemFunctionCollection', 'method' => 'customerSummary' ), $f['call_method'] );
        $this->assertSame( array( 'limit', 'subscription_id' ), array_column( $f['parameters'], 'name' ) );
        require_once __DIR__ . '/../classes/ticketsystemfunctioncollection.php';
        $this->assertTrue( method_exists( 'ticketsystemFunctionCollection', 'customerSummary' ) );
    }

    public function testNewTakesTheSubscriptionAsViewParameter()
    {
        $Module = array();
        $ViewList = array();
        $FunctionList = array();
        include __DIR__ . '/../modules/ticketsystem/module.php';
        $this->assertSame( array( 'user' => 'user', 'customer' => 'customer', 'subscription' => 'subscription' ), $ViewList['new']['unordered_params'] );
    }

    public function testWaitingOnTheCustomerIsTheStaffReplyRole()
    {
        $this->assertSame( 'waiting_customer', ticketsystemStatusSet::builtIn()->role( 'StaffReply' ) );
    }
}
