<?php
/**
 * ticketsystem/reports: the help desk's figures for a period (doc/reports.md), staff only (policy ticketsystem/report),
 * within the reader's read scope:
 * - volume: tickets opened and solved per day, week or month; by status, queue and priority;
 * - first response and resolution times (average, median);
 * - SLA targets met and satisfaction ratings (from the SLA part's aggregate when it is there);
 * - the backlog by age, queue and priority; the workload of every staff member (open tickets, replies in the period).
 *
 * View parameters, never GET parameters: (from)/<YYYY-MM-DD>, (to)/<YYYY-MM-DD> (both inclusive; default the last
 * [ReportSettings] DefaultDays days), (queue)/<category>, (period)/day|week|month, (days)/<n> (the last n days, 1.1),
 * (format)/csv downloads the tickets opened in the period (index data only: never a note, never the text of a post).
 * The filter form posts here (ReportRun, with the form token) and is redirected to the view parameters.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'report', '/ticketsystem/reports' ) ) !== null )
    return $denied;
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$cats = $settings->categories();

/** The view parameters of a report (pure). */
$url = function ( array $r, $queue, $period, $csv = false ) {
    $out = '/ticketsystem/reports/(from)/' . $r['from_text'] . '/(to)/' . $r['to_text'];
    if ( $queue !== '' )
        $out .= '/(queue)/' . rawurlencode( $queue );
    if ( $period !== '' )
        $out .= '/(period)/' . $period;
    return $out . ( $csv ? '/(format)/csv' : '' );
};
$defaultDays = (int)$settings->value( 'ReportSettings', 'DefaultDays', 30 );
$maxDays = (int)$settings->value( 'ReportSettings', 'MaxDays', ticketsystemReport::MAX_DAYS );

if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' )
{
    if ( !ticketsystemFormToken::valid( $http->postVariable( ticketsystemFormToken::FIELD, '' ) ) )
    {
        ticketsystemView::flash( array(), array( 'The form had expired. Please choose the period again.' ) );
        return $Module->redirectTo( '/ticketsystem/reports' );
    }
    $r = ticketsystemReport::range( array( 'from' => (string)$http->postVariable( 'ReportFrom', '' ), 'to' => (string)$http->postVariable( 'ReportTo', '' ) ),
                                    $defaultDays, time(), $maxDays );
    $queue = (string)$http->postVariable( 'ReportQueue', '' );
    $period = (string)$http->postVariable( 'ReportPeriod', '' );
    return $Module->redirectTo( $url( $r, isset( $cats[$queue] ) ? $queue : '', in_array( $period, array( 'day', 'week', 'month' ), true ) ? $period : '' ) );
}

$params = isset( $Params['UserParameters'] ) ? (array)$Params['UserParameters'] : array();
// (days)/<n> of 1.1: the last n days
if ( isset( $params['days'] ) && ctype_digit( (string)$params['days'] ) && !isset( $params['from'] ) )
{
    $defaultDays = max( 1, (int)$params['days'] );
    unset( $params['to'] );
}
$range = ticketsystemReport::range( $params, $defaultDays, time(), $maxDays );
$queue = isset( $params['queue'] ) && is_string( $params['queue'] ) && isset( $cats[$params['queue']] ) ? $params['queue'] : '';
$askedPeriod = isset( $params['period'] ) && in_array( $params['period'], array( 'day', 'week', 'month' ), true ) ? $params['period'] : '';
$report = new ticketsystemReport( $access, $settings );

if ( isset( $params['format'] ) && $params['format'] === 'csv' )
{
    $csv = $report->csv( $range['from'], $range['to'], $queue );
    while ( ob_get_level() > 0 )
        ob_end_clean();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="tickets-' . $range['from_text'] . '-to-' . $range['to_text'] . ( $queue !== '' ? '-' . $queue : '' ) . '.csv"' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Cache-Control: private, no-store' );
    echo $csv;
    eZExecution::cleanExit();
}

