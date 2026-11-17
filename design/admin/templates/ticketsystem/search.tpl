{* ticketsystem/search in the administration (doc/search.md, doc/views.md): ticket numbers, subjects and the text of
   every post; staff also the internal notes (a hit found only in a note is marked). The form posts and is redirected
   to view parameters. Input: $q, $filter (q, number, status, priority, category, assignee, customer, account, from,
   to), $searched, $tickets (ticketsystemView::deskRow + in_note), $total, $offset, $limit, $is_staff, $searches_notes,
   $status_choices (id, label), $priorities, $categories, $staff (id => name), $pager_prev, $pager_next, the form
   token. Only the shared admin components (.a4-*). *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Search'|i18n( $tsI )
         sub=cond( $searches_notes, 'Ticket numbers, subjects, the text of every post and the internal notes.'|i18n( $tsI ),
                   'Ticket numbers, subjects and the text of every post.'|i18n( $tsI ) )}

<form class="a4-section" method="post" action={'ticketsystem/search'|ezurl} id="ts-search-form" role="search">
<input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
<div class="a4-filterbar">
    <div class="a4-field a4-field-wide"><label for="ts-search-q">{'Words'|i18n( $tsI )}</label>
        <input class="a4-input" type="search" id="ts-search-q" name="SearchText" value="{$filter.q|wash}" maxlength="100" /></div>
    <div class="a4-field"><label for="ts-search-number">{'Ticket number'|i18n( $tsI )}</label>
        <input class="a4-input" type="text" id="ts-search-number" name="SearchNumber" value="{$filter.number|wash}" maxlength="32" /></div>
    <div class="a4-field"><label for="ts-search-status">{'Status'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-search-status" name="SearchStatus"><option value="all">{'Any status'|i18n( $tsI )}</option>
        {foreach $status_choices as $tsS}{if $tsS.id|ne( 'all' )}<option value="{$tsS.id|wash}"{if $filter.status|eq( $tsS.id )} selected="selected"{/if}>{$tsS.label|i18n( $tsI )|wash}</option>{/if}{/foreach}</select></div>
    <div class="a4-field"><label for="ts-search-priority">{'Priority'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-search-priority" name="SearchPriority"><option value="">{'Any priority'|i18n( $tsI )}</option>
        {foreach $priorities as $tsK => $tsL}<option value="{$tsK|wash}"{if $filter.priority|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
    <div class="a4-field"><label for="ts-search-queue">{'Queue'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-search-queue" name="SearchQueue"><option value="">{'Every queue'|i18n( $tsI )}</option>
        {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $filter.category|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
    {if $is_staff}
    <div class="a4-field"><label for="ts-search-assignee">{'Assigned to'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-search-assignee" name="SearchAssignee"><option value="">{'Anybody'|i18n( $tsI )}</option>
        <option value="me"{if $filter.assignee|eq( 'me' )} selected="selected"{/if}>{'Me'|i18n( $tsI )}</option>
        <option value="none"{if $filter.assignee|eq( 'none' )} selected="selected"{/if}>{'Nobody'|i18n( $tsI )}</option>
        {foreach $staff as $tsK => $tsL}<option value="{$tsK}"{if $filter.assignee|eq( $tsK )} selected="selected"{/if}>{$tsL|wash}</option>{/foreach}</select></div>
    <div class="a4-field"><label for="ts-search-customer">{'Customer (name or e-mail)'|i18n( $tsI )}</label>
        <input class="a4-input" type="text" id="ts-search-customer" name="SearchCustomer" value="{$filter.customer|wash}" maxlength="100" /></div>
    {/if}
    <div class="a4-field"><label for="ts-search-from">{'Opened from'|i18n( $tsI )}</label>
        <input class="a4-input" type="date" id="ts-search-from" name="SearchFrom" value="{$filter.from|wash}" /></div>
    <div class="a4-field"><label for="ts-search-to">{'Opened until'|i18n( $tsI )}</label>
        <input class="a4-input" type="date" id="ts-search-to" name="SearchTo" value="{$filter.to|wash}" /></div>
    <div class="a4-field"><div class="a4-actions">
        <button type="submit" class="a4-btn a4-btn-primary" name="SearchRun" value="1" id="ts-search-run">{'Search'|i18n( $tsI )}</button>
        {if $searched}<button type="submit" class="a4-btn a4-btn-ghost" name="SearchReset" value="1">{'Clear'|i18n( $tsI )}</button>{/if}
    </div></div>
</div>
</form>

{if $searched}
<p class="a4-meta" id="ts-search-total">{'%count tickets'|i18n( $tsI,, hash( '%count', $total ) )|wash}</p>
{if $tickets|count}
<div class="a4-table-wrap"><table class="a4-table" id="ts-search-results">
    <thead><tr><th scope="col">{'Number'|i18n( $tsI )}</th><th scope="col">{'Subject'|i18n( $tsI )}</th>{if $is_staff}<th scope="col">{'Account'|i18n( $tsI )}</th>{/if}
        <th scope="col">{'Status'|i18n( $tsI )}</th><th scope="col">{'Queue'|i18n( $tsI )}</th>{if $is_staff}<th scope="col">{'Assigned to'|i18n( $tsI )}</th>{/if}<th scope="col">{'Last post'|i18n( $tsI )}</th></tr></thead>
    <tbody>
    {foreach $tickets as $t}
    <tr data-ts-number="{$t.number|wash}">
        <td class="a4-nowrap"><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.number|wash}</a></td>
        <td><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.subject|wash}</a>{if $t.in_note} <span class="a4-badge is-warn" data-ts-in-note="1">{'in an internal note'|i18n( $tsI )}</span>{/if}</td>
        {if $is_staff}<td>{$t.account_label|wash}</td>{/if}
        <td>{include uri='design:ticketsystem/parts/status.tpl' label=$t.status_label tone=$t.status_tone}</td>
        <td>{$t.category_label|i18n( $tsI )|wash}</td>
        {if $is_staff}<td>{if $t.assignee_id}{$t.assignee|wash}{else}<span class="a4-muted">{'nobody'|i18n( $tsI )}</span>{/if}</td>{/if}
        <td class="a4-nowrap">{$t.last_post|l10n( 'shortdatetime' )}</td>
    </tr>
    {/foreach}
    </tbody>
</table></div>
{if or( $pager_prev, $pager_next )}
<nav class="a4-pager a4-actions" aria-label="{'Pages'|i18n( $tsI )|wash}">
    <span class="a4-meta">{'%from to %to of %total'|i18n( $tsI,, hash( '%from', $offset|inc, '%to', min( $offset|sum( $limit ), $total ), '%total', $total ) )|wash}</span>
    {if $pager_prev}<a class="a4-btn a4-btn-small" href={$pager_prev|ezurl}>{'Previous'|i18n( $tsI )}</a>{/if}
    {if $pager_next}<a class="a4-btn a4-btn-small" href={$pager_next|ezurl}>{'Next'|i18n( $tsI )}</a>{/if}
</nav>
{/if}
{else}
<p class="a4-empty" id="ts-search-empty">{'No ticket matches.'|i18n( $tsI )}</p>
{/if}
{/if}

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
