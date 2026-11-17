<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * The frame around the customer views ([ChromeSettings] Chrome, doc/design.md):
 *
 * - katalyst: the Katalyst customer console (its rail, top bar and command palette from
 *   design:katalyst/parts/chrome_open.tpl), for a user linked to a Katalyst customer; the views then use only the
 *   console's components (katalyst.css, ktl-*);
 * - standard: the extension's own frame (ticketsystem.css, ts-*), light and dark.
 *
 * The console's variables are built here with the console's own public helpers, so the rail, the account switcher
 * and the reseller's branding look exactly as on katalyst/dashboard.
 */
class ticketsystemChrome
{
    /**
     * @return array( 'name' => katalyst|standard, 'variables' => template variables )
     */
    public static function forUser( eZUser $user, $view )
    {
        $mode = (string)ticketsystemSettings::instance()->value( 'ChromeSettings', 'Chrome', 'auto' );
        if ( $mode !== 'standard' && ticketsystemAccount::katalystAvailable()
             && class_exists( '\Exponential\View\Extension\SevenxKatalyst\Katalyst\CustomerView' ) )
        {
            $vars = self::katalyst( $user, $view );
            if ( $vars !== null )
                return array( 'name' => 'katalyst', 'variables' => $vars );
        }
        return array( 'name' => 'standard', 'variables' => array() );
    }

    /** The Katalyst console's variables, or null when the user has no customer (or something is missing). */
    protected static function katalyst( eZUser $user, $view )
    {
        try
        {
            $cv = '\Exponential\View\Extension\SevenxKatalyst\Katalyst\CustomerView';
            $uid = (int)$user->attribute( 'contentobject_id' );
            $customer = expKatalystAccessService::customerForUser( $uid, $cv::chosenAccount() );
            if ( !$customer )
                return null;
            $links = $cv::links();
            $branding = expKatalystResellerService::brandingForCustomer( $customer->toArray(), $links );
            $links = $cv::brandedLinks( $links, $branding );
            $scope = expKatalystAccessService::customerScope( $customer );
            $service = new expKatalystCustomerService( null, null, $scope );
            $accounts = array();
            foreach ( expKatalystAccessService::customerRowsForUser( $uid ) as $r )
                $accounts[] = array( 'id' => (int)$r['id'], 'label' => expKatalystAdminService::label( $r ),
                                     'current' => (int)$r['id'] === (int)$customer->attribute( 'id' ) );
            $row = $customer->toArray();
            return array(
                'customer' => array( 'id' => (int)$row['id'], 'label' => expKatalystAdminService::label( $row ), 'status' => (string)$row['status'] ),
                'view' => $view,
                'links' => $links,
                'client_area_url' => isset( $links['client_area'] ) ? $links['client_area'] : '',
                'support_url' => isset( $links['support'] ) ? $links['support'] : '',
                'branding' => $branding,
                // the account switcher posts to katalyst/<view>; the help desk has no such view, so it shows the
                // accounts without switching here (the dashboard switches)
                'accounts' => count( $accounts ) > 1 ? array() : $accounts,
                'brand_choices' => array(),
                'brand_preview' => 0,
                'nav_sites' => $service->siteList( $customer ),
                'current_site' => null,
                'current_site_id' => 0,
                'tab' => '',
                'messages' => array(),
                'errors' => array(),
                'form_token' => class_exists( 'expKatalystCsrf' ) ? expKatalystCsrf::token() : '',
                'token_field' => class_exists( 'expKatalystCsrf' ) ? expKatalystCsrf::FIELD : '',
            );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeWarning( 'The Katalyst console frame is not available: ' . $e->getMessage(), __METHOD__ );
            return null;
        }
    }
}
