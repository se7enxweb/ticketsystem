<?php
/**
 * ticketsystem/time: the time spent on tickets (doc/time-tracking.md), staff with the policy ticketsystem/report, within
 * the reader's read scope: the entries of a period, the sums (all, billable, covered by an SLA or warranty, billed),
 * per account and per staff member.
 *
 * View parameters, never GET parameters: (from)/<YYYY-MM-DD>, (to)/<YYYY-MM-DD> (inclusive; default: this month),
 * (account)/<account key>, (user)/<staff user id>, (billable)/yes|no, (billed)/yes|no, (format)/csv (the entries as
 * CSV: dates, ticket numbers, account keys, staff names and minutes; never the text of a post or a note). The filter
 * form posts here (TimeRun with the form token) and is redirected to the view parameters.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'report', '/ticketsystem/time' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$time = new ticketsystemTime( $settings );

/** The view parameters of a filter (pure). */
$url = function ( array $f, $csv = false ) {
    $out = '/ticketsystem/time/(from)/' . $f['from'] . '/(to)/' . $f['to'];
    if ( $f['account'] !== '' )
        $out .= '/(account)/' . rawurlencode( $f['account'] );
    if ( $f['user'] )
        $out .= '/(user)/' . (int)$f['user'];
    foreach ( array( 'billable', 'billed' ) as $k )
        if ( $f[$k] !== 'all' )
            $out .= '/(' . $k . ')/' . $f[$k];
    return $out . ( $csv ? '/(format)/csv' : '' );
};

if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' )
{
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
    {
        ticketsystemView::flash( array(), array( 'The form had expired. Please choose the period again.' ) );
        return $Module->redirectTo( '/ticketsystem/time' );
    }
    $f = ticketsystemTime::filter( array( 'from' => (string)$http->postVariable( 'TimeFrom', '' ), 'to' => (string)$http->postVariable( 'TimeTo', '' ),
                                          'account' => trim( (string)$http->postVariable( 'TimeAccount', '' ) ), 'user' => (string)$http->postVariable( 'TimeUser', '' ),
                                          'billable' => (string)$http->postVariable( 'TimeBillable', '' ), 'billed' => (string)$http->postVariable( 'TimeBilled', '' ) ) );
    return $Module->redirectTo( $url( $f ) );
}

$params = isset( $Params['UserParameters'] ) ? (array)$Params['UserParameters'] : array();
$f = ticketsystemTime::filter( $params );
$report = $time->report( $access, $f );

if ( isset( $params['format'] ) && $params['format'] === 'csv' )
{
    $csv = ticketsystemTime::csv( $report['rows'] );
    while ( ob_get_level() > 0 )
        ob_end_clean();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="ticket-time-' . $f['from'] . '-to-' . $f['to'] . '.csv"' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Cache-Control: private, no-store' );
    echo $csv;
    eZExecution::cleanExit();
}

$Result = ticketsystemView::render( $user, 'time', 'time', array(
    'filter' => $f, 'rows' => $report['rows'], 'total' => $report['total'], 'sums' => $report['sums'],
    'staff' => ( new ticketsystemService( $access, $settings ) )->staffMembers(),
    'enabled' => $time->enabled(), 'csv_url' => $url( $f, true ),
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Time' ) ) );
