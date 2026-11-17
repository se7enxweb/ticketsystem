<?php
/**
 * DB-less tests of the pure parts of ticketsystem: status sets, numbers, text, scopes, captured addresses, mail content.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemPureTest extends TestCase
{
    public function testBuiltInStatusSetRoles()
    {
        $s = ticketsystemStatusSet::builtIn();
        $this->assertSame( 'open', $s->role( 'Default' ) );
        $this->assertSame( 'waiting_staff', $s->afterPost( 'open', false ) );
        $this->assertSame( 'waiting_customer', $s->afterPost( 'waiting_staff', true ) );
        $this->assertSame( 'on_hold', $s->afterPost( 'open', true, 'on_hold' ) );
        $this->assertSame( 'waiting_customer', $s->afterPost( 'open', true, 'nonsense' ) );
        $this->assertSame( 'waiting_staff', $s->afterPost( 'resolved', false ) );
        $this->assertNull( $s->afterPost( 'closed', false ) );
        $this->assertTrue( $s->customerMayReopen( 'resolved' ) );
        $this->assertFalse( $s->customerMayReopen( 'closed' ) );
        $this->assertSame( array( 'open', 'waiting_customer', 'waiting_staff', 'on_hold' ), $s->active() );
    }

    public function testStatusSetFromSettingsKeepsRolesApartFromLists()
    {
        $settings = new ticketsystemSettings( array( 'StatusSet_x' => array(
            'Statuses' => array( 'a' => 'A', 'b' => 'B', 'c' => 'C' ), 'Default' => 'a', 'Closed' => 'c',
            'ClosedStatuses' => array( 'c' ), 'DoneStatuses' => array( 'b' ) ) ) );
        $s = ticketsystemStatusSet::fromSettings( $settings, 'x' );
        $this->assertSame( 'c', $s->role( 'Closed' ) );
        $this->assertSame( array( 'c' ), $s->closed );
        $this->assertSame( array( 'b', 'c' ), $s->done );
        $this->assertSame( array( 'A', 'B', 'C' ), $s->optionLabels() );
    }

    public function testNumberFormat()
    {
        $this->assertSame( '7X-2026-00042', ticketsystemNumber::format( '%prefix-%year-%seq', '7x', 2026, 42, 5 ) );
        $this->assertSame( 'TS-2026-7', ticketsystemNumber::format( '', '', 2026, 7, 1 ) );
        $this->assertSame( 'ABC', ticketsystemNumber::cleanPrefix( 'a-b c!' ) );
    }

    public function testTextRoundTrip()
    {
        $xml = ticketsystemText::toXml( "Hello <you> & me\n\nline one\nline two\n\n\n" );
        $this->assertStringContainsString( '<paragraph>Hello &lt;you&gt; &amp; me</paragraph>', $xml );
        $this->assertSame( array( 'Hello <you> & me', "line one\nline two" ), ticketsystemText::fromXml( $xml ) );
        $this->assertSame( array(), ticketsystemText::fromXml( '' ) );
    }

    public function testScopes()
    {
        $this->assertSame( ticketsystemAccess::ALL, ticketsystemAccess::scopeOf( array( 'accessWord' => 'yes' ) ) );
        $this->assertSame( 0, ticketsystemAccess::scopeOf( array( 'accessWord' => 'no' ) ) );
        $this->assertSame( ticketsystemAccess::ACCOUNT, ticketsystemAccess::scopeOf( array( 'accessWord' => 'limited',
            'policies' => array( 1 => array( 'Scope' => array( '1' ) ), 2 => array( 'Scope' => array( '2' ) ) ) ) ) );
        $t = array( 'opener_id' => 5, 'account_key' => 'katalyst:9' );
        $this->assertTrue( ticketsystemAccess::reaches( ticketsystemAccess::OWN, $t, 5, array() ) );
        $this->assertFalse( ticketsystemAccess::reaches( ticketsystemAccess::OWN, $t, 6, array( 'katalyst:9' ) ) );
        $this->assertTrue( ticketsystemAccess::reaches( ticketsystemAccess::ACCOUNT, $t, 6, array( 'katalyst:9' ) ) );
        $this->assertFalse( ticketsystemAccess::reaches( ticketsystemAccess::ACCOUNT, $t, 6, array( 'katalyst:8' ) ) );
        $this->assertTrue( ticketsystemAccess::reaches( ticketsystemAccess::ALL, $t, 6, array() ) );
    }

    public function testCapturedDomains()
    {
        $this->assertTrue( ticketsystemMailer::capturedBy( 'a@b.example.invalid', array( 'invalid' ) ) );
        $this->assertFalse( ticketsystemMailer::capturedBy( 'a@invalidx.com', array( 'invalid' ) ) );
        $this->assertFalse( ticketsystemMailer::capturedBy( 'a@example.com', array( 'invalid' ) ) );
    }

    public function testMailContentHasTheFullPost()
    {
        $c = ticketsystemMailer::content( array( 'number' => '7X-2026-00001', 'subject' => 'Help', 'status' => 'Open', 'category' => '',
            'priority' => 'High', 'author' => 'Ann', 'author_is_staff' => true, 'is_opening' => false, 'own_copy' => true,
            'paragraphs' => array( 'One', "Two\nThree" ), 'url' => 'https://x.invalid/t', 'brand' => '7x' ) );
        $this->assertSame( 'Re: [7X-2026-00001] Help', $c['subject'] );
        $this->assertStringContainsString( "One\n\nTwo\nThree", $c['text'] );
        $this->assertStringContainsString( 'Ann (7x support)', $c['text'] );
        $this->assertStringContainsString( 'copy of your own message', $c['text'] );
        $this->assertCount( 3, $c['body'][1] );
    }
}
