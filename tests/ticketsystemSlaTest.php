<?php
/**
 * DB-less tests of the SLA timers, escalation steps and satisfaction ratings (doc/sla.md, doc/csat.md): the business
 * clock (working days, hours, holidays, daylight saving time), targets on their clocks, the pause maths, the
 * escalation steps, the signed rating tokens and the CSAT aggregate.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 */

use PHPUnit\Framework\TestCase;

class ticketsystemSlaTest extends TestCase
{
    const TZ = 'America/Los_Angeles';

    /** A local time in the test time zone. */
    private static function at( $text )
    {
        return ( new DateTimeImmutable( $text, new DateTimeZone( self::TZ ) ) )->getTimestamp();
    }

    private function clock()
    {
        return new ticketsystemBusinessClock( false, array( 1, 2, 3, 4, 5 ), '09:00-17:00', array( '12-25', '2026-11-26' ), self::TZ );
    }

    private function settings( array $more = array() )
    {
        return new ticketsystemSettings( array_replace_recursive( array(
            'SLASettings' => array( 'Enabled' => 'enabled', 'DefaultLevel' => 'standard', 'Levels' => array( 'standard' ),
                                    'PausedStatuses' => array( 'waiting_customer' ), 'WarnPercent' => 80, 'Clock' => 'business',
                                    'BusinessDays' => array( 1, 2, 3, 4, 5 ), 'BusinessHours' => '09:00-17:00', 'TimeZone' => self::TZ,
                                    'Holidays' => array( '12-25', '2026-11-26' ), 'CalendarPriorities' => array( 'urgent' ),
                                    'KatalystEntitlements' => 'enabled',
                                    'EscalationSteps' => array( '80:assignee', '100:leads', '100:raise', '100:mark' ) ),
            'SLA_standard' => array( 'Label' => 'Standard',
                                     'FirstResponse' => array( 'medium' => '480', 'urgent' => '60', 'billing:medium' => '120' ),
                                     'Resolution' => array( 'medium' => '2400', 'urgent' => '480' ) ),
            'CSATSettings' => array( 'Enabled' => 'enabled', 'AskInMail' => 'enabled', 'QuietSources' => array( 'whmcs' ) ),
        ), $more ) );
    }

    protected function tearDown(): void
    {
        ticketsystemSla::$katalystLevels = null;
    }

    // -------------------------------------------------------------------------------------- business clock

    public function testClockAddsWorkingTimeAcrossNightsWeekendsAndHolidays()
    {
        $c = $this->clock();
        // Monday 2026-11-16 10:00 + 2 h = 12:00
        $this->assertSame( self::at( '2026-11-16 12:00' ), $c->add( self::at( '2026-11-16 10:00' ), 7200 ) );
        // Monday 16:00 + 2 h = Tuesday 10:00 (1 h Monday, 1 h Tuesday)
        $this->assertSame( self::at( '2026-11-17 10:00' ), $c->add( self::at( '2026-11-16 16:00' ), 7200 ) );
        // Friday 2026-11-20 16:30 + 1 h = Monday 09:30
        $this->assertSame( self::at( '2026-11-23 09:30' ), $c->add( self::at( '2026-11-20 16:30' ), 3600 ) );
        // a start on Saturday counts from Monday's opening
        $this->assertSame( self::at( '2026-11-23 11:00' ), $c->add( self::at( '2026-11-21 13:00' ), 7200 ) );
        // before the opening: from 09:00
        $this->assertSame( self::at( '2026-11-16 09:45' ), $c->add( self::at( '2026-11-16 06:00' ), 2700 ) );
        // Wednesday 16:00 + 2 h: Thursday 2026-11-26 is a holiday (that date) -> Friday 10:00
        $this->assertSame( self::at( '2026-11-27 10:00' ), $c->add( self::at( '2026-11-25 16:00' ), 7200 ) );
        // 12-25 every year: Thursday 2026-12-24 16:00 + 2 h = Monday 2026-12-28 10:00 (Friday 25 off, weekend)
        $this->assertSame( self::at( '2026-12-28 10:00' ), $c->add( self::at( '2026-12-24 16:00' ), 7200 ) );
        // one business day (8 h) from Monday 09:00 ends at 17:00
        $this->assertSame( self::at( '2026-11-16 17:00' ), $c->add( self::at( '2026-11-16 09:00' ), 8 * 3600 ) );
        $this->assertSame( 480, $c->dayMinutes() );
    }

