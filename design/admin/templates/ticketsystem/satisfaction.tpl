{* ticketsystem/satisfaction in the administration (doc/csat.md, doc/views.md): the ratings of solved tickets. Input:
   $enabled, $days, $choices, $summary (ticketsystemCsat::summary(), null without the read scope All), $dist, $latest.
   Only the shared admin components (.a4-*). *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Satisfaction'|i18n( $tsI )
         sub='Ratings of solved tickets in the last %days days. Satisfied: 4 or 5 of 5.'|i18n( $tsI,, hash( '%days', $days ) )}

{if $enabled|not}<p class="a4-notice is-info">{'Ratings are switched off ([CSATSettings] Enabled).'|i18n( $tsI )}</p>{/if}

<nav class="a4-tabs is-segmented a4-section" aria-label="{'Period'|i18n( $tsI )|wash}" id="ts-csat-days">
{foreach $choices as $tsD}{if $tsD|eq( $days )}<span class="current" aria-current="page">{'%days days'|i18n( $tsI,, hash( '%days', $tsD ) )|wash}</span>{else}<a href={concat( 'ticketsystem/satisfaction/(days)/', $tsD )|ezurl}>{'%days days'|i18n( $tsI,, hash( '%days', $tsD ) )|wash}</a>{/if}{/foreach}
</nav>

{if $summary|not}
<p class="a4-notice is-info">{'The ratings are shown to support staff who may read every ticket.'|i18n( $tsI )}</p>
{else}
<ul class="a4-figures" id="ts-csat-figures">
    <li class="a4-figure is-ok"><strong id="ts-csat-share">{if $summary.count}{$summary.csat} %{else}&ndash;{/if}</strong> <span>{'satisfied'|i18n( $tsI )}</span></li>
    <li class="a4-figure"><strong id="ts-csat-avg">{if $summary.count}{$summary.avg} / 5{else}&ndash;{/if}</strong> <span>{'on average'|i18n( $tsI )}</span></li>
    <li class="a4-figure"><strong id="ts-csat-count">{$summary.count}</strong> <span>{'ratings'|i18n( $tsI )}</span></li>
    <li class="a4-figure"><strong id="ts-csat-asked">{$summary.asked}</strong> <span>{'asked by mail'|i18n( $tsI )}{if $summary.asked}, {'%pct % answered'|i18n( $tsI,, hash( '%pct', $summary.response_rate ) )|wash}{/if}</span></li>
</ul>

<div class="a4-grid is-wide">
<section class="a4-card is-flush" aria-labelledby="ts-csat-dist-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-csat-dist-h">{'Ratings'|i18n( $tsI )}</h2></div>
    <table class="a4-table is-compact" id="ts-csat-dist">
        <thead><tr><th scope="col">{'Rating'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Count'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Share'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $dist as $tsR}<tr><td>{$tsR.score} &middot; {$tsR.label|i18n( $tsI )|wash}<span class="a4-meter{if $tsR.score|ge( 4 )} is-ok{elseif $tsR.score|eq( 3 )} is-warn{else} is-bad{/if} a4-pct-{$tsR.pct}" aria-hidden="true"><span></span></span></td><td class="a4-num">{$tsR.count}</td><td class="a4-num">{$tsR.pct} %</td></tr>{/foreach}</tbody>
    </table>
</section>
<section class="a4-card is-flush" aria-labelledby="ts-csat-by-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-csat-by-h">{'By queue and assignee'|i18n( $tsI )}</h2></div>
    {if or( $summary.by_queue|count, $summary.by_assignee|count )}
    <table class="a4-table is-compact" id="ts-csat-by">
        <thead><tr><th scope="col">{'Queue or assignee'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Satisfied'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Ratings'|i18n( $tsI )}</th></tr></thead>
        <tbody>
        {foreach $summary.by_queue as $tsQ}<tr><td>{$tsQ.label|i18n( $tsI )|wash} <span class="a4-tag">{'queue'|i18n( $tsI )}</span></td><td class="a4-num">{$tsQ.csat} %</td><td class="a4-num">{$tsQ.count}</td></tr>{/foreach}
        {foreach $summary.by_assignee as $tsA}<tr><td>{$tsA.label|wash}</td><td class="a4-num">{$tsA.csat} %</td><td class="a4-num">{$tsA.count}</td></tr>{/foreach}
        </tbody>
    </table>
    {else}
    <p class="a4-empty">{'No ratings in this period.'|i18n( $tsI )}</p>
    {/if}
</section>
</div>

<section class="a4-card" aria-labelledby="ts-csat-latest-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-csat-latest-h">{'Latest ratings'|i18n( $tsI )}</h2></div>
    {if $latest|count}
    <div class="a4-table-wrap"><table class="a4-table" id="ts-csat-latest">
        <thead><tr><th scope="col">{'Ticket'|i18n( $tsI )}</th><th scope="col">{'Rating'|i18n( $tsI )}</th><th scope="col">{'Comment'|i18n( $tsI )}</th><th scope="col">{'Rated'|i18n( $tsI )}</th></tr></thead>
        <tbody>
        {foreach $latest as $tsR}
        <tr data-ts-number="{$tsR.number|wash}">
            <td><a href={concat( 'ticketsystem/view/', $tsR.number )|ezurl}>{$tsR.subject|wash}</a> <span class="a4-meta">{$tsR.number|wash}{if $tsR.category_label} &middot; {$tsR.category_label|i18n( $tsI )|wash}{/if}{if $tsR.assignee} &middot; {$tsR.assignee|wash}{/if}</span></td>
            <td>{include uri='design:ticketsystem/parts/status.tpl' label=concat( $tsR.score, ' · ', $tsR.label ) tone=cond( $tsR.satisfied, 'ok', $tsR.score|eq( 3 ), 'warn', 'bad' )}</td>
            <td class="a4-wrap">{$tsR.comment|wash}</td>
            <td class="a4-nowrap">{$tsR.rated|l10n( 'shortdatetime' )}</td>
        </tr>
        {/foreach}
        </tbody>
    </table></div>
    {else}
    <p class="a4-empty">{'No ratings in this period.'|i18n( $tsI )}</p>
    {/if}
</section>
{/if}

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
