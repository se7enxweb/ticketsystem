<?php
/**
 * DB-less tests of ticketsystem 1.1: SLA timers and escalation, the staff desk's filters and conditions, canned reply
 * placeholders, the attachment type checks, reports, inbound mail parsing and the signed reply reference, the WHMCS
 * text conversion.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemFeatureTest extends TestCase
{
    private function sla()
    {
        return new ticketsystemSla( new ticketsystemSettings( array(
            'SLASettings' => array( 'Enabled' => 'enabled', 'DefaultLevel' => 'standard', 'Levels' => array( 'standard', 'gold' ),
                                    'PausedStatuses' => array( 'waiting_customer' ), 'WarnPercent' => 80 ),
            'SLA_standard' => array( 'Label' => 'Standard', 'FirstResponse' => array( 'medium' => 100 ), 'Resolution' => array( 'medium' => 1000 ) ),
            'SLA_gold' => array( 'Label' => 'Gold', 'FirstResponse' => array( 'medium' => 10 ) ),
        ) ) );
    }

    public function testSlaTargetsAndFallbacks()
    {
        $s = $this->sla();
        $this->assertSame( 100, $s->minutes( 'standard', 'medium', 'FirstResponse' ) );
        $this->assertSame( 10, $s->minutes( 'gold', 'medium', 'FirstResponse' ) );
        $this->assertSame( 1000, $s->minutes( 'gold', 'medium', 'Resolution' ), 'a level without a resolution takes the default level' );
        $this->assertSame( 480, $s->minutes( 'sla9', 'medium', 'FirstResponse', '8 business hours' ), 'a Katalyst level: from its text' );
        $this->assertSame( array( 1000 + 6000, 1000 + 60000 ), $s->dues( 1000, 'standard', 'medium' ) );
        $this->assertSame( 2880, ticketsystemSla::parseDuration( '2 business days' ) );
        $this->assertSame( 30, ticketsystemSla::parseDuration( '30 minutes' ) );
        $this->assertSame( 0, ticketsystemSla::parseDuration( 'best effort' ) );
    }

    public function testSlaStatesEscalationAndPause()
    {
        $s = $this->sla();
        $row = array( 'created' => 0, 'response_due' => 1000, 'resolve_due' => 10000, 'responded' => 0, 'resolved' => 0, 'paused_since' => 0,
                      'escalation' => 0, 'breached' => 0 );
        $this->assertSame( 'ok', $s->state( $row, 500 )['response']['state'] );
        $this->assertSame( 'warn', $s->state( $row, 800 )['response']['state'] );
        $c = $s->check( $row, 850 );
        $this->assertSame( array( 'escalation' => 1, 'breached' => 0, 'raise' => true ), $c );
        $c = $s->check( array( 'escalation' => 1 ) + $row, 1001 );
        $this->assertSame( 2, $c['escalation'] );
        $this->assertSame( ticketsystemSla::BREACH_RESPONSE, $c['breached'] );
        $this->assertSame( 'met', $s->state( array( 'responded' => 900 ) + $row, 5000 )['response']['state'] );
        // waiting on the customer pauses; going on moves the open due times by the pause
        $p = $s->onStatus( $row, 'open', 'waiting_customer', 300 );
        $this->assertSame( array( 'paused_since' => 300 ), $p );
        $r = $s->onStatus( array( 'paused_since' => 300 ) + $row, 'waiting_customer', 'waiting_staff', 500 );
        $this->assertSame( array( 'response_due' => 1200, 'resolve_due' => 10200, 'paused_since' => 0 ), $r );
        $this->assertSame( 'paused', $s->state( array( 'paused_since' => 300 ) + $row, 900 )['response']['state'] );
    }

    public function testDeskFilterUrlAndConditions()
    {
        $spec = ticketsystemDesk::listSpec( array( 'open' => 'Open', 'closed' => 'Closed' ), array( 'low' => 'Low', 'urgent' => 'Urgent' ), array( 'billing' => 'Billing' ) );
        $p = ticketsystemDesk::filter( array( 'assignee' => 'me', 'priority' => 'urgent', 'category' => 'x;drop', 'status' => 'closed', 'sort' => 'priority',
                                              'order' => 'asc', 'offset' => '25', 'evil' => '1' ), $spec );
        $this->assertSame( array( 'status' => 'closed', 'priority' => 'urgent', 'assignee' => 'me' ), $p['filter'] );
        $this->assertSame( array( 'priority', 'asc', 25 ), array( $p['sort'], $p['order'], $p['offset'] ) );
        $this->assertSame( '/ticketsystem/list/(status)/closed/(priority)/urgent/(assignee)/me/(sort)/priority/(order)/asc/(offset)/25',
                           ticketsystemDesk::url( '/ticketsystem/list', $p['filter'], 'priority', 'asc', 25, $spec ) );
        $q = function ( $v ) { return "'" . addslashes( $v ) . "'"; };
        $this->assertSame( array( "priority = 'urgent'", 'assignee_id = 7' ), ticketsystemDesk::conditions( array( 'priority' => 'urgent', 'assignee' => 'me' ), $q, 7 ) );
        $this->assertSame( array( 'assignee_id = 0', 'escalation >= 2', "account_key = 'katalyst:5'" ),
                           ticketsystemDesk::conditions( array( 'assignee' => 'none', 'sla' => 'breached', 'account' => 'katalyst-5' ), $q ) );
        $this->assertSame( array( "number = '7X-2026-00042'" ), ticketsystemDesk::conditions( array( 'q' => '7x-2026-00042' ), $q ) );
        $this->assertSame( array( "LOWER(subject) LIKE '%disk%full%'" ), ticketsystemDesk::conditions( array( 'q' => 'Disk  FULL' ), $q ) );
        $this->assertSame( '%100%', ticketsystemDesk::likePattern( '100%' ) );
        $this->assertSame( array( 'open' ), ticketsystemDesk::statusesFor( '', array( 'open', 'closed' ), array( 'closed' ) ) );
        $this->assertSame( array(), ticketsystemDesk::statusesFor( 'all', array( 'open', 'closed' ), array( 'closed' ) ) );
        $this->assertSame( '2 d late', ticketsystemDesk::left( -2 * 86400 ) );
    }

    public function testCannedPlaceholders()
    {
        $this->assertSame( 'Hello Ann, ticket 7X-1 {unknown}', ticketsystemService::expand( 'Hello {customer}, ticket {number} {unknown}',
                                                                                         array( 'customer' => 'Ann', 'number' => '7X-1' ) ) );
    }

    public function testAttachmentTypes()
    {
        $this->assertTrue( ticketsystemFiles::typeMatches( 'pdf', 'application/pdf' ) );
        $this->assertFalse( ticketsystemFiles::typeMatches( 'pdf', 'text/html' ) );
        $this->assertFalse( ticketsystemFiles::typeMatches( 'png', 'application/x-php' ) );
        $this->assertTrue( ticketsystemFiles::typeMatches( 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ) );
        $this->assertSame( 'evil_.php.txt', ticketsystemFiles::cleanName( '../../evil<>.php.txt' ) );
        $this->assertSame( '2.0 MB', ticketsystemFiles::sizeLabel( 2097152 ) );
    }

    public function testReportFigures()
    {
        $row = array( 'status' => 'open', 'category' => 'billing', 'priority' => 'low', 'assignee_id' => 0, 'escalation' => 0, 'breached' => 0,
                      'created' => 0, 'responded' => 0, 'resolved' => 0, 'closed' => 0, 'response_due' => 0, 'resolve_due' => 0, 'rating' => 0 );
        $f = ticketsystemReport::figures( array(
            $row,
            array( 'status' => 'closed', 'assignee_id' => 9, 'responded' => 600, 'resolved' => 7200, 'response_due' => 1200, 'resolve_due' => 3600,
                   'rating' => 5, 'escalation' => 1, 'breached' => 2 ) + $row ), array( 'resolved', 'closed' ), 10000 );
        $this->assertSame( array( 2, 1, 1, 1, 1, 1 ), array( $f['opened'], $f['resolved'], $f['active'], $f['unassigned'], $f['escalated'], $f['breached'] ) );
        $this->assertSame( 10, $f['first_response']['avg_minutes'] );
        $this->assertSame( array( 1, 1, 0, 1 ), array( $f['sla']['response_met'], $f['sla']['response_total'], $f['sla']['resolution_met'], $f['sla']['resolution_total'] ) );
        $this->assertSame( 5.0, (float)$f['csat']['avg'] );
        $this->assertSame( "'=SUM(A1)", ticketsystemReport::csvSafe( '=SUM(A1)' ) );
    }

    public function testMailParserAndQuotedText()
    {
        $raw = "From: =?UTF-8?Q?Ann_M=C3=BCller?= <Ann@Example.invalid>\r\nTo: support+ts.7X-2026-00042.5.0123456789abcdef0123@example.invalid\r\n"
             . "Subject: Re: [7X-2026-00042] Disk full\r\nMessage-ID: <abc@mail>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n"
             . "--b1\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nThanks, it works now=21\r\n\r\nOn Mon, 16 Nov 2026 support wrote:\r\n> old text\r\n"
             . "--b1\r\nContent-Type: application/pdf; name=\"log.pdf\"\r\nContent-Disposition: attachment; filename=\"log.pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . base64_encode( '%PDF-1.4' ) . "\r\n--b1--\r\n";
        $m = ticketsystemMailParser::parse( $raw );
        $this->assertSame( 'ann@example.invalid', $m['from'] );
        $this->assertSame( 'Ann Müller', $m['from_name'] );
        $this->assertSame( '<abc@mail>', $m['message_id'] );
        $this->assertCount( 1, $m['attachments'] );
        $this->assertSame( '%PDF-1.4', $m['attachments'][0]['data'] );
        $this->assertSame( 'Thanks, it works now!', ticketsystemReplyToken::stripQuoted( $m['text'] ) );
        $this->assertSame( '7X-2026-00042', ticketsystemReplyToken::subjectNumber( $m['subject'] ) );
        $this->assertSame( "Line\n\nNext", ticketsystemMailParser::htmlToText( '<p>Line</p><p>Next</p>' ) );
    }

    public function testSignedReference()
    {
        $ref = ticketsystemReplyToken::reference( '7x-2026-00042', 5, 'secret' );
        $this->assertMatchesRegularExpression( '/^ts\.7X-2026-00042\.5\.[a-f0-9]{20}$/', $ref );
        $this->assertSame( array( array( 'number' => '7X-2026-00042', 'user_id' => 5 ) ), ticketsystemReplyToken::find( array( "x $ref y" ), 'secret' ) );
        $this->assertSame( array(), ticketsystemReplyToken::find( array( $ref ), 'other secret' ), 'a reference of another secret is refused' );
        $forged = preg_replace( '/\.5\./', '.6.', $ref );
        $this->assertSame( array(), ticketsystemReplyToken::find( array( $forged ), 'secret' ), 'another user id breaks the signature' );
        $this->assertSame( "support+$ref@x.invalid", ticketsystemReplyToken::replyAddress( 'support+%token@x.invalid', '7X-2026-00042', 5, 'secret' ) );
        $this->assertSame( "New text", ticketsystemReplyToken::stripQuoted( "New text\n\n" . ticketsystemReplyToken::MARKER . "\nold" ) );
    }

    public function testWhmcsText()
    {
        $this->assertSame( "Hello & bye\n\nNext", ticketsystemWhmcsImport::text( "<p>Hello &amp; bye</p>Next" ) );
        $this->assertSame( 0, ticketsystemWhmcsImport::time( '' ) );
    }
}
