{* The opening of every help desk page in the administration (design admin4, doc/design.md): the page head with the
   section name, the title, an optional subtitle and the page actions, then the one-time messages. Built only from the
   administration's shared components (components.css, .a4-*), so light and dark follow the --a4-* tokens.
   Input: $title, $sub (optional), $ts_view, $ts_messages, $ts_errors, $ts_admin (staff links: may_create, is_staff,
   may_report). Closed by ticketsystem/parts/admin_close.tpl. *}
<div class="context-block a4-page">

<div class="a4-pagehead">
    <div class="a4-pagehead-main">
        <p class="a4-pagehead-pre">{'Help desk'|i18n( 'extension/ticketsystem' )}</p>
        <h1 class="context-title a4-pagehead-title">{$title|wash}</h1>
        {if and( is_set( $sub ), $sub )}<p class="a4-pagehead-sub">{$sub|wash}</p>{/if}
    </div>
    <div class="a4-pagehead-actions">
        {if and( is_set( $ts_admin.may_create ), $ts_admin.may_create, $ts_view|ne( 'new' ) )}<a class="a4-btn a4-btn-small a4-btn-primary" id="ts-a4-new" href={'ticketsystem/new'|ezurl}>{'New ticket'|i18n( 'extension/ticketsystem' )}</a>{/if}
    </div>
</div>

{foreach $ts_messages as $tsM}<p class="a4-notice is-ok" role="status">{$tsM|i18n( 'extension/ticketsystem' )|wash}</p>{/foreach}
{foreach $ts_errors as $tsE}<p class="a4-notice is-bad" role="alert">{$tsE|i18n( 'extension/ticketsystem' )|wash}</p>{/foreach}
