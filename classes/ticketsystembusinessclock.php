<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The clock an SLA target runs on (doc/sla.md): business hours (working days, opening hours, holidays, a time zone)
 * or the calendar (around the clock). Pure: no database, no settings; ticketsystemSla builds it from [SLASettings].
 *
 * add() answers when a number of working seconds counted from a moment ends; between() how many working seconds lie
 * between two moments. Both walk the days in the clock's time zone, so a change to or from daylight saving time
 * moves the opening hours with the local clock.
 */
class ticketsystemBusinessClock
{
    /** The days add() and between() walk at most (ten years). */
    const MAX_DAYS = 3660;

    /** @var bool */
    protected $calendar;
    /** @var int[] ISO weekdays, 1 = Monday ... 7 = Sunday */
    protected $days;
    /** @var int minutes after midnight */
    protected $open;
    /** @var int minutes after midnight (1440 = midnight of the next day) */
    protected $close;
    /** @var array 'Y-m-d' or 'm-d' => true */
    protected $holidays;
    /** @var DateTimeZone */
    protected $zone;

    /**
     * @param bool $calendar true: around the clock (the other arguments do not matter)
     * @param int[] $days ISO weekdays that are working days
     * @param string $hours opening hours "09:00-17:00" (24:00 = the end of the day)
     * @param string[] $holidays days without work: "2026-12-25" (that day) or "12-25" (every year)
     * @param string $timezone a PHP time zone name; '' = the installation's
     */
    public function __construct( $calendar, array $days = array( 1, 2, 3, 4, 5 ), $hours = '09:00-17:00', array $holidays = array(), $timezone = '' )
    {
        $this->calendar = (bool)$calendar;
        $this->days = array_values( array_unique( array_filter( array_map( 'intval', $days ), function ( $d ) { return $d >= 1 && $d <= 7; } ) ) );
        list( $this->open, $this->close ) = self::parseHours( $hours );
        $this->holidays = array();
        foreach ( $holidays as $h )
        {
            $h = trim( (string)$h );
            if ( preg_match( '/^(\d{4}-)?\d{2}-\d{2}$/', $h ) )
                $this->holidays[$h] = true;
        }
        try
        {
            $this->zone = new DateTimeZone( trim( (string)$timezone ) !== '' ? trim( (string)$timezone ) : date_default_timezone_get() );
        }
        catch ( Exception $e )
        {
            $this->zone = new DateTimeZone( 'UTC' );
        }
        // a clock without a working minute would never end: it counts around the clock instead
        if ( !$this->days || $this->close <= $this->open )
            $this->calendar = true;
    }

    /** A clock around the clock. */
    public static function calendar()
    {
        return new self( true );
    }

    /**
     * The opening hours of "HH:MM-HH:MM" in minutes after midnight (pure); "09:00-17:00" when it reads nothing.
     *
     * @return int[] open, close
     */
    public static function parseHours( $hours )
    {
        if ( !preg_match( '/^\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})\s*$/', (string)$hours, $m ) )
            return array( 540, 1020 );
        $open = min( 1440, (int)$m[1] * 60 + (int)$m[2] );
        $close = min( 1440, (int)$m[3] * 60 + (int)$m[4] );
        return array( $open, $close );
    }

    public function isCalendar()
    {
        return $this->calendar;
    }

    public function timezone()
    {
        return $this->zone->getName();
    }

    /** The working minutes of one working day (1440 around the clock). */
    public function dayMinutes()
    {
        return $this->calendar ? 1440 : $this->close - $this->open;
    }

    /** Whether a local day is a working day. */
    public function isWorkingDay( DateTimeImmutable $day )
    {
        if ( $this->calendar )
            return true;
        return in_array( (int)$day->format( 'N' ), $this->days, true )
            && !isset( $this->holidays[$day->format( 'Y-m-d' )] ) && !isset( $this->holidays[$day->format( 'm-d' )] );
    }

    /** Whether a moment is inside the working hours. */
    public function isWorking( $time )
    {
        if ( $this->calendar )
            return true;
        list( $start, $end ) = $this->window( $this->dayOf( (int)$time ) );
        return $start !== null && (int)$time >= $start && (int)$time < $end;
    }

    /** Midnight of the local day of a moment. */
    protected function dayOf( $time )
    {
        return ( new DateTimeImmutable( '@' . (int)$time ) )->setTimezone( $this->zone )->setTime( 0, 0, 0 );
    }

    /**
     * The working window of a local day as timestamps, or (null, null) on a day without work.
     *
     * @return array start, end
     */
    protected function window( DateTimeImmutable $day )
    {
        if ( !$this->isWorkingDay( $day ) )
            return array( null, null );
        $start = $day->setTime( intdiv( $this->open, 60 ), $this->open % 60 )->getTimestamp();
        $end = $this->close >= 1440 ? $day->modify( '+1 day' )->getTimestamp()
                                    : $day->setTime( intdiv( $this->close, 60 ), $this->close % 60 )->getTimestamp();
        return array( $start, $end );
    }

    /**
     * The moment $seconds working seconds after $from. A start outside the working hours counts from the next
     * opening; 0 seconds is $from itself.
     *
     * @param int $from
     * @param int $seconds
     * @return int
     */
    public function add( $from, $seconds )
    {
        $from = (int)$from;
        $seconds = max( 0, (int)$seconds );
        if ( $this->calendar || $seconds === 0 )
            return $from + $seconds;
        $cursor = $from;
        $day = $this->dayOf( $from );
        for ( $i = 0; $i < self::MAX_DAYS; $i++ )
        {
            list( $start, $end ) = $this->window( $day );
            if ( $start !== null )
            {
                $s = max( $cursor, $start );
                if ( $s < $end )
                {
                    if ( $seconds <= $end - $s )
                        return $s + $seconds;
                    $seconds -= $end - $s;
                }
            }
            $day = $day->modify( '+1 day' );
            $cursor = $day->getTimestamp();
        }
        return $cursor + $seconds;
    }

    /**
     * The working seconds from $from to $to (0 when $to is not later).
     *
     * @return int
     */
    public function between( $from, $to )
    {
        $from = (int)$from;
        $to = (int)$to;
        if ( $to <= $from )
            return 0;
        if ( $this->calendar )
            return $to - $from;
        $sum = 0;
        $day = $this->dayOf( $from );
        for ( $i = 0; $i < self::MAX_DAYS && $day->getTimestamp() < $to; $i++ )
        {
            list( $start, $end ) = $this->window( $day );
            if ( $start !== null )
                $sum += max( 0, min( $end, $to ) - max( $start, $from ) );
            $day = $day->modify( '+1 day' );
        }
        return $sum;
    }
}
