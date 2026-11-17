<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The satisfaction rating (CSAT, doc/csat.md). When a ticket is resolved or closed, its opener gets one mail "How did
 * we do?" with a link per score (1 to 5, 5 = very good); a link opens ticketsystem/rate/<token>/<score>, which needs
 * no sign-in, has the score chosen and takes an optional comment. The token is signed (HMAC-SHA256 over the ticket id,
 * its number, its opening time and the expiry) and works once: a ticket that has a rating takes no token any more.
 * The opener and the users of the ticket's account can also rate on the ticket page while it is done.
 *
 * No rating mail for an imported ticket (source whmcs), a ticket that is not done, one that was asked already (the
 * history event "csat_ask") or one that has a rating. The rating lives in the index row (rating, rating_comment,
 * rated); summary() is the aggregate for the reports ("fetch( 'ticketsystem', 'csat_summary', ... )").
 *
 * The token methods are pure.
 */
class ticketsystemCsat
{
    const MIN = 1;
    const MAX = 5;
    /** Scores that count as satisfied in the CSAT share */
    const SATISFIED = 4;

    /** @var ticketsystemSettings */
    protected $settings;

    public function __construct( ?ticketsystemSettings $settings = null )
    {
        $this->settings = $settings ?: ticketsystemSettings::instance();
    }

    public function enabled()
    {
        return $this->settings->enabled( 'CSATSettings', 'Enabled', 'disabled' );
    }

    public function askByMail()
    {
        return $this->enabled() && $this->settings->enabled( 'CSATSettings', 'AskInMail', 'enabled' );
    }

    public function tokenDays()
    {
        return max( 1, min( 365, (int)$this->settings->value( 'CSATSettings', 'TokenDays', 30 ) ) );
    }

    /** The sources whose tickets are never asked (imports). */
    public function quietSources()
    {
        return array_values( array_map( 'strval', (array)$this->settings->value( 'CSATSettings', 'QuietSources', array( 'whmcs' ) ) ) );
    }

    /** score => label */
    public static function labels()
    {
        return array( 5 => 'Very good', 4 => 'Good', 3 => 'Okay', 2 => 'Bad', 1 => 'Very bad' );
    }

    // -------------------------------------------------------------------------------------------- tokens

    /** The secret: [CSATSettings] TokenSecret, else one derived from the installation (never the mail-in one). */
    public function secret()
    {
        $s = trim( (string)$this->settings->value( 'CSATSettings', 'TokenSecret', '' ) );
        if ( $s !== '' )
            return $s;
        $ini = eZINI::instance();
        return hash( 'sha256', 'ticketsystem-csat|' . $ini->variable( 'DatabaseSettings', 'Database' ) . '|'
                               . $ini->variable( 'DatabaseSettings', 'Password' ) . '|' . eZSys::rootDir() );
    }

    /**
     * A signed token (pure): <ticket id>.<expiry>.<signature>, the signature over the ticket's id, number and
     * opening time, so a token of one ticket never fits another.
     */
    public static function token( $ticketId, $number, $created, $expires, $secret )
    {
        $sig = substr( hash_hmac( 'sha256', 'csat|' . (int)$ticketId . '|' . strtoupper( (string)$number ) . '|' . (int)$created . '|' . (int)$expires,
                                  (string)$secret ), 0, 40 );
        return (int)$ticketId . '.' . base_convert( (string)(int)$expires, 10, 36 ) . '.' . $sig;
    }

    /**
     * The parts of a token (pure): ticket id and expiry, or null when it is malformed.
     *
     * @return array|null id, expires
     */
    public static function parse( $token )
    {
        if ( !is_string( $token ) || !preg_match( '/^(\d{1,10})\.([0-9a-z]{1,8})\.([0-9a-f]{40})$/D', $token, $m ) )
            return null;
        return array( 'id' => (int)$m[1], 'expires' => (int)base_convert( $m[2], 36, 10 ), 'sig' => $m[3] );
    }

    /**
     * Whether a token is valid for a ticket now (pure): well formed, signed for this ticket, not expired. Used once
     * is checked by the caller (the ticket has no rating yet).
     */
    public static function verify( $token, $ticketId, $number, $created, $now, $secret )
    {
        $p = self::parse( $token );
        if ( !$p || $p['id'] !== (int)$ticketId || $p['expires'] < (int)$now )
            return false;
        return hash_equals( self::token( $ticketId, $number, $created, $p['expires'], $secret ), (string)$token );
    }