    public function testClockCountsWorkingTimeBetweenMoments()
    {
        $c = $this->clock();
        $this->assertSame( 7200, $c->between( self::at( '2026-11-16 16:00' ), self::at( '2026-11-17 10:00' ) ) );
        $this->assertSame( 3600, $c->between( self::at( '2026-11-20 16:30' ), self::at( '2026-11-23 09:30' ) ) );
        $this->assertSame( 0, $c->between( self::at( '2026-11-21 08:00' ), self::at( '2026-11-22 20:00' ) ), 'a weekend has no working time' );
        $this->assertSame( 0, $c->between( self::at( '2026-11-17 10:00' ), self::at( '2026-11-16 10:00' ) ) );
        // a whole working week: 5 x 8 h
        $this->assertSame( 40 * 3600, $c->between( self::at( '2026-11-09 00:00' ), self::at( '2026-11-16 00:00' ) ) );
        // add() and between() agree
        $from = self::at( '2026-11-19 15:10' );
        $this->assertSame( 12345, $c->between( $from, $c->add( $from, 12345 ) ) );
    }

    public function testClockKeepsLocalHoursOverDaylightSavingTime()
    {
        $c = $this->clock();
        // the clocks went back on Sunday 2026-11-01: Friday 16:00 + 2 h is still Monday 10:00 local time
        $this->assertSame( self::at( '2026-11-02 10:00' ), $c->add( self::at( '2026-10-30 16:00' ), 7200 ) );
        $this->assertSame( '10:00', ( new DateTimeImmutable( '@' . $c->add( self::at( '2026-10-30 16:00' ), 7200 ) ) )->setTimezone( new DateTimeZone( self::TZ ) )->format( 'H:i' ) );
    }

    public function testCalendarClockAndBrokenHours()
    {
        $c = ticketsystemBusinessClock::calendar();
        $this->assertSame( 1000 + 3600, $c->add( 1000, 3600 ) );
        $this->assertSame( 500, $c->between( 1000, 1500 ) );
        // no working day, or a closing before the opening: around the clock instead of never
        $this->assertTrue( ( new ticketsystemBusinessClock( false, array(), '09:00-17:00' ) )->isCalendar() );
        $this->assertTrue( ( new ticketsystemBusinessClock( false, array( 1 ), '17:00-09:00' ) )->isCalendar() );
        $this->assertSame( array( 540, 1020 ), ticketsystemBusinessClock::parseHours( 'nonsense' ) );
        $this->assertSame( array( 0, 1440 ), ticketsystemBusinessClock::parseHours( '00:00-24:00' ) );
    }

    // -------------------------------------------------------------------------------------------- targets

    public function testTargetsOnTheirClocks()
    {
        $s = new ticketsystemSla( $this->settings() );
        $this->assertSame( array( 480, true ), $s->spec( 'standard', 'medium', 'FirstResponse' ) );
        $this->assertSame( array( 120, true ), $s->spec( 'standard', 'medium', 'FirstResponse', 'billing' ), 'a queue\'s own target' );
        $this->assertSame( array( 60, false ), $s->spec( 'standard', 'urgent', 'FirstResponse' ), 'urgent runs around the clock' );
        $this->assertSame( array( 0, false ), $s->spec( 'standard', 'low', 'FirstResponse' ), 'no target' );
        $this->assertSame( array( 960, true ), ticketsystemSla::parseText( '2 business days', 480 ) );
        $this->assertSame( array( 30, false ), ticketsystemSla::parseText( '30 minutes', 480 ) );
        $this->assertSame( array( 240, false ), ticketsystemSla::parseText( '4 hours', 480 ) );
        $this->assertNull( ticketsystemSla::parseText( 'best effort', 480 ) );
        // a Katalyst level: the first response from its text, the resolution from the default level
        ticketsystemSla::$katalystLevels = array( 'sla3' => array( 'label' => 'SLA 3 Business',
                                                                   'response' => array( 'critical' => '2 hours', 'minor' => '1 business day' ) ) );
        $this->assertSame( array( 480, true ), $s->spec( 'sla3', 'medium', 'FirstResponse' ) );
        $this->assertSame( array( 120, false ), $s->spec( 'sla3', 'urgent', 'FirstResponse' ) );
        $this->assertSame( array( 2400, true ), $s->spec( 'sla3', 'medium', 'Resolution' ) );
        $this->assertSame( 'SLA 3 Business', $s->levelLabel( 'sla3' ) );
        // due times: Monday 16:00 + 8 business hours = Tuesday 16:00; urgent + 60 minutes around the clock
        $open = self::at( '2026-11-16 16:00' );
        $this->assertSame( array( self::at( '2026-11-17 16:00' ), self::at( '2026-11-23 16:00' ) ), $s->dues( $open, 'standard', 'medium' ) );
        $this->assertSame( array( $open + 3600, $open + 8 * 3600 ), $s->dues( $open, 'standard', 'urgent' ) );
    }

