<?php
/**
 * DB-less tests of ticketsystem 1.2: time tracking (parsing, labels, the report filter and its CSV), CC addresses,
 * merge rules, automatic ticket keys, the CC copy of a post's mail and the virus scanner's verdict.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemExtrasTest extends TestCase
{
    public function testParseMinutes()
    {
        $cases = array( '' => 0, '0' => 0, '45' => 45, '45m' => 45, '45 min' => 45, '1:30' => 90, '1h30' => 90, '1h 30m' => 90, '1.5h' => 90,
                        '1,5 h' => 90, '2 h' => 120, '2hours' => 120, '0:05' => 5, ' 90 ' => 90,
                        'abc' => -1, '1:75' => -1, '-5' => -1, '1h75m' => -1, '5 days' => -1, '1e3' => -1 );
        foreach ( $cases as $in => $want )
            $this->assertSame( $want, ticketsystemTime::parseMinutes( (string)$in ), "\"$in\"" );
    }

    public function testRoundUpAndLabel()
    {
        $this->assertSame( 15, ticketsystemTime::roundUp( 1, 15 ) );
        $this->assertSame( 30, ticketsystemTime::roundUp( 16, 15 ) );
        $this->assertSame( 7, ticketsystemTime::roundUp( 7, 1 ) );
        $this->assertSame( '45 min', ticketsystemTime::label( 45 ) );
        $this->assertSame( '2 h', ticketsystemTime::label( 120 ) );
        $this->assertSame( '1 h 30 min', ticketsystemTime::label( 90 ) );
        $this->assertSame( '0 min', ticketsystemTime::label( -3 ) );
    }

    public function testCheckUsesTheSettings()
    {
        $t = new ticketsystemTime( new ticketsystemSettings( array( 'TimeSettings' => array( 'Increment' => 15, 'MaxMinutes' => 120 ) ) ) );
        $this->assertSame( array( '', 15 ), $t->check( '5' ) );
        $this->assertSame( array( '', 0 ), $t->check( '' ) );
        $this->assertNotSame( '', $t->check( '3h' )[0], 'more than MaxMinutes' );
        $this->assertNotSame( '', $t->check( 'soon' )[0] );
    }

    public function testFilterOfTheReport()
    {
        $now = mktime( 12, 0, 0, 11, 17, 2026 );
        $f = ticketsystemTime::filter( array(), $now );
        $this->assertSame( array( '2026-11-01', '2026-11-17', '', 0, 'all', 'all' ), array( $f['from'], $f['to'], $f['account'], $f['user'], $f['billable'], $f['billed'] ) );
        $f = ticketsystemTime::filter( array( 'from' => '2026-11-10', 'to' => '2026-11-02', 'account' => 'katalyst:12', 'user' => '42', 'billable' => 'yes', 'billed' => 'no' ), $now );
        $this->assertSame( array( '2026-11-02', '2026-11-10', 'katalyst:12', 42, 'yes', 'no' ), array( $f['from'], $f['to'], $f['account'], $f['user'], $f['billable'], $f['billed'] ) );
        $f = ticketsystemTime::filter( array( 'from' => "2026-11-01' OR 1=1", 'account' => "katalyst:1' --", 'user' => '4 2', 'billable' => 'maybe' ), $now );
        $this->assertSame( array( '2026-11-01', '', 0, 'all' ), array( $f['from'], $f['account'], $f['user'], $f['billable'] ), 'anything else falls back' );
    }

    public function testCsvNeverLetsARemarkBeAFormula()
    {
        $row = array( 'work_date' => mktime( 9, 0, 0, 11, 17, 2026 ), 'number' => '7X-2026-00001', 'account_key' => 'katalyst:3', 'user' => 'Ann Staff',
                      'minutes' => 90, 'billable' => true, 'covered' => '', 'billed' => 0, 'billing_ref' => '', 'remark' => '=HYPERLINK("x")' );
        $csv = ticketsystemTime::csv( array( $row ) );
        $lines = explode( "\n", trim( $csv ) );
        $this->assertSame( 'date,ticket,account,staff,minutes,hours,billable,covered_by,billed,billing_ref,remark', $lines[0] );
        $this->assertStringContainsString( '2026-11-17,7X-2026-00001,katalyst:3,"Ann Staff",90,1.50,yes,,,', $lines[1] );
        $this->assertStringContainsString( '\'=HYPERLINK', $lines[1] );
    }

    public function testCheckCc()
    {
        $ok = function ( $email, array $existing = array(), $max = 5, array $domains = array(), array $refused = array() ) {
            return ticketsystemWatch::checkCc( $email, $existing, $max, $domains, $refused );
        };
        $this->assertSame( '', $ok( 'Boss@Example.COM' ) );
        $this->assertNotSame( '', $ok( 'not an address' ) );
        $this->assertNotSame( '', $ok( 'a@b' ) );
        $this->assertNotSame( '', $ok( 'two@example.com, three@example.com' ), 'one address only' );
        $this->assertNotSame( '', $ok( "x@example.com\r\nBcc: y@example.com" ), 'no header injection' );
        $this->assertNotSame( '', $ok( 'boss@example.com', array( 'boss@example.com' ) ), 'twice' );
        $this->assertNotSame( '', $ok( 'new@example.com', array( 'a@example.com', 'b@example.com' ), 2 ), 'at most MaxCC' );
        $this->assertSame( '', $ok( 'it@sub.acme.test', array(), 5, array( 'acme.test' ) ) );
        $this->assertNotSame( '', $ok( 'it@evilacme.test', array(), 5, array( 'acme.test' ) ), 'a domain, not a suffix of a name' );
        $this->assertNotSame( '', $ok( 'support@se7enx.com', array(), 5, array(), array( 'support@se7enx.com' ) ), 'the help desk itself' );
        $this->assertNotSame( '', $ok( 'help+abc@team.se7enx.com', array(), 5, array(), array( '@team.se7enx.com' ) ), 'its reply domain' );
    }

    public function testMergeable()
    {
        $t = function ( $id, $account, $status = 'open', $merged = 0 ) {
            return array( 'id' => $id, 'account_key' => $account, 'status' => $status, 'merged_into' => $merged );
        };
        $this->assertSame( array( true, '' ), ticketsystemMerge::mergeable( $t( 1, 'katalyst:3' ), $t( 2, 'katalyst:3' ), array( 'closed' ) ) );
        $this->assertFalse( ticketsystemMerge::mergeable( $t( 1, 'katalyst:3' ), $t( 1, 'katalyst:3' ) )[0], 'into itself' );
        $this->assertFalse( ticketsystemMerge::mergeable( $t( 1, 'katalyst:3' ), $t( 2, 'katalyst:4' ) )[0], 'another customer: never' );
        $this->assertFalse( ticketsystemMerge::mergeable( $t( 1, 'user:7' ), $t( 2, 'katalyst:3' ) )[0], 'another account' );
        $this->assertFalse( ticketsystemMerge::mergeable( $t( 1, 'katalyst:3', 'open', 9 ), $t( 2, 'katalyst:3' ) )[0], 'merged already' );
        $this->assertFalse( ticketsystemMerge::mergeable( $t( 1, 'katalyst:3' ), $t( 2, 'katalyst:3', 'open', 9 ) )[0], 'into a merged one' );
        $this->assertFalse( ticketsystemMerge::mergeable( $t( 1, 'katalyst:3' ), $t( 2, 'katalyst:3', 'closed' ), array( 'closed' ) )[0], 'into a closed one' );
    }

    public function testAutoTicketKeys()
    {
        $this->assertSame( 'katalyst-job:provisioning:3', ticketsystemAutoTicket::cleanKey( 'katalyst-job:provisioning:3' ) );
        $this->assertSame( 'a-b-c', ticketsystemAutoTicket::cleanKey( " A b'c " ) );
        $this->assertSame( 64, strlen( ticketsystemAutoTicket::cleanKey( str_repeat( 'x', 100 ) ) ) );
        $this->assertSame( '', ticketsystemAutoTicket::cleanKey( "'''" ) );
    }

    public function testCcCopyCarriesNoReplyReference()
    {
        $d = array( 'number' => '7X-2026-00001', 'subject' => 'Mail down', 'status' => 'Open', 'category' => '', 'priority' => '', 'author' => 'Ann',
                    'author_is_staff' => true, 'is_opening' => false, 'paragraphs' => array( 'Fixed.' ), 'url' => 'https://example.test/ticketsystem/view/7X-2026-00001',
                    'brand' => 'Example', 'own_copy' => false );
        $reader = ticketsystemMailer::content( $d + array( 'reference' => 'ref123', 'rate_url' => '' ) );
        $cc = ticketsystemMailer::content( $d + array( 'reference' => 'ref123', 'rate_url' => '', 'cc' => true ) );
        $this->assertStringContainsString( 'ref123', $reader['text'] );
        $this->assertStringNotContainsString( 'ref123', $cc['text'], 'a CC copy never carries the signed reference' );
        $this->assertStringNotContainsString( 'ref123', $cc['footer'] );
        $this->assertStringContainsString( 'CC', $cc['notice'] );
        $this->assertStringContainsString( 'Fixed.', $cc['text'], 'the full post' );
    }

    public function testScanVerdict()
    {
        $this->assertSame( '', ticketsystemFiles::scanVerdict( 0 ) );
        $this->assertSame( 'The file did not pass the virus check.', ticketsystemFiles::scanVerdict( 1 ) );
        $this->assertSame( 'Files cannot be checked right now. Please try again later.', ticketsystemFiles::scanVerdict( 2 ) );
    }
}
