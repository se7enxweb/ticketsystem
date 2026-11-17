<?php
/**
 * @copyright Copyright (C) 2026 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package ticketsystem
 */

/**
 * What the views of the module share: the one-time messages after a POST (post/redirect/get), the rows the templates
 * show and the rendering inside the chosen frame (ticketsystemChrome).
 */
class ticketsystemView
{
    const FLASH = 'ticketsystem_flash';

    public static function flash( array $messages, array $errors = array() )
    {
        eZSession::set( self::FLASH, array( 'messages' => $messages, 'errors' => $errors ) );
    }

    /** @return array( messages, errors ) and forgets them */
    public static function takeFlash()
    {
        $f = eZSession::get( self::FLASH );
        eZSession::unsetkey( self::FLASH );
        return is_array( $f ) ? array( (array)$f['messages'], (array)$f['errors'] ) : array( array(), array() );
    }

    /** A list row of a ticket for the templates. */
    public static function row( ticketsystemTicket $t, ?ticketsystemSettings $settings = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        $set = $settings->statusSet();
        $cats = $settings->categories();
        $prios = $settings->priorities();
        $s = (string)$t->attribute( 'status' );
        return array(
            'id' => (int)$t->attribute( 'id' ), 'number' => (string)$t->attribute( 'number' ),
            'subject' => (string)$t->attribute( 'subject' ),
            'status' => $s, 'status_label' => $set->label( $s ), 'status_tone' => $set->tone( $s ),
            'done' => $set->isDone( $s ), 'closed' => $set->isClosed( $s ),
            'category' => (string)$t->attribute( 'category' ),
            'category_label' => isset( $cats[$t->attribute( 'category' )] ) ? $cats[$t->attribute( 'category' )] : '',
            'priority' => (string)$t->attribute( 'priority' ),
            'priority_label' => isset( $prios[$t->attribute( 'priority' )] ) ? $prios[$t->attribute( 'priority' )] : '',
            'post_count' => (int)$t->attribute( 'post_count' ), 'created' => (int)$t->attribute( 'created' ),
            'modified' => (int)$t->attribute( 'modified' ), 'last_post' => (int)$t->attribute( 'last_post' ),
            'last_post_staff' => (bool)$t->attribute( 'last_post_staff' ),
            'account_label' => ticketsystemAccount::label( $t->attribute( 'account_key' ) ),
            'opener_id' => (int)$t->attribute( 'opener_id' ),
            'merged_into' => (int)$t->attribute( 'merged_into' ), 'source' => (string)$t->attribute( 'source' ),
        );
    }

    /**
     * Renders design:ticketsystem/<template>.tpl in the user's frame.
     *
     * @return array the module result
     */
    public static function render( eZUser $user, $view, $template, array $vars, array $path )
    {
        $chrome = ticketsystemChrome::forUser( $user, $view );
        $tpl = eZTemplate::factory();
        foreach ( $chrome['variables'] as $k => $v )
            $tpl->setVariable( $k, $v );
        list( $messages, $errors ) = self::takeFlash();
        $vars += array( 'ts_messages' => array(), 'ts_errors' => array() );
        $vars['ts_messages'] = array_merge( $messages, $vars['ts_messages'] );
        $vars['ts_errors'] = array_merge( $errors, $vars['ts_errors'] );
        $vars['ts_chrome'] = $chrome['name'];
        $vars['ts_view'] = $view;
        $access = new ticketsystemAccess( $user );
        $vars['ts_admin'] = array( 'may_create' => $access->may( 'create' ), 'is_staff' => $access->isStaff(), 'may_report' => $access->may( 'report' ),
                                   'import' => ticketsystemSettings::instance()->enabled( 'ImportSettings', 'Enabled', 'disabled' ) );
        $vars['ts_token'] = ticketsystemFormToken::token();
        $vars['ts_token_field'] = ticketsystemFormToken::FIELD;
        foreach ( $vars as $k => $v )
            $tpl->setVariable( $k, $v );
        return array( 'content' => $tpl->fetch( 'design:ticketsystem/' . $template . '.tpl' ), 'path' => $path,
                      'navigation_part' => 'ezticketsystemnavigationpart' );
    }

    /**
     * The policy check of a view: a visitor who is not signed in is sent to the sign-in page (and back here), a
     * signed-in user without the policy gets the kernel's access denied. Null when the view may go on, else the
     * module result to return.
     */
    public static function guard( eZModule $module, $function, $back )
    {
        $user = eZUser::currentUser();
        if ( !$user->isRegistered() )
            return $module->redirectTo( '/user/login?' . http_build_query( array( 'RedirectURI' => $back ) ) );
        $r = $user->hasAccessTo( 'ticketsystem', $function );
        if ( !isset( $r['accessWord'] ) || $r['accessWord'] === 'no' )
            return $module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
        return null;
    }

    /** The path: "Support > <page>" on the site, "TicketSystem > <page>" in the administration. */
    public static function path( $last = null, $middle = null )
    {
        $root = ticketsystemChrome::isAdmin() ? 'TicketSystem' : ezpI18n::tr( 'extension/ticketsystem', 'Support' );
        $path = array( array( 'text' => $root, 'url' => $last === null ? false : 'ticketsystem/list' ) );
        if ( $middle !== null )
            $path[] = $middle;
        if ( $last !== null )
            $path[] = array( 'text' => $last, 'url' => false );
        return $path;
    }

    /**
     * A row of the staff desk: the list row plus the assignee's name, the waiting time, the SLA state and the badge
     * classes of the administration.
     */
    public static function deskRow( ticketsystemTicket $t, array &$names, ?ticketsystemSettings $settings = null )
    {
        $settings = $settings ?: ticketsystemSettings::instance();
        $r = self::row( $t, $settings );
        $aid = (int)$t->attribute( 'assignee_id' );
        if ( $aid && !isset( $names[$aid] ) )
        {
            $o = eZContentObject::fetch( $aid );
            $names[$aid] = $o ? (string)$o->attribute( 'name' ) : '#' . $aid;
        }
        $sla = new ticketsystemSla( $settings );
        $state = $sla->enabled() && ( (int)$t->attribute( 'response_due' ) || (int)$t->attribute( 'resolve_due' ) ) && !$r['done']
               ? $sla->state( $t->values(), time() ) : null;
        $prios = array_keys( $settings->priorities() );
        $r += array(
            'badge' => ticketsystemDesk::badgeClass( $r['status_tone'] ),
            'assignee_id' => $aid, 'assignee' => $aid ? $names[$aid] : '',
            'waiting' => ticketsystemDesk::age( (int)$t->attribute( 'last_post' ), time() ),
            'priority_high' => $prios && array_search( $r['priority'], $prios, true ) >= max( 0, count( $prios ) - 2 ) && count( $prios ) > 2,
            'escalation' => (int)$t->attribute( 'escalation' ),
            'sla_state' => $state ? $state['worst'] : '', 'sla_tone' => $state ? $state['tone'] : '',
            'sla_due' => $state ? ( $state['response']['state'] !== 'none' && $state['response']['state'] !== 'met' ? $state['response'] : $state['resolution'] ) : null,
            'rating' => (int)$t->attribute( 'rating' ), 'note_count' => (int)$t->attribute( 'note_count' ),
            'attachment_count' => (int)$t->attribute( 'attachment_count' ), 'source' => (string)$t->attribute( 'source' ),
        );
        if ( $r['sla_due'] )
            $r['sla_due']['text'] = ticketsystemDesk::left( $r['sla_due']['left'] );
        return $r;
    }
}