    private function row( array $more = array() )
    {
        $open = self::at( '2026-11-16 09:00' );
        return $more + array( 'id' => 1, 'created' => $open, 'sla_level' => 'standard', 'priority' => 'medium', 'category' => '',
                              'response_due' => self::at( '2026-11-16 17:00' ), 'resolve_due' => self::at( '2026-11-20 17:00' ),
                              'responded' => 0, 'resolved' => 0, 'paused_since' => 0, 'escalation' => 0, 'breached' => 0 );
    }

    public function testStatesInBusinessTime()
    {
        $s = new ticketsystemSla( $this->settings() );
        $row = $this->row();
        $this->assertSame( 'ok', $s->state( $row, self::at( '2026-11-16 12:00' ) )['response']['state'] );
        // 80 % of 8 h is 15:24: due soon
        $st = $s->state( $row, self::at( '2026-11-16 15:30' ) );
        $this->assertSame( 'warn', $st['response']['state'] );
        $this->assertSame( 81, $st['response']['pct'] );
        $this->assertSame( 5400, $st['response']['left'], 'working seconds left' );
        $this->assertSame( 'breached', $s->state( $row, self::at( '2026-11-16 17:01' ) )['response']['state'] );
        // the night does not count: on Tuesday 08:00 the response is late by 1 minute of working time ... none
        $this->assertSame( 0, $s->state( $row, self::at( '2026-11-17 08:00' ) )['response']['left'] );
        $this->assertSame( 'met', $s->state( $this->row( array( 'responded' => self::at( '2026-11-16 11:00' ) ) ), self::at( '2026-11-18 11:00' ) )['response']['state'] );
        $this->assertSame( 'Due soon', ticketsystemSla::stateLabel( 'warn' ) );
    }

    public function testPauseMovesDueTimesByWorkingTime()
    {
        $s = new ticketsystemSla( $this->settings() );
        $row = $this->row();
        // waiting on the customer from Monday 16:00
        $p = $s->onStatus( $row, 'open', 'waiting_customer', self::at( '2026-11-16 16:00' ) );
        $this->assertSame( array( 'paused_since' => self::at( '2026-11-16 16:00' ) ), $p );
        $paused = $p + $row;
        $this->assertSame( 'paused', $s->state( $paused, self::at( '2026-11-17 15:00' ) )['response']['state'] );
        // the customer answers on Tuesday 10:00: 2 working hours paused (16-17, 9-10), both due times move on by them
        $r = $s->onStatus( $paused, 'waiting_customer', 'waiting_staff', self::at( '2026-11-17 10:00' ) );
        $this->assertSame( array( 'response_due' => self::at( '2026-11-17 11:00' ), 'resolve_due' => self::at( '2026-11-23 11:00' ), 'paused_since' => 0 ), $r );
        // a pause over the weekend costs nothing of the weekend
        $w = $s->onStatus( array( 'paused_since' => self::at( '2026-11-20 17:00' ) ) + $row, 'waiting_customer', 'waiting_staff', self::at( '2026-11-23 09:00' ) );
        $this->assertSame( $row['response_due'], $w['response_due'] );
        // a resolved ticket pauses; an answered first response stays where it is
        $this->assertSame( array( 'paused_since' => 100 ), $s->onStatus( $row, 'open', 'resolved', 100, true ) );
        $x = $s->onStatus( array( 'paused_since' => self::at( '2026-11-16 16:00' ), 'responded' => 5 ) + $row, 'resolved', 'waiting_staff', self::at( '2026-11-17 10:00' ) );
        $this->assertArrayNotHasKey( 'response_due', $x );
        // urgent: the pause counts around the clock
        $u = $this->row( array( 'priority' => 'urgent', 'response_due' => 10000, 'resolve_due' => 50000, 'paused_since' => 1000 ) );
        $this->assertSame( array( 'response_due' => 10500, 'resolve_due' => 50500, 'paused_since' => 0 ), $s->onStatus( $u, 'waiting_customer', 'open', 1500 ) );
    }