    /** The token of a ticket now. */
    public function tokenFor( ticketsystemTicket $t, $now = null )
    {
        $now = $now === null ? time() : (int)$now;
        return self::token( (int)$t->attribute( 'id' ), (string)$t->attribute( 'number' ), (int)$t->attribute( 'created' ),
                            $now + $this->tokenDays() * 86400, $this->secret() );
    }

    /**
     * The ticket of a token that may still rate, or an error.
     *
     * @return array ticket (ticketsystemTicket|null), error ('' = fine: invalid, expired, used, off)
     */
    public function ticketForToken( $token, $now = null )
    {
        $now = $now === null ? time() : (int)$now;
        if ( !$this->enabled() )
            return array( 'ticket' => null, 'error' => 'off' );
        $p = self::parse( $token );
        $t = $p ? ticketsystemTicket::fetch( $p['id'] ) : null;
        if ( !$t || !self::verify( $token, (int)$t->attribute( 'id' ), (string)$t->attribute( 'number' ), (int)$t->attribute( 'created' ), $p['expires'], $this->secret() ) )
            return array( 'ticket' => null, 'error' => 'invalid' );
        if ( $p['expires'] < $now )
            return array( 'ticket' => $t, 'error' => 'expired' );
        if ( (int)$t->attribute( 'rated' ) > 0 )
            return array( 'ticket' => $t, 'error' => 'used' );
        if ( !$this->settings->statusSet()->isDone( (string)$t->attribute( 'status' ) ) )
            return array( 'ticket' => $t, 'error' => 'open' );
        return array( 'ticket' => $t, 'error' => '' );
    }

    // --------------------------------------------------------------------------------------------- ask

    /**
     * Whether a ticket gets the rating mail now (pure apart from the settings): done, not imported, no rating.
     *
     * @param bool $asked the mail went out before
     */
    public function shouldAsk( array $row, $asked )
    {
        return $this->askByMail() && !$asked && (int)$row['rated'] === 0 && (int)$row['rating'] === 0
            && $this->settings->statusSet()->isDone( (string)$row['status'] )
            && !in_array( (string)$row['source'], $this->quietSources(), true );
    }

    /**
     * Sends the rating mail of a ticket that was just resolved or closed, once. Called by ticketsystemService after a
     * status change; never by the imports.
     *
     * @return array|null the mail counts, or null when nothing was sent
     */
    public function ask( ticketsystemTicket $t, $now = null )
    {
        $asked = ticketsystemEvent::countBy( array( 'ticket_id' => (int)$t->attribute( 'id' ), 'kind' => 'csat_ask' ) ) > 0;
        if ( !$this->shouldAsk( $t->values(), $asked ) )
            return null;
        $user = eZUser::fetch( (int)$t->attribute( 'opener_id' ) );
        if ( !$user instanceof eZUser || !$user->isEnabled() || trim( (string)$user->attribute( 'email' ) ) === '' )
            return null;
        // the event first: a second status change while the mail is on its way asks no more
        ticketsystemService::event( $t, 0, 'csat_ask', '', '' );
        $mailer = new ticketsystemSlaMailer( $this->settings );
        $token = $this->tokenFor( $t, $now );
        $links = array();
        foreach ( self::labels() as $score => $label )
            $links[] = array( 'score' => $score, 'label' => $label, 'url' => $mailer->siteUrl() . '/ticketsystem/rate/' . $token . '/' . $score );
        return $mailer->rating( $t, $user, $links, $this->tokenDays() );
    }

    // -------------------------------------------------------------------------------------------- store

    /** A comment as stored: one line, at most 1000 characters. */
    public static function cleanComment( $comment )
    {
        return mb_substr( trim( preg_replace( '/\s+/u', ' ', ticketsystemText::clean( (string)$comment ) ) ), 0, 1000 );
    }

