{* ticketsystem/reports in the administration (doc/reports.md). Input: $days, $choices, $figures
   (ticketsystemReport::figures), $by_status, $by_category, $by_priority, $by_assignee, $response_pct,
   $resolution_pct, $csat_dist. *}
{def $tsI = 'extension/ticketsystem'
     $tsF = $figures}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Reports'|i18n( $tsI ) sub='The tickets opened in the period, within what you may read.'|i18n( $tsI )}

<div class="a4-toolbar">
    <ul class="a4-tabs" aria-label="{'Period'|i18n( $tsI )|wash}">
    {foreach $choices as $tsD}<li>{if $tsD|eq( $days )}<span class="is-current" aria-current="page">{'%days days'|i18n( $tsI,, hash( '%days', $tsD ) )|wash}</span>{else}<a href={concat( 'ticketsystem/reports/(days)/', $tsD )|ezurl}>{'%days days'|i18n( $tsI,, hash( '%days', $tsD ) )|wash}</a>{/if}</li>{/foreach}
    </ul>
    <a class="a4-btn a4-btn-small" id="ts-csv" href={concat( 'ticketsystem/reports/(days)/', $days, '/(format)/csv' )|ezurl}>{'Download CSV'|i18n( $tsI )}</a>
</div>

<ul class="a4-figures" id="ts-report-figures">
    <li class="a4-figure is-info"><strong>{$tsF.opened}</strong> <span>{'opened'|i18n( $tsI )}</span></li>
    <li class="a4-figure is-ok"><strong>{$tsF.resolved}</strong> <span>{'solved or closed'|i18n( $tsI )}</span></li>
    <li class="a4-figure"><strong>{$tsF.active}</strong> <span>{'still open'|i18n( $tsI )}</span></li>
    <li class="a4-figure{if $tsF.unassigned} is-attention{/if}"><strong>{$tsF.unassigned}</strong> <span>{'open and unassigned'|i18n( $tsI )}</span></li>
    <li class="a4-figure{if $tsF.escalated} is-attention{/if}"><strong>{$tsF.escalated}</strong> <span>{'escalated'|i18n( $tsI )}</span></li>
    <li class="a4-figure{if $tsF.breached} is-bad{/if}"><strong>{$tsF.breached}</strong> <span>{'missed an SLA target'|i18n( $tsI )}</span></li>
</ul>

<div class="a4-grid is-wide">
<section class="a4-card" aria-labelledby="ts-r-times-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-r-times-h">{'Response and resolution'|i18n( $tsI )}</h2></div>
    <div class="a4-card-body">
    <dl class="a4-facts is-rows" id="ts-report-times">
        <dt>{'First response'|i18n( $tsI )}</dt><dd>{if $tsF.first_response.count}{'%avg min on average, median %median min (%count tickets)'|i18n( $tsI,, hash( '%avg', $tsF.first_response.avg_minutes, '%median', $tsF.first_response.median_minutes, '%count', $tsF.first_response.count ) )|wash}{else}<span class="a4-muted">&ndash;</span>{/if}</dd>
        <dt>{'Resolution'|i18n( $tsI )}</dt><dd>{if $tsF.resolution.count}{'%avg h on average (%count tickets)'|i18n( $tsI,, hash( '%avg', $tsF.resolution.avg_hours, '%count', $tsF.resolution.count ) )|wash}{else}<span class="a4-muted">&ndash;</span>{/if}</dd>
        <dt>{'SLA: first response met'|i18n( $tsI )}</dt><dd>{if $response_pct|is_null|not}{$response_pct} % ({$tsF.sla.response_met} / {$tsF.sla.response_total}){else}<span class="a4-muted">&ndash;</span>{/if}</dd>
        <dt>{'SLA: resolution met'|i18n( $tsI )}</dt><dd>{if $resolution_pct|is_null|not}{$resolution_pct} % ({$tsF.sla.resolution_met} / {$tsF.sla.resolution_total}){else}<span class="a4-muted">&ndash;</span>{/if}</dd>
    </dl>
    </div>
</section>
<section class="a4-card" aria-labelledby="ts-r-csat-h" id="ts-report-csat">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-r-csat-h">{'Satisfaction'|i18n( $tsI )}</h2>
        <p class="a4-meta">{if $tsF.csat.count}{'%avg of 5 from %count ratings'|i18n( $tsI,, hash( '%avg', $tsF.csat.avg, '%count', $tsF.csat.count ) )|wash}{else}{'No ratings in this period.'|i18n( $tsI )}{/if}</p></div>
    <div class="a4-card-body">
    <table class="a4-table is-compact"><tbody>
    {foreach $csat_dist|reverse as $tsR}<tr><th scope="row">{$tsR.label} / 5</th><td><span class="a4-meter is-ok" role="img" aria-label="{$tsR.count}"><span class="a4-pct-{$tsR.pct}"></span></span></td><td class="a4-num">{$tsR.count}</td></tr>{/foreach}
    </tbody></table>
    </div>
</section>
{include uri='design:ticketsystem/parts/bars.tpl' rows=$by_status title='By status'|i18n( $tsI ) id='ts-report-status'}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$by_category title='By queue'|i18n( $tsI ) id='ts-report-queue'}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$by_priority title='By priority'|i18n( $tsI ) id='ts-report-priority'}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$by_assignee title='By assignee'|i18n( $tsI ) id='ts-report-assignee'}
</div>

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI $tsF}