    // ----------------------------------------------------------------------------------------- escalation

    public function testEscalationStepsRunOncePerTargetAndTicket()
    {
        $s = new ticketsystemSla( $this->settings() );
        $row = $this->row();
        $this->assertSame( array(), $s->steps( $row, self::at( '2026-11-16 12:00' ) ) );
        // 80 %: the assignee
        $steps = $s->steps( $row, self::at( '2026-11-16 15:30' ) );
        $this->assertSame( array( 'response:80:assignee' ), array_column( $steps, 'key' ) );
        $this->assertSame( 1, $steps[0]['level'] );
        // breached: the leads, the priority, the mark; the assignee step only when it has not run
        $late = self::at( '2026-11-16 17:30' );
        $this->assertSame( array( 'response:80:assignee', 'response:100:leads', 'raise', 'mark' ), array_column( $s->steps( $row, $late ), 'key' ) );
        $this->assertSame( array( 'response:100:leads', 'raise', 'mark' ), array_column( $s->steps( $row, $late, array( 'response:80:assignee' ) ), 'key' ) );
        $this->assertSame( 2, $s->steps( $row, $late, array( 'response:80:assignee' ) )[0]['level'] );
        $this->assertSame( array(), $s->steps( $row, $late, array( 'response:80:assignee', 'response:100:leads', 'raise', 'mark' ) ) );
        // the resolution target has its own steps; raise and mark do not come again
        $resLate = $this->row( array( 'responded' => self::at( '2026-11-16 10:00' ) ) );
        $this->assertSame( array( 'resolution:80:assignee', 'resolution:100:leads' ),
                           array_column( $s->steps( $resLate, self::at( '2026-11-23 10:00' ), array( 'raise', 'mark' ) ), 'key' ) );
        // answered late: the first response has no more steps
        $this->assertSame( array(), $s->steps( $this->row( array( 'responded' => self::at( '2026-11-17 12:00' ), 'resolve_due' => 0 ) ), self::at( '2026-11-18 12:00' ) ) );
        // paused before 80 %: nothing while it waits
        $this->assertSame( array(), $s->steps( $this->row( array( 'paused_since' => self::at( '2026-11-16 12:00' ) ) ), self::at( '2026-11-18 12:00' ) ) );
        // the level and breach flags
        $c = $s->check( $row, $late );
        $this->assertSame( array( 'escalation' => 2, 'breached' => ticketsystemSla::BREACH_RESPONSE, 'raise' => true ), $c );
    }

    public function testEscalationStepSettings()
    {
        $s = new ticketsystemSla( $this->settings( array( 'SLASettings' => array( 'EscalationSteps' => array( '100:mark', 'junk', '50:group', '90:nonsense', '100:leads' ) ) ) ) );
        $this->assertSame( array( array( 'percent' => 50, 'action' => 'group' ), array( 'percent' => 100, 'action' => 'mark' ), array( 'percent' => 100, 'action' => 'leads' ) ),
                           $s->escalationSteps() );
        $this->assertSame( 'mark', ticketsystemSla::stepKey( 'response', 100, 'mark' ) );
        $this->assertSame( 'resolution:50:group', ticketsystemSla::stepKey( 'resolution', 50, 'group' ) );
    }

    // ---------------------------------------------------------------------------------------------- CSAT

