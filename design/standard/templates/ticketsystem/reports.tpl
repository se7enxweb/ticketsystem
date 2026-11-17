{* ticketsystem/reports on the site (doc/reports.md); the administration has its own (design admin4). *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead"><div><h1 class="ktl-h1 ts-h1">{'Reports'|i18n( $tsI )}</h1>
    <p class="ktl-lede ts-lede">{'%days days'|i18n( $tsI,, hash( '%days', $days ) )|wash}</p></div>
    <p class="ktl-actions ts-actions"><a class="ktl-btn ts-btn" href={concat( 'ticketsystem/reports/(days)/', $days, '/(format)/csv' )|ezurl}>{'Download CSV'|i18n( $tsI )}</a></p></div>
<nav class="ktl-tabs ts-tabs">{foreach $choices as $tsD}<a class="ktl-tab ts-tab{if $tsD|eq( $days )} is-current{/if}" href={concat( 'ticketsystem/reports/(days)/', $tsD )|ezurl}>{'%days days'|i18n( $tsI,, hash( '%days', $tsD ) )|wash}</a>{/foreach}</nav>
<section class="ktl-card ts-card">
<dl class="ktl-facts ts-facts" id="ts-report-figures">
    <dt>{'opened'|i18n( $tsI )}</dt><dd>{$figures.opened}</dd>
    <dt>{'solved or closed'|i18n( $tsI )}</dt><dd>{$figures.resolved}</dd>
    <dt>{'open and unassigned'|i18n( $tsI )}</dt><dd>{$figures.unassigned}</dd>
    <dt>{'escalated'|i18n( $tsI )}</dt><dd>{$figures.escalated}</dd>
    <dt>{'First response'|i18n( $tsI )}</dt><dd>{$figures.first_response.avg_minutes} min</dd>
    <dt>{'Satisfaction'|i18n( $tsI )}</dt><dd>{$figures.csat.avg} / 5 ({$figures.csat.count})</dd>
</dl>
</section>
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
