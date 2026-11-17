{* ticketsystem/search on the site (doc/reports.md); the administration has its own (design admin4). *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead"><div><h1 class="ktl-h1 ts-h1">{'Search'|i18n( $tsI )}</h1>
    <p class="ktl-lede ts-lede">{'Ticket numbers, subjects and the text of every post.'|i18n( $tsI )}</p></div></div>
<section class="ktl-card ts-card">
<form class="ktl-form ts-form" method="post" action={'ticketsystem/search'|ezurl} id="ts-search-form">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
    <div class="ktl-field ts-field"><label for="ts-search-q">{'Search for'|i18n( $tsI )}</label>
        <input class="ktl-input ts-input" type="search" id="ts-search-q" name="SearchText" value="{$q|wash}" maxlength="100" required /></div>
    <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="TicketSearch" value="1">{'Search'|i18n( $tsI )}</button></p>
</form>
</section>
{if $q}
<section class="ktl-card ts-card">
{if $tickets|count}
    <div class="ktl-table-wrap ts-table-wrap"><table class="ktl-table ts-table" id="ts-search-results">
        <thead><tr><th scope="col">{'Ticket'|i18n( $tsI )}</th><th scope="col">{'Status'|i18n( $tsI )}</th><th scope="col">{'Last update'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $tickets as $t}<tr data-ts-number="{$t.number|wash}">
            <td data-label="{'Ticket'|i18n( $tsI )|wash}"><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}><strong>{$t.subject|wash}</strong></a><br><span class="ktl-muted ts-muted">{$t.number|wash}</span>{if $t.in_note} <span class="ktl-badge ts-badge">{'in an internal note'|i18n( $tsI )}</span>{/if}</td>
            <td data-label="{'Status'|i18n( $tsI )|wash}">{include uri='design:ticketsystem/parts/status.tpl' label=$t.status_label tone=$t.status_tone}</td>
            <td data-label="{'Last update'|i18n( $tsI )|wash}">{$t.last_post|l10n( 'shortdatetime' )}</td></tr>{/foreach}</tbody>
    </table></div>
{else}<p class="ktl-empty-line ts-empty" id="ts-search-empty">{'No ticket matches.'|i18n( $tsI )}</p>{/if}
</section>
{/if}
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
