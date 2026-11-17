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
        $vars['ts_token'] = ticketsystemFormToken::token();
        $vars['ts_token_field'] = ticketsystemFormToken::FIELD;
        foreach ( $vars as $k => $v )
            $tpl->setVariable( $k, $v );
        return array( 'content' => $tpl->fetch( 'design:ticketsystem/' . $template . '.tpl' ), 'path' => $path );
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

    public static function path( $last = null )
    {
        $path = array( array( 'text' => ezpI18n::tr( 'extension/ticketsystem', 'Support' ), 'url' => $last === null ? false : 'ticketsystem/list' ) );
        if ( $last !== null )
            $path[] = array( 'text' => $last, 'url' => false );
        return $path;
    }
}