    public function testRatingTokensAreSignedBoundAndExpire()
    {
        $secret = str_repeat( 'k', 32 );
        $t = ticketsystemCsat::token( 42, '7X-2026-00042', 1000, 5000, $secret );
        $this->assertMatchesRegularExpression( '/^42\.[0-9a-z]+\.[0-9a-f]{40}$/', $t );
        $this->assertTrue( ticketsystemCsat::verify( $t, 42, '7X-2026-00042', 1000, 4999, $secret ) );
        $this->assertFalse( ticketsystemCsat::verify( $t, 42, '7X-2026-00042', 1000, 5001, $secret ), 'expired' );
        $this->assertFalse( ticketsystemCsat::verify( $t, 43, '7X-2026-00042', 1000, 10, $secret ), 'another ticket id' );
        $this->assertFalse( ticketsystemCsat::verify( $t, 42, '7X-2026-00043', 1000, 10, $secret ), 'another number' );
        $this->assertFalse( ticketsystemCsat::verify( $t, 42, '7X-2026-00042', 1001, 10, $secret ), 'a ticket opened at another time' );
        $this->assertFalse( ticketsystemCsat::verify( $t, 42, '7X-2026-00042', 1000, 10, 'other secret' ), 'another secret' );
        // a later expiry with the old signature does not pass
        $forged = preg_replace( '/^42\.[0-9a-z]+\./', '42.' . base_convert( '999999', 10, 36 ) . '.', $t );
        $this->assertFalse( ticketsystemCsat::verify( $forged, 42, '7X-2026-00042', 1000, 10, $secret ) );
        $this->assertNull( ticketsystemCsat::parse( '42.x.y' ) );
        $this->assertNull( ticketsystemCsat::parse( array() ) );
        $this->assertSame( array( 'id' => 42, 'expires' => 5000 ), array_slice( ticketsystemCsat::parse( $t ), 0, 2 ) );
    }

    public function testWhoIsAskedForARating()
    {
        $c = new ticketsystemCsat( $this->settings() );
        $row = array( 'status' => 'resolved', 'rated' => 0, 'rating' => 0, 'source' => 'web' );
        $this->assertTrue( $c->shouldAsk( $row, false ) );
        $this->assertFalse( $c->shouldAsk( $row, true ), 'asked once' );
        $this->assertFalse( $c->shouldAsk( array( 'source' => 'whmcs' ) + $row, false ), 'imported history' );
        $this->assertFalse( $c->shouldAsk( array( 'status' => 'waiting_staff' ) + $row, false ), 'not done' );
        $this->assertFalse( $c->shouldAsk( array( 'rated' => 5, 'rating' => 4 ) + $row, false ), 'rated' );
        $this->assertTrue( $c->shouldAsk( array( 'status' => 'closed' ) + $row, false ) );
        $off = new ticketsystemCsat( $this->settings( array( 'CSATSettings' => array( 'AskInMail' => 'disabled' ) ) ) );
        $this->assertFalse( $off->shouldAsk( $row, false ) );
    }

    public function testCsatFigures()
    {
        $f = ticketsystemCsat::figures( array(
            array( 'rating' => 5, 'category' => 'billing', 'assignee_id' => 7 ),
            array( 'rating' => 4, 'category' => 'billing', 'assignee_id' => 7 ),
            array( 'rating' => 2, 'category' => 'hosting', 'assignee_id' => 0 ),
            array( 'rating' => 0, 'category' => 'hosting', 'assignee_id' => 0 ),
            array( 'rating' => 9, 'category' => 'hosting', 'assignee_id' => 0 ),
        ) );
        $this->assertSame( 3, $f['count'] );
        $this->assertSame( 3.67, $f['avg'] );
        $this->assertSame( 67, $f['csat'] );
        $this->assertSame( array( 1 => 0, 2 => 1, 3 => 0, 4 => 1, 5 => 1 ), $f['dist'] );
        $this->assertSame( 100, $f['by_queue']['billing']['csat'] );
        $this->assertSame( 0, $f['by_queue']['hosting']['csat'] );
        $this->assertSame( 2, $f['by_assignee'][7]['count'] );
        $this->assertSame( 0, ticketsystemCsat::figures( array() )['csat'] );
    }
}
