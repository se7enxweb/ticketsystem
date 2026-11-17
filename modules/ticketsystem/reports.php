<?php
/**
 * ticketsystem/reports: the help desk's figures for a period (doc/reports.md), policy ticketsystem/report, within the
 * read scope: tickets opened, solved and open, unassigned, escalated and breached; by status, queue, priority and
 * assignee; first response and resolution times; SLA targets met; satisfaction ratings. (days)/<7|30|90|365>;
 * (format)/csv downloads the tickets of the period (index data only: never a note, never the text of a post).
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'report', '/ticketsystem/reports' ) ) !== null )
    return $denied;
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$params = isset( $Params['UserParameters'] ) ? (array)$Params['UserParameters'] : array();
$choices = array( 7, 30, 90, 365 );
$days = isset( $params['days'] ) && in_array( (int)$params['days'], $choices, true ) ? (int)$params['days'] : (int)$settings->value( 'ReportSettings', 'DefaultDays', 30 );
if ( !in_array( $days, $choices, true ) )
    $days = 30;
$to = time() + 60;
$from = $to - $days * 86400;
$report = new ticketsystemReport( $access, $settings );
if ( isset( $params['format'] ) && $params['format'] === 'csv' )
{
    $csv = $report->csv( $from, $to );
    while ( ob_get_level() > 0 )
        ob_end_clean();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="tickets-' . date( 'Y-m-d' ) . '-' . $days . 'd.csv"' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Cache-Control: private, no-store' );
    echo $csv;
    eZExecution::cleanExit();
}
$f = $report->period( $from, $to );
$set = $settings->statusSet();
$label = function ( array $counts, array $labels ) {
    $out = array();
    $max = max( array_merge( array( 1 ), array_values( $counts ) ) );
    foreach ( $counts as $k => $n )
        $out[] = array( 'id' => (string)$k, 'label' => isset( $labels[$k] ) ? $labels[$k] : ( (string)$k === '' ? '-' : (string)$k ), 'count' => $n,
                        'pct' => (int)( round( $n * 25 / $max ) * 4 ) );
    return $out;
};
$staff = ( new ticketsystemService( $access, $settings ) )->staffMembers();
$staff[0] = ezpI18n::tr( 'extension/ticketsystem', 'Nobody' );
$statusLabels = array();
foreach ( $set->statuses as $k => $v )
    $statusLabels[$k] = $v;
$pct = function ( $a, $b ) { return $b > 0 ? (int)round( $a * 100 / $b ) : null; };
$Result = ticketsystemView::render( $user, 'reports', 'reports', array(
    'days' => $days, 'choices' => $choices, 'figures' => $f,
    'by_status' => $label( $f['by_status'], $statusLabels ), 'by_category' => $label( $f['by_category'], $settings->categories() ),
    'by_priority' => $label( $f['by_priority'], $settings->priorities() ), 'by_assignee' => $label( $f['by_assignee'], $staff ),
    'response_pct' => $pct( $f['sla']['response_met'], $f['sla']['response_total'] ),
    'resolution_pct' => $pct( $f['sla']['resolution_met'], $f['sla']['resolution_total'] ),
    'csat_dist' => $label( $f['csat']['dist'], array() ),
    'is_staff' => $access->isStaff(),
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Reports' ) ) );