    /**
     * Stores a rating (the caller checked who may rate).
     *
     * @param string $channel web (the ticket page) or mail (a token)
     * @return array ok, message, errors
     */
    public function record( ticketsystemTicket $t, $score, $comment, $actorId, $channel )
    {
        $score = (int)$score;
        if ( $score < self::MIN || $score > self::MAX )
            return array( 'ok' => false, 'message' => 'Please choose from 1 to 5.', 'errors' => array( 'rating' => 'Please choose from 1 to 5.' ) );
        $old = (int)$t->attribute( 'rating' );
        $t->setAttribute( 'rating', $score );
        $t->setAttribute( 'rating_comment', self::cleanComment( $comment ) );
        $t->setAttribute( 'rated', time() );
        $t->store();
        ticketsystemService::event( $t, (int)$actorId, 'rate', (string)$old, $score . ':' . ( $channel === 'mail' ? 'mail' : 'web' ) );
        return array( 'ok' => true, 'message' => 'Thank you for your rating.', 'errors' => array() );
    }

    /** Stores the rating of a token, once. */
    public function rateByToken( $token, $score, $comment )
    {
        $r = $this->ticketForToken( $token );
        if ( $r['error'] !== '' )
            return array( 'ok' => false, 'message' => self::tokenMessage( $r['error'] ), 'errors' => array(), 'reason' => $r['error'] );
        return $this->record( $r['ticket'], $score, $comment, (int)$r['ticket']->attribute( 'opener_id' ), 'mail' ) + array( 'reason' => '' );
    }

    public static function tokenMessage( $error )
    {
        $m = array( 'invalid' => 'This rating link is not valid.', 'expired' => 'This rating link has expired.',
                    'used' => 'This ticket was rated already. Thank you!', 'open' => 'This ticket is open again; you can rate it when it is solved.',
                    'off' => 'Ratings are not collected at the moment.' );
        return isset( $m[$error] ) ? $m[$error] : 'This rating link is not valid.';
    }

    // ------------------------------------------------------------------------------------------- figures

