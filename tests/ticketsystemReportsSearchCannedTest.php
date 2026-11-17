<?php
/**
 * DB-less tests of reports, search and canned replies (doc/reports.md, doc/search.md, doc/canned-replies.md):
 * placeholder expansion, the permission scope filters (who searches the internal notes, who keeps which canned
 * replies), CSV escaping with the formula-injection guard, report periods, volume, backlog and workload.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemReportsSearchCannedTest extends TestCase
{
    /** A quoter as ticketsystemDesk::quoter() writes it, without a database. */
    protected static function quote()
    {
        return function ( $v ) { return "'" . str_replace( "'", "''", (string)$v ) . "'"; };
    }

    protected static function spec()
    {
        return ticketsystemDesk::listSpec( array( 'open' => 'Open', 'resolved' => 'Resolved', 'closed' => 'Closed' ),
                                           array( 'low' => 'Low', 'high' => 'High' ), array( 'billing' => 'Billing', 'hosting' => 'Hosting' ) );
    }

    // ------------------------------------------------------------------------------------------ placeholders

    public function testEveryPlaceholderIsFilledIn()
    {
        $v = ticketsystemService::cannedValues( array( 'number' => '7X-2026-00042', 'subject' => 'Disk full', 'status' => 'Open', 'queue' => 'Hosting',
            'priority' => 'High', 'customer' => 'Ann Example', 'customer_first' => '', 'staff' => 'Sam Support', 'staff_first' => 'Samuel',
            'site_url' => 'https://example.invalid/', 'site' => '7x' ) );
        $text = "Hello {customer_first_name} ({customer}),\nticket {number} \"{subject}\" is {status} in {queue} ({priority}).\n"
              . "{link}\n{tickets_link}\n{new_ticket_link}\n{staff_name} / {staff_first_name}, {site}";
        $this->assertSame( "Hello Ann (Ann Example),\nticket 7X-2026-00042 \"Disk full\" is Open in Hosting (High).\n"
                         . "https://example.invalid/ticketsystem/view/7X-2026-00042\nhttps://example.invalid/ticketsystem/list\n"
                         . "https://example.invalid/ticketsystem/new\nSam Support / Samuel, 7x", ticketsystemService::expand( $text, $v ) );
        foreach ( array_keys( ticketsystemService::CANNED_PLACEHOLDERS ) as $name )
            $this->assertArrayHasKey( $name, $v, "placeholder {{$name}} has a value" );
    }

    public function testAliasesUnknownNamesAndNoSecondPass()
    {
        $v = ticketsystemService::cannedValues( array( 'number' => 'TS-2026-1', 'customer' => 'Bo', 'staff' => '{number}', 'site_url' => 'https://x.invalid' ) );
        // the 1.1 names still work
        $this->assertSame( 'Bo Bo {number} https://x.invalid/ticketsystem/view/TS-2026-1',
                           ticketsystemService::expand( '{first_name} {customer} {agent} {ticket_link}', $v ) );
        // a value is never expanded again, unknown and malformed placeholders stay as written
        $this->assertSame( '{number} {nope} {Number} { number } {x}', ticketsystemService::expand( '{agent} {nope} {Number} { number } {x}', $v ) );
        $this->assertSame( 'TS-2026-1', ticketsystemService::expand( '{number}', $v ) );
    }

    public function testFirstNames()
    {
        $this->assertSame( 'Ann', ticketsystemService::firstName( '  Ann   Example ' ) );
        $this->assertSame( 'Annie', ticketsystemService::firstName( 'Ann Example', ' Annie ' ) );
        $this->assertSame( '', ticketsystemService::firstName( '' ) );
        $this->assertSame( 'Zoë', ticketsystemService::firstName( "Zoë\tO'Brien" ) );
    }

    public function testTicketNumberInLinksIsEncoded()
    {
        $v = ticketsystemService::cannedValues( array( 'number' => 'A B/1', 'site_url' => 'https://x.invalid' ) );
        $this->assertSame( 'https://x.invalid/ticketsystem/view/A%20B%2F1', $v['link'] );
    }

    // -------------------------------------------------------------------------------- canned reply policies

    public function testCannedScopesFromPolicies()
    {
        $this->assertSame( array( 'personal' => true, 'shared' => true ), ticketsystemAccess::cannedScopesOf( array( 'accessWord' => 'yes' ) ) );
        $this->assertSame( array( 'personal' => false, 'shared' => false ), ticketsystemAccess::cannedScopesOf( array( 'accessWord' => 'no' ) ) );
        $this->assertSame( array( 'personal' => true, 'shared' => false ), ticketsystemAccess::cannedScopesOf( array( 'accessWord' => 'limited',
            'policies' => array( 1 => array( 'CannedScope' => array( '1' ) ) ) ) ) );
        $this->assertSame( array( 'personal' => true, 'shared' => true ), ticketsystemAccess::cannedScopesOf( array( 'accessWord' => 'limited',
            'policies' => array( 1 => array( 'CannedScope' => array( '1' ) ), 2 => array( 'CannedScope' => array( '2' ) ) ) ) ) );
        // a policy of the function without a limitation (another role) allows both
        $this->assertSame( array( 'personal' => true, 'shared' => true ), ticketsystemAccess::cannedScopesOf( array( 'accessWord' => 'limited',
            'policies' => array( 1 => array( 'CannedScope' => array( '1' ) ), 2 => array() ) ) ) );
    }

    public function testWhoMayKeepWhichCannedReply()
    {
        $personal = array( 'personal' => true, 'shared' => false );
        $shared = array( 'personal' => false, 'shared' => true );
        $both = array( 'personal' => true, 'shared' => true );
        $none = array( 'personal' => false, 'shared' => false );
        // own personal ones with Personal; never somebody else's
        $this->assertTrue( ticketsystemService::mayKeepCanned( $personal, 7, 7 ) );
        $this->assertFalse( ticketsystemService::mayKeepCanned( $personal, 7, 8 ) );
        $this->assertFalse( ticketsystemService::mayKeepCanned( $both, 7, 8 ) );
        $this->assertFalse( ticketsystemService::mayKeepCanned( $personal, 7, 0 ) );
        // shared ones (owner 0) with Shared only
        $this->assertTrue( ticketsystemService::mayKeepCanned( $shared, 7, 0 ) );
        $this->assertFalse( ticketsystemService::mayKeepCanned( $shared, 7, 7 ) );
        // making one shared or personal needs both ends
        $this->assertFalse( ticketsystemService::mayKeepCanned( $personal, 7, 7, 0 ) );
        $this->assertFalse( ticketsystemService::mayKeepCanned( $shared, 7, 0, 7 ) );
        $this->assertTrue( ticketsystemService::mayKeepCanned( $both, 7, 7, 0 ) );
        $this->assertFalse( ticketsystemService::mayKeepCanned( $none, 7, 7 ) );
        $this->assertFalse( ticketsystemService::mayKeepCanned( $personal, 0, 0 ) );
    }

    public function testWhoSeesWhichCannedReplyAndInWhichQueue()
    {
        $this->assertTrue( ticketsystemService::cannedVisible( 0, 7 ) );
        $this->assertTrue( ticketsystemService::cannedVisible( 7, 7 ) );
        $this->assertFalse( ticketsystemService::cannedVisible( 8, 7 ) );
        $this->assertTrue( ticketsystemService::cannedInQueue( '', 'billing' ) );
        $this->assertTrue( ticketsystemService::cannedInQueue( 'billing', 'billing' ) );
        $this->assertFalse( ticketsystemService::cannedInQueue( 'hosting', 'billing' ) );
        $this->assertTrue( ticketsystemService::cannedInQueue( 'hosting', '' ) );
    }

    // ---------------------------------------------------------------------------------- search scope filters

    public function testInternalNotesAreSearchedForStaffOnly()
    {
        $staff = ticketsystemReport::searchPlan( array( 'q' => 'disk full' ), true, self::quote() );
        $customer = ticketsystemReport::searchPlan( array( 'q' => 'disk full' ), false, self::quote() );
        $this->assertTrue( $staff['search_notes'] );
        $this->assertTrue( $staff['search_posts'] );
        $this->assertFalse( $customer['search_notes'], 'a customer\'s search never reads the internal notes' );
        $this->assertTrue( $customer['search_posts'] );
        $this->assertSame( '%disk%full%', $customer['like'] );
        // a ticket number is looked up as a number, no text search at all
        $n = ticketsystemReport::searchPlan( array( 'q' => '7x-2026-00042' ), true, self::quote() );
        $this->assertSame( '7X-2026-00042', $n['number'] );
        $this->assertFalse( $n['search_notes'] || $n['search_posts'] );
    }

    public function testSearchFiltersBecomeConditions()
    {
        $plan = ticketsystemReport::searchPlan( array( 'priority' => 'high', 'category' => 'billing', 'assignee' => 'me', 'number' => '00042',
                                                       'from' => '2026-11-01', 'to' => '2026-11-30', 'account' => 'katalyst-9' ), true, self::quote(), 5 );
        $c = implode( ' AND ', $plan['conditions'] );
        $this->assertStringContainsString( "priority = 'high'", $c );
        $this->assertStringContainsString( "category = 'billing'", $c );
        $this->assertStringContainsString( 'assignee_id = 5', $c );
        $this->assertStringContainsString( "account_key = 'katalyst:9'", $c );
        $this->assertStringContainsString( "number LIKE '%00042%'", $c );
        $this->assertStringContainsString( 'created >= ' . mktime( 0, 0, 0, 11, 1, 2026 ), $c );
        $this->assertStringContainsString( 'created < ' . mktime( 0, 0, 0, 12, 1, 2026 ), $c );
    }

    public function testSearchFilterKeepsOnlyAllowedValues()
    {
        $f = ticketsystemReport::searchFilter( array( 'q' => rawurlencode( 'disk ' ), 'status' => 'nonsense', 'priority' => 'high', 'queue' => 'billing',
                                                      'assignee' => 'x; DROP', 'customer' => rawurlencode( "ann\x00@example" ), 'from' => '2026-02-30',
                                                      'to' => '2026-11-30', 'number' => rawurlencode( "7x-2026'--" ), 'evil' => '1' ), self::spec() );
        $this->assertSame( array( 'q' => 'disk', 'priority' => 'high', 'category' => 'billing', 'customer' => 'ann @example', 'to' => '2026-11-30' ), $f );
        $this->assertArrayNotHasKey( 'number', $f, 'a number with a quote is dropped' );
    }

    public function testScopeReachStillDecidesTheTicket()
    {
        // the search adds its conditions to ticketsystemAccess::listConditions(); reaches() is the same rule per ticket
        $t = array( 'opener_id' => 5, 'account_key' => 'katalyst:9' );
        $this->assertFalse( ticketsystemAccess::reaches( ticketsystemAccess::OWN, $t, 6, array( 'katalyst:9' ) ) );
        $this->assertTrue( ticketsystemAccess::reaches( ticketsystemAccess::ACCOUNT, $t, 6, array( 'katalyst:9' ) ) );
        $this->assertFalse( ticketsystemAccess::reaches( 0, $t, 5, array() ) );
    }

    public function testInListIsChunkedForOracle()
    {
        $this->assertSame( '0=1', ticketsystemReport::inList( 'id', array() ) );
        $this->assertSame( 'id IN (1,2)', ticketsystemReport::inList( 'id', array( '1', 2, 2 ) ) );
        $sql = ticketsystemReport::inList( 'id', range( 1, 1201 ) );
        $this->assertSame( 3, substr_count( $sql, 'id IN (' ) );
        $this->assertStringStartsWith( '(', $sql );
    }

    public function testSearchUrlAndLikeMatching()
    {
        $this->assertSame( '/ticketsystem/search/(q)/a%20b%20c/(category)/billing/(from)/2026-11-01/(offset)/50',
                           str_replace( '(queue)', '(category)', ticketsystemReport::searchUrl( '/ticketsystem/search', array( 'q' => 'a/b\\c', 'category' => 'billing', 'from' => '2026-11-01' ), 50 ) ) );
        $this->assertStringContainsString( '/(queue)/billing', ticketsystemReport::searchUrl( '/ticketsystem/search', array( 'category' => 'billing' ) ) );
        $this->assertTrue( ticketsystemReport::likeMatches( '%disk%full%', 'My DISK is very full' ) );
        $this->assertFalse( ticketsystemReport::likeMatches( '%disk%full%', 'full disk' ) );
        $this->assertFalse( ticketsystemReport::likeMatches( '', 'anything' ) );
    }

    // ---------------------------------------------------------------------------------------------------- CSV

    public function testCsvFormulaInjectionIsDefused()
    {
        foreach ( array( '=SUM(A1)', '+1', '-1+2', '@cmd', "\t=1", "\r=1", '  =HYPERLINK("x")', "\u{FF1D}1+1" ) as $evil )
            $this->assertStringStartsWith( "'", ticketsystemReport::csvSafe( $evil ), json_encode( $evil ) );
        foreach ( array( 'Disk full', '7X-2026-00042', '', 'a=b', '2026-11-17T10:00:00+01:00' ) as $fine )
            $this->assertSame( $fine, ticketsystemReport::csvSafe( $fine ) );
        // numbers stay numbers (a negative one is a number, not a formula)
        $this->assertSame( '-5', ticketsystemReport::csvSafe( -5 ) );
        $this->assertSame( '1.5', ticketsystemReport::csvSafe( 1.5 ) );
    }

    public function testCsvLineQuotesAndCleans()
    {
        $this->assertSame( "a,\"b,c\",\"say \"\"hi\"\"\",\"line\nbreak\",'=1,3\r\n",
                           ticketsystemReport::csvLine( array( 'a', 'b,c', 'say "hi"', "line\nbreak", '=1', 3 ) ) );
        $this->assertSame( "bell,\" x\"\r\n", ticketsystemReport::csvLine( array( "be\x07ll", ' x' ) ) );
        // a defused cell that also needs quotes
        $this->assertSame( "\"'=1,2\"\r\n", ticketsystemReport::csvLine( array( '=1,2' ) ) );
    }

    // ------------------------------------------------------------------------------------------------ reports

    public function testRangeDefaultsSwapsAndLimits()
    {
        $now = mktime( 15, 0, 0, 11, 17, 2026 );
        $r = ticketsystemReport::range( array(), 30, $now );
        $this->assertSame( array( '2026-10-19', '2026-11-17', 30 ), array( $r['from_text'], $r['to_text'], $r['days'] ) );
        $this->assertSame( mktime( 0, 0, 0, 11, 18, 2026 ), $r['to'] );
        $r = ticketsystemReport::range( array( 'from' => '2026-11-30', 'to' => '2026-11-01' ), 30, $now );
        $this->assertSame( array( '2026-11-01', '2026-11-30', 30 ), array( $r['from_text'], $r['to_text'], $r['days'] ) );
        $r = ticketsystemReport::range( array( 'from' => '2020-01-01', 'to' => '2026-11-17' ), 30, $now, 90 );
        $this->assertSame( 90, $r['days'] );
        $r = ticketsystemReport::range( array( 'from' => 'garbage', 'to' => '2026-13-01' ), 7, $now );
        $this->assertSame( array( '2026-11-11', '2026-11-17' ), array( $r['from_text'], $r['to_text'] ) );
        $this->assertSame( 0, ticketsystemReport::parseDate( '2026-02-29' ) );
    }

    public function testPeriodsAndVolume()
    {
        $this->assertSame( 'day', ticketsystemReport::periodFor( 30 ) );
        $this->assertSame( 'week', ticketsystemReport::periodFor( 90 ) );
        $this->assertSame( 'month', ticketsystemReport::periodFor( 365 ) );
        $this->assertSame( 'month', ticketsystemReport::periodFor( 7, 'month' ) );
        $from = mktime( 0, 0, 0, 11, 1, 2026 );
        $to = mktime( 0, 0, 0, 11, 4, 2026 );
        $day = function ( $d, $s = 0 ) { return mktime( 0, 0, $s, 11, 1 + $d, 2026 ); }; // 1 November 2026 has 25 hours in some zones
        $v = ticketsystemReport::volume(
            array( array( 'created' => $day( 0, 10 ) ), array( 'created' => $day( 0, 20 ) ), array( 'created' => $day( 1, 5 ) ) ),
            array( array( 'resolved' => $day( 2, 1 ), 'closed' => 0 ), array( 'resolved' => 0, 'closed' => $day( 1 ) ),
                   array( 'resolved' => $to + 5, 'closed' => 0 ) ), $from, $to, 'day' );
        $this->assertSame( array( '2026-11-01', '2026-11-02', '2026-11-03' ), array_column( $v, 'key' ) );
        $this->assertSame( array( 2, 1, 0 ), array_column( $v, 'opened' ) );
        $this->assertSame( array( 0, 1, 1 ), array_column( $v, 'solved' ) );
    }

    public function testFiguresTimesAndMedian()
    {
        $row = array( 'status' => 'closed', 'category' => 'billing', 'priority' => 'low', 'assignee_id' => 3, 'created' => 0, 'closed' => 0 );
        $f = ticketsystemReport::figures( array( array( 'responded' => 60, 'resolved' => 3600 ) + $row, array( 'responded' => 180, 'resolved' => 7200 ) + $row,
                                                 array( 'responded' => 6000, 'resolved' => 36000 ) + $row ), array( 'resolved', 'closed' ), 99999 );
        $this->assertSame( array( 3, 3 ), array( $f['first_response']['count'], $f['first_response']['median_minutes'] ) );
        $this->assertSame( 35, $f['first_response']['avg_minutes'] );
        $this->assertSame( 2.0, (float)$f['resolution']['median_hours'] );
        $this->assertSame( 0, $f['csat']['count'] );
    }

    public function testBacklogAndWorkload()
    {
        $now = 10 * 86400;
        $b = ticketsystemReport::backlog( array(
            array( 'created' => $now - 3600, 'category' => 'billing', 'priority' => 'high', 'last_post_staff' => 0 ),
            array( 'created' => $now - 2 * 86400, 'category' => 'billing', 'priority' => 'low', 'last_post_staff' => 1 ),
            array( 'created' => 0, 'category' => 'hosting', 'priority' => 'low', 'last_post_staff' => 0 ) ), $now );
        $this->assertSame( array( 'under_1d' => 1, '1_3d' => 1, '3_7d' => 0, '7_30d' => 1, 'over_30d' => 0 ), $b['ages'] );
        $this->assertSame( array( 'billing' => 2, 'hosting' => 1 ), $b['by_category'] );
        $this->assertSame( 10 * 86400, $b['oldest_seconds'] );
        $this->assertSame( 2, $b['waiting_staff'] );
        $w = ticketsystemReport::workload( array( array( 'assignee_id' => 7 ), array( 'assignee_id' => 7 ), array( 'assignee_id' => 0 ) ),
                                          array( array( 'author_id' => 8, 'ticket_id' => 1 ), array( 'author_id' => 8, 'ticket_id' => 1 ),
                                                 array( 'author_id' => 7, 'ticket_id' => 2 ) ) );
        $this->assertSame( array( 7, 8, 0 ), array_keys( $w ), 'busiest first, "nobody" last' );
        $this->assertSame( array( 'open' => 2, 'replies' => 1, 'tickets' => 1 ), $w[7] );
        $this->assertSame( array( 'open' => 0, 'replies' => 2, 'tickets' => 1 ), $w[8] );
        $this->assertSame( 1, $w[0]['open'] );
    }
}
