<?php
/**
 * ticketsystem/settings/<section>: what the help desk runs with, for staff (doc/settings.md), policy
 * ticketsystem/manage. Read only: the settings live in ticketsystem.ini. Sections: queues (categories, round-robin,
 * statuses, priorities), sla (levels and targets, escalation), mail (reply by e-mail, attachments, ratings), import
 * (WHMCS: what was imported, the commands; only when [ImportSettings] Enabled).
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$section = isset( $Params['Section'] ) && in_array( $Params['Section'], array( 'queues', 'sla', 'mail', 'import' ), true ) ? (string)$Params['Section'] : 'queues';
if ( ( $denied = ticketsystemView::guard( $Module, 'manage', '/ticketsystem/settings/' . $section ) ) !== null )
    return $denied;
$user = eZUser::currentUser();
$access = new ticketsystemAccess( $user );
$settings = ticketsystemSettings::instance();
if ( $section === 'import' && !$settings->enabled( 'ImportSettings', 'Enabled', 'disabled' ) )
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
$set = $settings->statusSet();
$sla = new ticketsystemSla( $settings );
$auto = (array)$settings->value( 'AssignmentSettings', 'AutoAssign', array() );
$desk = new ticketsystemDesk( $access, $settings );
$queues = array();
foreach ( $settings->categories() as $id => $label )
{
    $w = $desk->where( array( 'category' => $id ) );
    $queues[] = array( 'id' => $id, 'label' => $label, 'auto' => isset( $auto[$id] ) && $auto[$id] === 'enabled',
                       'active' => $w === null ? 0 : ticketsystemTicket::countWhere( $w ),
                       'unassigned' => $w === null ? 0 : ticketsystemTicket::countWhere( $desk->where( array( 'category' => $id, 'assignee' => 'none' ) ) ) );
}
$levels = array();
foreach ( $sla->levels() as $id => $label )
{
    $targets = array();
    foreach ( $settings->priorities() as $p => $pl )
        $targets[] = array( 'priority' => $pl, 'first' => $sla->minutes( $id, $p, 'FirstResponse' ), 'resolution' => $sla->minutes( $id, $p, 'Resolution' ) );
    $levels[] = array( 'id' => $id, 'label' => $label, 'default' => $id === $sla->defaultLevel(), 'targets' => $targets );
}
$files = new ticketsystemFiles( $settings );
$mailer = new ticketsystemMailer( $settings );
$Result = ticketsystemView::render( $user, 'settings', 'settings', array(
    'section' => $section, 'queues' => $queues, 'statuses' => $set->rows(), 'status_set' => $set->name, 'priorities' => $settings->priorities(),
    'staff_group' => (string)$settings->value( 'AssignmentSettings', 'StaffGroup', '' ), 'staff_count' => count( ( new ticketsystemService( $access, $settings ) )->staffMembers() ),
    'claim_on_reply' => $settings->enabled( 'AssignmentSettings', 'ClaimOnReply' ),
    'sla' => array( 'enabled' => $sla->enabled(), 'warn' => $sla->warnPercent(), 'paused' => array_map( array( $set, 'label' ), $sla->pausedStatuses() ),
                    'levels' => $levels, 'katalyst' => $settings->enabled( 'SLASettings', 'KatalystEntitlements' ) && ticketsystemAccount::katalystAvailable(),
                    'raise' => in_array( 'raise', array_column( $sla->escalationSteps(), 'action' ), true ), 'steps' => $sla->escalationSteps(),
                    'business' => $sla->businessDefault(), 'hours' => (string)$settings->value( 'SLASettings', 'BusinessHours', '09:00-17:00' ),
                    'timezone' => $sla->clock( true )->timezone(),
                    'escalated' => ticketsystemTicket::countWhere( (string)$desk->where( array( 'sla' => 'escalated' ) ) ) ),
    'mail' => array( 'enabled' => $mailer->mailInEnabled(), 'reply_address' => (string)$settings->value( 'MailInSettings', 'ReplyAddress', '' ),
                     'require_token' => $settings->enabled( 'MailInSettings', 'RequireToken' ), 'open_new' => $settings->enabled( 'MailInSettings', 'OpenNewTickets' ),
                     'secret_set' => trim( (string)$settings->value( 'MailInSettings', 'TokenSecret', '' ) ) !== '', 'sender' => $mailer->sender() ),
    'attach' => $files->formInfo() + array( 'scan' => trim( (string)$settings->value( 'AttachmentSettings', 'ScanCommand', '' ) ) !== '',
                                            'outside' => strpos( $files->directory(), eZSys::rootDir() . '/' ) !== 0 ),
    'csat' => array( 'enabled' => $settings->enabled( 'CSATSettings', 'Enabled' ), 'ask' => $settings->enabled( 'CSATSettings', 'AskInMail' ) ),
    'import' => $section === 'import' ? ticketsystemWhmcsImport::imported() : array(),
    'is_staff' => true,
), ticketsystemView::path( ezpI18n::tr( 'extension/ticketsystem', 'Settings' ) ) );