    /**
     * The aggregate of rated rows (pure): count, average, the share of satisfied (4 and 5), the distribution and the
     * same per queue and per assignee.
     *
     * @param array[] $rows rating, category, assignee_id
     */
    public static function figures( array $rows )
    {
        $one = function () { return array( 'count' => 0, 'sum' => 0, 'satisfied' => 0, 'dist' => array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ) ); };
        $add = function ( array &$f, $score ) {
            $f['count']++;
            $f['sum'] += $score;
            $f['dist'][$score]++;
            if ( $score >= ticketsystemCsat::SATISFIED )
                $f['satisfied']++;
        };
        $done = function ( array $f ) {
            $f['avg'] = $f['count'] ? round( $f['sum'] / $f['count'], 2 ) : 0;
            $f['csat'] = $f['count'] ? (int)round( $f['satisfied'] * 100 / $f['count'] ) : 0;
            unset( $f['sum'] );
            return $f;
        };
        $all = $one();
        $byQueue = array();
        $byAssignee = array();
        foreach ( $rows as $r )
        {
            $s = (int)$r['rating'];
            if ( $s < self::MIN || $s > self::MAX )
                continue;
            $add( $all, $s );
            $q = (string)$r['category'];
            $a = (int)$r['assignee_id'];
            if ( !isset( $byQueue[$q] ) )
                $byQueue[$q] = $one();
            if ( !isset( $byAssignee[$a] ) )
                $byAssignee[$a] = $one();
            $add( $byQueue[$q], $s );
            $add( $byAssignee[$a], $s );
        }
        return $done( $all ) + array( 'by_queue' => array_map( $done, $byQueue ), 'by_assignee' => array_map( $done, $byAssignee ) );
    }

    /**
     * The aggregate of the ratings given in a period (by "rated"), optionally of one queue or assignee, plus how many
     * tickets were asked.
     *
     * @param array $filter from, to (timestamps; 0 = open), category, assignee_id
     */
    public function summary( array $filter = array() )
    {
        $where = $this->where( $filter );
        $rows = array();
        $db = eZDB::instance();
        for ( $offset = 0; ; $offset += 1000 )
        {
            $batch = $db->arrayQuery( 'SELECT rating, category, assignee_id FROM ticketsystem_ticket WHERE ' . $where,
                                      array( 'offset' => $offset, 'limit' => 1000 ) );
            $batch = is_array( $batch ) ? $batch : array();
            foreach ( $batch as $r )
                $rows[] = array_change_key_case( $r, CASE_LOWER );
            if ( count( $batch ) < 1000 )
                break;
        }
        $f = self::figures( $rows );
        $cats = $this->settings->categories();
        foreach ( $f['by_queue'] as $q => &$v )
            $v['label'] = isset( $cats[$q] ) ? $cats[$q] : $q;
        unset( $v );
        foreach ( $f['by_assignee'] as $a => &$v )
        {
            $o = $a ? eZContentObject::fetch( (int)$a ) : null;
            $v['label'] = $o ? (string)$o->attribute( 'name' ) : 'Nobody';
        }
        unset( $v );
        $from = isset( $filter['from'] ) ? (int)$filter['from'] : 0;
        $to = isset( $filter['to'] ) ? (int)$filter['to'] : 0;
        $asked = ticketsystemEvent::countBy( array( 'kind' => 'csat_ask' ) + ( $from || $to ? array( 'created' => array( false, array( $from, $to ?: 2147483647 ) ) ) : array() ) );
        $f['asked'] = $asked;
        $f['response_rate'] = $asked ? (int)round( min( $asked, $f['count'] ) * 100 / $asked ) : 0;
        $f['from'] = $from;
        $f['to'] = $to;
        return $f;
    }

    /** The SQL condition of rated tickets for a filter; every value cast or escaped. */
    protected function where( array $filter )
    {
        $db = eZDB::instance();
        $parts = array( 'rating >= ' . self::MIN, 'rated > 0' );
        if ( !empty( $filter['from'] ) )
            $parts[] = 'rated >= ' . (int)$filter['from'];
        if ( !empty( $filter['to'] ) )
            $parts[] = 'rated < ' . (int)$filter['to'];
        if ( isset( $filter['category'] ) && (string)$filter['category'] !== '' )
            $parts[] = "category = '" . $db->escapeString( (string)$filter['category'] ) . "'";
        if ( isset( $filter['assignee_id'] ) && (int)$filter['assignee_id'] > 0 )
            $parts[] = 'assignee_id = ' . (int)$filter['assignee_id'];
        return implode( ' AND ', $parts );
    }

    /**
     * The latest ratings for staff, newest first: number, subject, score, label, comment, rated, category, assignee.
     *
     * @return array[]
     */
    public function latest( array $filter = array(), $offset = 0, $limit = 50 )
    {
        $out = array();
        $labels = self::labels();
        $cats = $this->settings->categories();
        foreach ( ticketsystemTicket::fetchWhere( $this->where( $filter ), (int)$offset, max( 1, (int)$limit ), 'rated DESC, id DESC' ) as $t )
        {
            $s = (int)$t->attribute( 'rating' );
            $out[] = array( 'id' => (int)$t->attribute( 'id' ), 'number' => (string)$t->attribute( 'number' ), 'subject' => (string)$t->attribute( 'subject' ),
                            'score' => $s, 'label' => isset( $labels[$s] ) ? $labels[$s] : '', 'comment' => (string)$t->attribute( 'rating_comment' ),
                            'rated' => (int)$t->attribute( 'rated' ), 'category' => (string)$t->attribute( 'category' ),
                            'category_label' => isset( $cats[$t->attribute( 'category' )] ) ? $cats[$t->attribute( 'category' )] : '',
                            'assignee_id' => (int)$t->attribute( 'assignee_id' ), 'satisfied' => $s >= self::SATISFIED );
        }
        return $out;
    }

    /** The rating of one ticket for a view: enabled, score, label, comment, rated, asked. */
    public function forTicket( ticketsystemTicket $t )
    {
        $labels = self::labels();
        $s = (int)$t->attribute( 'rating' );
        return array( 'enabled' => $this->enabled(), 'score' => $s, 'label' => isset( $labels[$s] ) ? $labels[$s] : '',
                      'comment' => (string)$t->attribute( 'rating_comment' ), 'rated' => (int)$t->attribute( 'rated' ),
                      'asked' => ticketsystemEvent::countBy( array( 'ticket_id' => (int)$t->attribute( 'id' ), 'kind' => 'csat_ask' ) ) > 0,
                      'labels' => $labels );
    }
}