$r = $report->report( $range['from'], $range['to'], $queue, $askedPeriod );
$f = $r['figures'];
$set = $settings->statusSet();
$bars = function ( array $counts, array $labels ) {
    $out = array();
    $max = max( array_merge( array( 1 ), array_values( $counts ) ) );
    foreach ( $counts as $k => $n )
        $out[] = array( 'id' => (string)$k, 'label' => isset( $labels[$k] ) ? $labels[$k] : ( (string)$k === '' ? '-' : (string)$k ), 'count' => $n,
                        // in steps of 4 %, so a stylesheet class (a4-pct-<pct>, ts-pct-<pct>) can draw it without inline CSS
                        'pct' => (int)( round( $n * 25 / $max ) * 4 ) );
    return $out;
};
$service = new ticketsystemService( $access, $settings );
$staff = $service->staffMembers();
$staffLabel = $staff;
$staffLabel[0] = ezpI18n::tr( 'extension/ticketsystem', 'Nobody' );
$workload = array();
foreach ( $r['workload'] as $id => $w )
{
    if ( !isset( $staffLabel[$id] ) )
    {
        $o = eZContentObject::fetch( (int)$id );
        $staffLabel[$id] = $o ? (string)$o->attribute( 'name' ) : '#' . (int)$id;
    }
    $workload[] = array( 'id' => (int)$id, 'name' => $staffLabel[$id], 'open' => $w['open'], 'replies' => $w['replies'], 'tickets' => $w['tickets'] );
}
$volume = array();
$vmax = 1;
foreach ( $r['volume'] as $v )
    $vmax = max( $vmax, $v['opened'], $v['solved'] );
foreach ( $r['volume'] as $v )
    $volume[] = $v + array( 'opened_pct' => (int)( round( $v['opened'] * 25 / $vmax ) * 4 ), 'solved_pct' => (int)( round( $v['solved'] * 25 / $vmax ) * 4 ) );
$ageLabels = array( 'under_1d' => 'Less than a day', '1_3d' => '1 to 3 days', '3_7d' => '3 to 7 days', '7_30d' => '7 to 30 days', 'over_30d' => 'More than 30 days' );
$pct = function ( $a, $b ) { return $b > 0 ? (int)round( $a * 100 / $b ) : null; };

$Result = ticketsystemView::render( $user, 'reports', 'reports', array(
    'range' => $range, 'queue' => $queue, 'period' => $r['period'], 'asked_period' => $askedPeriod, 'categories' => $cats,
    'days' => $range['days'], 'choices' => array( 7, 30, 90, 365 ),
    'figures' => $f, 'volume' => $volume,
    'by_status' => $bars( $f['by_status'], $set->statuses ), 'by_category' => $bars( $f['by_category'], $cats ),
    'by_priority' => $bars( $f['by_priority'], $settings->priorities() ), 'by_assignee' => $bars( $f['by_assignee'], $staffLabel ),
    'backlog' => $r['backlog'], 'backlog_ages' => $bars( $r['backlog']['ages'], $ageLabels ),
    'backlog_by_category' => $bars( $r['backlog']['by_category'], $cats ), 'backlog_by_priority' => $bars( $r['backlog']['by_priority'], $settings->priorities() ),
    'oldest' => $r['backlog']['oldest_seconds'] ? ticketsystemDesk::age( 0, $r['backlog']['oldest_seconds'] ) : '',
    'workload' => $workload,
    'response_pct' => $pct( $f['sla']['response_met'], $f['sla']['response_total'] ),
    'resolution_pct' => $pct( $f['sla']['resolution_met'], $f['sla']['resolution_total'] ),
    'csat_dist' => $bars( $f['csat']['dist'], array() ), 'csat_source' => $r['csat_source'],
    'sla_enabled' => $r['sla_enabled'], 'csat_enabled' => $r['csat_enabled'], 'truncated' => $r['truncated'],
    'csv_url' => $url( $range, $queue, $askedPeriod, true ),
    'is_staff' => $access->isStaff(),
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Reports' ) ) );
