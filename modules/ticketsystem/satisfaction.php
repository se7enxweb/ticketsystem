<?php
/**
 * ticketsystem/satisfaction: the satisfaction ratings (doc/csat.md), policy ticketsystem/report, within the read
 * scope of the reports: the CSAT share (4 and 5 of 5), the average, the distribution, per queue and per assignee, how
 * many were asked and answered, and the latest ratings with their comments. (days)/<7|30|90|365>.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'report', '/ticketsystem/satisfaction' ) ) !== null )
    return $denied;
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$choices = array( 7, 30, 90, 365 );
$days = isset( $Params['UserParameters']['days'] ) && in_array( (int)$Params['UserParameters']['days'], $choices, true )
      ? (int)$Params['UserParameters']['days'] : 30;
$to = time() + 60;
$from = $to - $days * 86400;
$csat = new ticketsystemCsat( $settings );
$summary = $access->scope( 'read' ) >= ticketsystemAccess::ALL ? $csat->summary( array( 'from' => $from, 'to' => $to ) ) : null;
$latest = $summary ? $csat->latest( array( 'from' => $from, 'to' => $to ), 0, 50 ) : array();
$names = ( new ticketsystemService( $access, $settings ) )->staffMembers();
foreach ( $latest as &$r )
    $r['assignee'] = $r['assignee_id'] ? ( isset( $names[$r['assignee_id']] ) ? $names[$r['assignee_id']] : '#' . $r['assignee_id'] ) : '';
unset( $r );
$dist = array();
if ( $summary )
    foreach ( ticketsystemCsat::labels() as $score => $label )
        $dist[] = array( 'score' => $score, 'label' => $label, 'count' => $summary['dist'][$score],
                         'pct' => $summary['count'] ? (int)round( $summary['dist'][$score] * 100 / $summary['count'] ) : 0 );
$Result = ticketsystemView::render( $user, 'satisfaction', 'satisfaction', array(
    'enabled' => $csat->enabled(), 'days' => $days, 'choices' => $choices, 'summary' => $summary, 'dist' => $dist, 'latest' => $latest,
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Satisfaction' ) ) );
