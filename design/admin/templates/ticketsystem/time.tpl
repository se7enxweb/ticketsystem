{* ticketsystem/time in the administration (1.2, doc/time-tracking.md): the time spent on tickets in a period. The form
   posts and is redirected to view parameters. Input: $filter (from, to, account, user, billable, billed), $rows
   (ticketsystemTime::report), $total, $sums (minutes_label, billable_label, covered_label, billed_label, accounts,
   users), $staff, $enabled, $csv_url, the form token. Only the shared admin components (.a4-*). *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Time'|i18n( $tsI )
         sub='%from to %to'|i18n( $tsI,, hash( '%from', $filter.from, '%to', $filter.to ) )}

{if $enabled|not}<p class="a4-notice is-warn" role="status">{'Time tracking is switched off ([TimeSettings] Enabled); the entries recorded before are shown.'|i18n( $tsI )}</p>{/if}

<form class="a4-section" method="post" action={'ticketsystem/time'|ezurl} id="ts-time-form">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
    <div class="a4-filterbar">
        <div class="a4-field"><label for="ts-t-from">{'From'|i18n( $tsI )}</label><input class="a4-input" type="date" id="ts-t-from" name="TimeFrom" value="{$filter.from|wash}" required /></div>
        <div class="a4-field"><label for="ts-t-to">{'To'|i18n( $tsI )}</label><input class="a4-input" type="date" id="ts-t-to" name="TimeTo" value="{$filter.to|wash}" required /></div>
        <div class="a4-field"><label for="ts-t-account">{'Account'|i18n( $tsI )}</label><input class="a4-input" type="text" id="ts-t-account" name="TimeAccount" value="{$filter.account|wash}" placeholder="katalyst:12" maxlength="40" /></div>
        <div class="a4-field"><label for="ts-t-user">{'Staff'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-t-user" name="TimeUser"><option value="">{'Everyone'|i18n( $tsI )}</option>
            {foreach $staff as $tsK => $tsL}<option value="{$tsK}"{if $filter.user|eq( $tsK )} selected="selected"{/if}>{$tsL|wash}</option>{/foreach}</select></div>
        <div class="a4-field"><label for="ts-t-billable">{'Billable'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-t-billable" name="TimeBillable">{foreach hash( 'all', 'All', 'yes', 'Billable', 'no', 'Not billable' ) as $tsK => $tsL}<option value="{$tsK}"{if $filter.billable|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )}</option>{/foreach}</select></div>
        <div class="a4-field"><label for="ts-t-billed">{'Billed'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-t-billed" name="TimeBilled">{foreach hash( 'all', 'All', 'yes', 'Billed', 'no', 'Not billed yet' ) as $tsK => $tsL}<option value="{$tsK}"{if $filter.billed|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )}</option>{/foreach}</select></div>
        <div class="a4-field"><div class="a4-actions">
            <button type="submit" class="a4-btn a4-btn-primary" name="TimeRun" value="1" id="ts-time-run">{'Show'|i18n( $tsI )}</button>
            <a class="a4-btn" id="ts-time-csv" href={$csv_url|ezurl}>{'Download CSV'|i18n( $tsI )}</a>
        </div></div>
    </div>
</form>

<ul class="a4-figures" id="ts-time-figures">
    <li class="a4-figure"><strong data-ts-figure="minutes">{$sums.minutes_label|wash}</strong> <span>{'in all (%count entries)'|i18n( $tsI,, hash( '%count', $total ) )|wash}</span></li>
    <li class="a4-figure is-attention"><strong data-ts-figure="billable">{$sums.billable_label|wash}</strong> <span>{'billable'|i18n( $tsI )}</span></li>
    <li class="a4-figure"><strong data-ts-figure="covered">{$sums.covered_label|wash}</strong> <span>{'covered by an SLA or warranty'|i18n( $tsI )}</span></li>
    <li class="a4-figure is-ok"><strong data-ts-figure="billed">{$sums.billed_label|wash}</strong> <span>{'billed'|i18n( $tsI )}</span></li>
</ul>

<div class="a4-grid is-wide">
{foreach hash( 'accounts', 'Per account', 'users', 'Per staff member' ) as $tsK => $tsL}
<section class="a4-card is-flush" aria-labelledby="ts-time-{$tsK}-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-time-{$tsK}-h">{$tsL|i18n( $tsI )}</h2></div>
    {if $sums[$tsK]|count}
    <div class="a4-table-wrap"><table class="a4-table is-compact">
        <thead><tr><th scope="col">{cond( $tsK|eq( 'accounts' ), 'Account', 'Staff' )|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Time'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Billable'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $sums[$tsK] as $tsS}<tr><td>{$tsS.label|wash}</td><td class="a4-num">{$tsS.label_time|wash}</td><td class="a4-num">{$tsS.label_billable|wash}</td></tr>{/foreach}</tbody>
    </table></div>
    {else}<div class="a4-card-body"><p class="a4-empty">{'No time in this period.'|i18n( $tsI )}</p></div>{/if}
</section>
{/foreach}
</div>

<section class="a4-card is-flush" aria-labelledby="ts-time-rows-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-time-rows-h">{'Entries'|i18n( $tsI )}</h2></div>
    {if $rows|count}
    <div class="a4-table-wrap"><table class="a4-table is-compact" id="ts-time-rows">
        <thead><tr><th scope="col">{'Date'|i18n( $tsI )}</th><th scope="col">{'Ticket'|i18n( $tsI )}</th><th scope="col">{'Account'|i18n( $tsI )}</th><th scope="col">{'Staff'|i18n( $tsI )}</th>
            <th scope="col" class="a4-num">{'Time'|i18n( $tsI )}</th><th scope="col">{'Billable'|i18n( $tsI )}</th><th scope="col">{'Remark'|i18n( $tsI )}</th></tr></thead>
        <tbody>
        {foreach $rows as $r}
        <tr><td class="a4-nowrap">{$r.work_date|l10n( 'shortdate' )}</td>
            <td class="a4-nowrap"><a href={concat( 'ticketsystem/view/', $r.number )|ezurl}>{$r.number|wash}</a></td>
            <td>{$r.account_label|wash}</td><td>{$r.user|wash}</td><td class="a4-num">{$r.label|wash}</td>
            <td>{if $r.billable}<span class="a4-badge is-warn">{'billable'|i18n( $tsI )}</span>{else}{'no'|i18n( $tsI )}{/if}{if $r.covered} <span class="a4-badge">{$r.covered|wash}</span>{/if}{if $r.billed} <span class="a4-badge is-ok">{'billed'|i18n( $tsI )}</span>{/if}</td>
            <td>{$r.remark|wash}</td></tr>
        {/foreach}
        </tbody>
    </table></div>
    {else}<div class="a4-card-body"><p class="a4-empty">{'No time in this period.'|i18n( $tsI )}</p></div>{/if}
</section>

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
