#!/usr/bin/env php
<?php
/**
 * Installs or updates the help desk (doc/install.md): tables, classes, the Technical Support Group and the roles.
 * Idempotent; --dry-run reports only. Ends with PASS or FAIL.
 *
 *   php extension/ticketsystem/bin/php/install.php -s <admin siteaccess> --dry-run
 *   php extension/ticketsystem/bin/php/install.php -s <admin siteaccess> --customer-group="Members" \
 *       --front-siteaccess=<front> --admin-siteaccess=<admin>
 *
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => 'ticketsystem: install the tables, classes, groups and roles of the help desk',
                                     'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions( '[dry-run][customer-group:][staff-group:][front-siteaccess:][admin-siteaccess:][admin-user:][skip-roles]', '',
    array( 'dry-run' => 'Report only, change nothing',
           'customer-group' => 'User groups that get the role "Ticket Customer", comma separated (e.g. "Members")',
           'staff-group' => 'The support group (default "Technical Support Group")',
           'front-siteaccess' => 'The siteaccess customers and staff sign in to',
           'admin-siteaccess' => 'The administration siteaccess staff may sign in to',
           'admin-user' => 'Login of the user who owns what is created (default: admin)',
           'skip-roles' => 'Only tables and classes' ) );
$script->initialize();

$login = $options['admin-user'] ? $options['admin-user'] : 'admin';
$admin = eZUser::fetchByName( $login );
if ( $admin )
    eZUser::setCurrentlyLoggedInUser( $admin, (int)$admin->attribute( 'contentobject_id' ) );

$installer = new ticketsystemInstaller( (bool)$options['dry-run'] );
$installer->tables();
$installer->classes();
if ( !$options['skip-roles'] )
{
    $groups = $options['customer-group'] ? array_filter( array_map( 'trim', explode( ',', $options['customer-group'] ) ) ) : array();
    $installer->roles( $groups, (string)$options['front-siteaccess'], (string)$options['admin-siteaccess'],
                       $options['staff-group'] ? $options['staff-group'] : ticketsystemInstaller::STAFF_GROUP );
}
foreach ( $installer->lines as $line )
    $cli->output( $line );
$cli->output( $installer->ok ? ( $options['dry-run'] ? 'PASS (dry run)' : 'PASS' ) : 'FAIL' );
$script->shutdown( $installer->ok ? 0 : 1 );
