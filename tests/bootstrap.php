<?php
/**
 * Bootstrap of the DB-less unit tests: the pure classes only, no kernel.
 *   vendor/bin/phpunit -c extension/ticketsystem/phpunit.xml.dist
 */
foreach ( array( 'ticketsystemsettings', 'ticketsystemstatusset', 'ticketsystemnumber', 'ticketsystemtext', 'ticketsystemaccess', 'ticketsystemmailer', 'ticketsystemschema', 'ticketsystemsla', 'ticketsystemdesk',
                 'ticketsystemmailparser', 'ticketsystemreplytoken', 'ticketsystemmailin', 'ticketsystemreport', 'ticketsystemfiles', 'ticketsystemservice', 'ticketsystemwhmcsimport',
                 'ticketsystembusinessclock', 'ticketsystemcsat' ) as $f )
    require_once __DIR__ . '/../classes/' . $f . '.php';
