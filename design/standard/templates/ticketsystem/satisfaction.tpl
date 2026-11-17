{* ticketsystem/satisfaction (doc/csat.md): the satisfaction ratings for staff. Input: $enabled, $days, $choices,
   $summary (ticketsystemCsat::summary(), null without the read scope All), $dist, $latest, the frame's variables.
   The administration restyles it (design admin4). Mobile first; no inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1">{'Satisfaction'|i18n( $tsI )}</h1>
        <p class="ktl-lede ts-lede">{'Ratings of solved tickets in the last %days days. Satisfied: 4 or 5 of 5.'|i18n( $tsI,, hash( '%days', $days ) )|wash}{if $enabled|not} {'Ratings are switched off ([CSATSettings] Enabled).'|i18n( $tsI )}{/if}</p>
    </div>
</div>
<nav class="ktl-tabs ts-tabs" aria-label="{'Period'|i18n( $tsI )|wash}">{foreach $choices as $tsD}<a class="ktl-tab ts-tab{if $tsD|eq( $days )} is-current{/if}" href={concat( 'ticketsystem/satisfaction/(days)/', $tsD )|ezurl}>{'%days days'|i18n( $tsI,, hash( '%days', $tsD ) )|wash}</a>{/foreach}</nav>
{if $summary|not}
<section class="ktl-card ktl-empty ts-card ts-empty"><p>{'The ratings are shown to support staff who may read every ticket.'|i18n( $tsI )}</p></section>
{else}
<div class="ktl-grid is-2-1 ts-grid">
<section class="ktl-card ts-card" aria-labelledby="ts-csat-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-csat-h"><i class="fa-solid fa-face-smile ktl-icon" aria-hidden="true"></i>{'Overview'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts ts-facts" id="ts-csat-figures">
        <dt>{'Satisfied'|i18n( $tsI )}</dt><dd id="ts-csat-share">{if $summary.count}{$summary.csat} %{else}&ndash;{/if}</dd>
        <dt>{'Average'|i18n( $tsI )}</dt><dd id="ts-csat-avg">{if $summary.count}{$summary.avg} / 5{else}&ndash;{/if}</dd>
        <dt>{'Ratings'|i18n( $tsI )}</dt><dd id="ts-csat-count">{$summary.count}</dd>
        <dt>{'Asked by mail'|i18n( $tsI )}</dt><dd id="ts-csat-asked">{$summary.asked}{if $summary.asked} ({'%pct % answered'|i18n( $tsI,, hash( '%pct', $summary.response_rate ) )|wash}){/if}</dd>
    </dl>
    <table class="ktl-table ts-table ts-csat-dist" id="ts-csat-dist">
        <thead><tr><th scope="col">{'Rating'|i18n( $tsI )}</th><th scope="col" class="ktl-num">{'Count'|i18n( $tsI )}</th><th scope="col" class="ktl-num">{'Share'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $dist as $tsR}<tr><td data-label="{'Rating'|i18n( $tsI )|wash}">{$tsR.score} &middot; {$tsR.label|i18n( $tsI )|wash}</td><td data-label="{'Count'|i18n( $tsI )|wash}" class="ktl-num">{$tsR.count}</td><td data-label="{'Share'|i18n( $tsI )|wash}" class="ktl-num">{$tsR.pct} %</td></tr>{/foreach}</tbody>
    </table>
</section>
<section class="ktl-card ts-card" aria-labelledby="ts-csat-by-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-csat-by-h">{'By queue and assignee'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts is-compact ts-facts">
    {foreach $summary.by_queue as $tsQ}<dt>{$tsQ.label|i18n( $tsI )|wash}</dt><dd>{$tsQ.csat} % &middot; {$tsQ.count}</dd>{/foreach}
    {foreach $summary.by_assignee as $tsA}<dt>{$tsA.label|wash}</dt><dd>{$tsA.csat} % &middot; {$tsA.count}</dd>{/foreach}
    </dl>
</section>
</div>
<section class="ktl-card ts-card" aria-labelledby="ts-csat-latest-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-csat-latest-h">{'Latest ratings'|i18n( $tsI )}</h2></div>
    {if $latest|count}
    <div class="ktl-table-wrap ts-table-wrap">
    <table class="ktl-table ts-table" id="ts-csat-latest">
        <thead><tr><th scope="col">{'Ticket'|i18n( $tsI )}</th><th scope="col">{'Rating'|i18n( $tsI )}</th><th scope="col" class="ktl-col-wide">{'Comment'|i18n( $tsI )}</th><th scope="col">{'Rated'|i18n( $tsI )}</th></tr></thead>
        <tbody>
        {foreach $latest as $tsR}
            <tr data-ts-number="{$tsR.number|wash}">
                <td data-label="{'Ticket'|i18n( $tsI )|wash}"><a href={concat( 'ticketsystem/view/', $tsR.number )|ezurl}>{$tsR.subject|wash}</a><br><span class="ktl-muted ktl-small ts-muted ts-small">{$tsR.number|wash}{if $tsR.category_label} &middot; {$tsR.category_label|i18n( $tsI )|wash}{/if}{if $tsR.assignee} &middot; {$tsR.assignee|wash}{/if}</span></td>
                <td data-label="{'Rating'|i18n( $tsI )|wash}">{include uri='design:ticketsystem/parts/status.tpl' label=concat( $tsR.score, ' · ', $tsR.label ) tone=cond( $tsR.satisfied, 'ok', $tsR.score|eq( 3 ), 'warn', 'bad' )}</td>
                <td data-label="{'Comment'|i18n( $tsI )|wash}" class="ktl-col-wide">{$tsR.comment|wash}</td>
                <td data-label="{'Rated'|i18n( $tsI )|wash}" class="ktl-nowrap">{$tsR.rated|l10n( 'shortdatetime' )}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    </div>
    {else}
    <p class="ktl-muted ts-muted">{'No ratings in this period.'|i18n( $tsI )}</p>
    {/if}
</section>
{/if}
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
