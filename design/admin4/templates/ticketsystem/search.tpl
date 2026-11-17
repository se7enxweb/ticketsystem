{* ticketsystem/search in the administration (doc/reports.md): number, subject and the text of every post; staff also
   the internal notes (marked). Input: $q, $tickets (deskRow + in_note), $total, $pager_prev, $pager_next. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Search'|i18n( $tsI ) sub='Ticket numbers, subjects and the text of every post.'|i18n( $tsI )}
<form method="post" action={'ticketsystem/search'|ezurl} id="ts-search-form">
<input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
<div class="a4-filterbar">
    <div class="a4-field a4-field-wide"><label for="ts-search-q">{'Search for'|i18n( $tsI )}</label>
        <input class="a4-input" type="search" id="ts-search-q" name="SearchText" value="{$q|wash}" maxlength="100" required="required" /></div>
    <div class="a4-actions"><button type="submit" class="a4-btn a4-btn-primary" name="TicketSearch" value="1">{'Search'|i18n( $tsI )}</button></div>
</div>
</form>
{if $q}
{if $tickets|count}
<p class="a4-meta" id="ts-search-total">{'%count tickets found.'|i18n( $tsI,, hash( '%count', $total ) )|wash}</p>
<div class="a4-table-wrap"><table class="a4-table" id="ts-search-results">
    <thead><tr><th scope="col">{'Number'|i18n( $tsI )}</th><th scope="col">{'Subject'|i18n( $tsI )}</th><th scope="col">{'Account'|i18n( $tsI )}</th>
        <th scope="col">{'Status'|i18n( $tsI )}</th><th scope="col">{'Assigned to'|i18n( $tsI )}</th><th scope="col">{'Last post'|i18n( $tsI )}</th></tr></thead>
    <tbody>
    {foreach $tickets as $t}
    <tr data-ts-number="{$t.number|wash}">
        <td class="a4-nowrap"><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.number|wash}</a></td>
        <td><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.subject|wash}</a>{if $t.in_note} <span class="a4-badge is-warn">{'in an internal note'|i18n( $tsI )}</span>{/if}</td>
        <td>{$t.account_label|wash}</td>
        <td>{include uri='design:ticketsystem/parts/status.tpl' label=$t.status_label tone=$t.status_tone}</td>
        <td>{if $t.assignee_id}{$t.assignee|wash}{else}<span class="a4-muted">{'nobody'|i18n( $tsI )}</span>{/if}</td>
        <td class="a4-nowrap">{$t.last_post|l10n( 'shortdatetime' )}</td>
    </tr>
    {/foreach}
    </tbody>
</table></div>
{if or( $pager_prev, $pager_next )}<nav class="a4-pager a4-actions" aria-label="{'Pages'|i18n( $tsI )|wash}">
    {if $pager_prev}<a class="a4-btn a4-btn-small" href={$pager_prev|ezurl}>{'Previous'|i18n( $tsI )}</a>{/if}
    {if $pager_next}<a class="a4-btn a4-btn-small" href={$pager_next|ezurl}>{'Next'|i18n( $tsI )}</a>{/if}</nav>{/if}
{else}
<p class="a4-empty" id="ts-search-empty">{'No ticket matches.'|i18n( $tsI )}</p>
{/if}
{/if}
{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
