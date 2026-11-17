{* ticketsystem/time (1.2, doc/time-tracking.md): the time spent on tickets, for staff with ticketsystem/report. Same
   input as the administration's design:ticketsystem/time.tpl. Mobile first; no inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1">{'Time'|i18n( $tsI )}</h1>
        <p class="ktl-lede ts-lede">{'%from to %to'|i18n( $tsI,, hash( '%from', $filter.from, '%to', $filter.to ) )|wash}</p>
    </div>
</div>
<form class="ktl-card ktl-form ts-card ts-form" method="post" action={'ticketsystem/time'|ezurl} id="ts-time-form">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
    <div class="ktl-fields-2 ts-fields-2">
        <div class="ktl-field ts-field"><label for="ts-t-from">{'From'|i18n( $tsI )}</label><input class="ktl-input ts-input" type="date" id="ts-t-from" name="TimeFrom" value="{$filter.from|wash}" required /></div>
        <div class="ktl-field ts-field"><label for="ts-t-to">{'To'|i18n( $tsI )}</label><input class="ktl-input ts-input" type="date" id="ts-t-to" name="TimeTo" value="{$filter.to|wash}" required /></div>
        <div class="ktl-field ts-field"><label for="ts-t-account">{'Account'|i18n( $tsI )}</label><input class="ktl-input ts-input" type="text" id="ts-t-account" name="TimeAccount" value="{$filter.account|wash}" maxlength="40" /></div>
        <div class="ktl-field ts-field"><label for="ts-t-billable">{'Billable'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-t-billable" name="TimeBillable">{foreach hash( 'all', 'All', 'yes', 'Billable', 'no', 'Not billable' ) as $tsK => $tsL}<option value="{$tsK}"{if $filter.billable|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )}</option>{/foreach}</select></div>
    </div>
    <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="TimeRun" value="1" id="ts-time-run">{'Show'|i18n( $tsI )}</button>
        <a class="ktl-btn ts-btn" id="ts-time-csv" href={$csv_url|ezurl}>{'Download CSV'|i18n( $tsI )}</a></p>
</form>
<section class="ktl-card ts-card" aria-labelledby="ts-time-sum-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-time-sum-h">{'Sums'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts ts-facts" id="ts-time-figures">
        <dt>{'In all'|i18n( $tsI )}</dt><dd>{$sums.minutes_label|wash} ({$total})</dd>
        <dt>{'Billable'|i18n( $tsI )}</dt><dd>{$sums.billable_label|wash}</dd>
        <dt>{'Covered by an SLA or warranty'|i18n( $tsI )}</dt><dd>{$sums.covered_label|wash}</dd>
        <dt>{'Billed'|i18n( $tsI )}</dt><dd>{$sums.billed_label|wash}</dd>
    </dl>
</section>
<section class="ktl-card ts-card" aria-labelledby="ts-time-rows-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-time-rows-h">{'Entries'|i18n( $tsI )}</h2></div>
    {if $rows|count}
    <table class="ktl-table ts-table" id="ts-time-rows">
        <thead><tr><th scope="col">{'Date'|i18n( $tsI )}</th><th scope="col">{'Ticket'|i18n( $tsI )}</th><th scope="col">{'Staff'|i18n( $tsI )}</th><th scope="col">{'Time'|i18n( $tsI )}</th><th scope="col">{'Billable'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $rows as $r}<tr><td>{$r.work_date|l10n( 'shortdate' )}</td><td><a href={concat( 'ticketsystem/view/', $r.number )|ezurl}>{$r.number|wash}</a> &middot; {$r.account_label|wash}</td>
            <td>{$r.user|wash}</td><td>{$r.label|wash}</td><td>{if $r.billable}{'billable'|i18n( $tsI )}{else}{'no'|i18n( $tsI )}{/if}{if $r.billed} &middot; {'billed'|i18n( $tsI )}{/if}</td></tr>{/foreach}</tbody>
    </table>
    {else}<p class="ts-muted">{'No time in this period.'|i18n( $tsI )}</p>{/if}
</section>
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
