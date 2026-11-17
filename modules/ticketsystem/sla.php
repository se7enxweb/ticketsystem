<?php
/**
 * ticketsystem/sla: the SLA board of staff (doc/sla.md), policy ticketsystem/manage: the active tickets with a target,
 * the most urgent first, the counts on track / due soon / breached / paused, and the latest escalation steps.
 * (state)/<ok|warn|breached|paused> shows one state.
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
if ( ( $denied = ticketsystemView::guard( $Module, 'manage', '/ticketsystem/sla' ) ) !== null )
    return $denied;
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
$state = isset( $Params['UserParameters']['state'] ) ? (string)$Params['UserParameters']['state'] : '';
if ( !in_array( $state, array( 'ok', 'warn', 'breached', 'paused' ), true ) )
    $state = '';
$board = ticketsystemEscalation::board( $access, $settings, $state, 200 );
$service = new ticketsystemService( $access, $settings );
$names = $service->staffMembers();
foreach ( $board['tickets'] as &$row )
    $row['assignee'] = $row['assignee_id'] ? ( isset( $names[$row['assignee_id']] ) ? $names[$row['assignee_id']] : '#' . $row['assignee_id'] ) : '';
unset( $row );
$numbers = array();
$log = ticketsystemEscalation::recent( 30 );
foreach ( $log as &$l )
{
    if ( !isset( $numbers[$l['ticket_id']] ) )
    {
        $t = ticketsystemTicket::fetch( $l['ticket_id'] );
        $numbers[$l['ticket_id']] = $t && $access->canRead( $t ) ? (string)$t->attribute( 'number' ) : '';
    }
    $l['number'] = $numbers[$l['ticket_id']];
}
unset( $l );
$sla = new ticketsystemSla( $settings );
$Result = ticketsystemView::render( $user, 'sla', 'sla', array(
    'board' => $board, 'state' => $state,
    'log' => array_values( array_filter( $log, function ( $l ) { return $l['number'] !== ''; } ) ),
    'warn_percent' => $sla->warnPercent(), 'steps' => $sla->escalationSteps(),
    'clock' => array( 'business' => $sla->businessDefault(), 'hours' => (string)$settings->value( 'SLASettings', 'BusinessHours', '09:00-17:00' ),
                      'timezone' => $sla->clock( true )->timezone() ),
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'SLA and escalation' ) ) );
