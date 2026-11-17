{* ticketsystem/search (doc/search.md): the search form and its results; the administration has its own (design admin4).
   Input: $q, $filter (q, number, status, priority, category, assignee, customer, account, from, to), $searched, $tickets
   (ticketsystemView::deskRow + in_note), $total, $offset, $limit, $is_staff, $searches_notes, $status_choices (id,
   label), $priorities, $categories, $staff (id => name), $pager_prev, $pager_next, the form token. The form posts and
   is redirected to view parameters. No inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead"><div><h1 class="ktl-h1 ts-h1">{'Search tickets'|i18n( $tsI )}</h1>
    <p class="ktl-lede ts-lede">{if $searches_notes}{'Ticket numbers, subjects, the text of every post and the internal notes.'|i18n( $tsI )}{else}{'Ticket numbers, subjects and the text of every post.'|i18n( $tsI )}{/if}</p></div></div>

<section class="ktl-card ts-card" aria-label="{'Search'|i18n( $tsI )|wash}">
<form class="ktl-form ts-form" method="post" action={'ticketsystem/search'|ezurl} id="ts-search-form">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
    <div class="ktl-field ts-field"><label for="ts-search-q">{'Words'|i18n( $tsI )}</label>
        <input class="ktl-input ts-input" type="search" id="ts-search-q" name="SearchText" value="{$filter.q|wash}" maxlength="100" /></div>
    <div class="ktl-grid is-3 ts-grid">
        <div class="ktl-field ts-field"><label for="ts-search-number">{'Ticket number'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="text" id="ts-search-number" name="SearchNumber" value="{$filter.number|wash}" maxlength="32" /></div>
        <div class="ktl-field ts-field"><label for="ts-search-status">{'Status'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-search-status" name="SearchStatus"><option value="all">{'Any status'|i18n( $tsI )}</option>
            {foreach $status_choices as $tsS}{if $tsS.id|ne( 'all' )}<option value="{$tsS.id|wash}"{if $filter.status|eq( $tsS.id )} selected="selected"{/if}>{$tsS.label|i18n( $tsI )|wash}</option>{/if}{/foreach}</select></div>
        <div class="ktl-field ts-field"><label for="ts-search-priority">{'Priority'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-search-priority" name="SearchPriority"><option value="">{'Any priority'|i18n( $tsI )}</option>
            {foreach $priorities as $tsK => $tsL}<option value="{$tsK|wash}"{if $filter.priority|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
        <div class="ktl-field ts-field"><label for="ts-search-queue">{'Queue'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-search-queue" name="SearchQueue"><option value="">{'Every queue'|i18n( $tsI )}</option>
            {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $filter.category|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
        {if $is_staff}
        <div class="ktl-field ts-field"><label for="ts-search-assignee">{'Assigned to'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-search-assignee" name="SearchAssignee"><option value="">{'Anybody'|i18n( $tsI )}</option>
            <option value="me"{if $filter.assignee|eq( 'me' )} selected="selected"{/if}>{'Me'|i18n( $tsI )}</option>
            <option value="none"{if $filter.assignee|eq( 'none' )} selected="selected"{/if}>{'Nobody'|i18n( $tsI )}</option>
            {foreach $staff as $tsK => $tsL}<option value="{$tsK}"{if $filter.assignee|eq( $tsK )} selected="selected"{/if}>{$tsL|wash}</option>{/foreach}</select></div>
        <div class="ktl-field ts-field"><label for="ts-search-customer">{'Customer (name or e-mail)'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="text" id="ts-search-customer" name="SearchCustomer" value="{$filter.customer|wash}" maxlength="100" /></div>
        {/if}
        <div class="ktl-field ts-field"><label for="ts-search-from">{'Opened from'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="date" id="ts-search-from" name="SearchFrom" value="{$filter.from|wash}" /></div>
        <div class="ktl-field ts-field"><label for="ts-search-to">{'Opened until'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="date" id="ts-search-to" name="SearchTo" value="{$filter.to|wash}" /></div>
    </div>
    <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="SearchRun" value="1" id="ts-search-run">{'Search'|i18n( $tsI )}</button>
        {if $searched}<button type="submit" class="ktl-btn is-ghost ts-btn is-ghost" name="SearchReset" value="1">{'Clear'|i18n( $tsI )}</button>{/if}</p>
</form>
</section>

{if $searched}
<section class="ktl-card ts-card" aria-labelledby="ts-results-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-results-h">{'Results'|i18n( $tsI )}</h2><span class="ktl-muted ts-muted" id="ts-search-total">{'%count tickets'|i18n( $tsI,, hash( '%count', $total ) )|wash}</span></div>
{if $tickets|count}
    <div class="ktl-table-wrap ts-table-wrap"><table class="ktl-table ts-table" id="ts-search-results">
        <thead><tr><th scope="col">{'Ticket'|i18n( $tsI )}</th><th scope="col">{'Status'|i18n( $tsI )}</th><th scope="col" class="ktl-col-wide">{'Queue'|i18n( $tsI )}</th><th scope="col">{'Last update'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $tickets as $t}<tr data-ts-number="{$t.number|wash}">
            <td data-label="{'Ticket'|i18n( $tsI )|wash}"><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}><strong>{$t.subject|wash}</strong></a><br><span class="ktl-muted ts-muted">{$t.number|wash}{if $is_staff} &middot; {$t.account_label|wash}{/if}</span>{if $t.in_note} <span class="ktl-badge ts-badge" data-ts-in-note="1">{'in an internal note'|i18n( $tsI )}</span>{/if}</td>
            <td data-label="{'Status'|i18n( $tsI )|wash}">{include uri='design:ticketsystem/parts/status.tpl' label=$t.status_label tone=$t.status_tone}</td>
            <td data-label="{'Queue'|i18n( $tsI )|wash}" class="ktl-col-wide">{$t.category_label|i18n( $tsI )|wash}</td>
            <td data-label="{'Last update'|i18n( $tsI )|wash}" class="ktl-nowrap">{$t.last_post|l10n( 'shortdatetime' )}</td></tr>{/foreach}</tbody>
    </table></div>
    {if or( $pager_prev, $pager_next )}
    <nav class="ktl-pager ts-pager" aria-label="{'Pages'|i18n( $tsI )|wash}">
        {if $pager_prev}<a class="ktl-btn is-ghost ts-btn is-ghost" href={$pager_prev|ezurl}>{'Previous'|i18n( $tsI )}</a>{/if}
        {if $pager_next}<a class="ktl-btn is-ghost ts-btn is-ghost" href={$pager_next|ezurl}>{'Next'|i18n( $tsI )}</a>{/if}
    </nav>
    {/if}
{else}<p class="ktl-empty-line ts-empty" id="ts-search-empty">{'No ticket matches.'|i18n( $tsI )}</p>{/if}
</section>
{/if}
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
