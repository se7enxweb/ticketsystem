{* ticketsystem/reports in the administration (doc/reports.md, doc/views.md): the figures of a period. The form posts
   and is redirected to view parameters (from, to, queue, period). Input: $range (from, to, from_text, to_text, days),
   $queue, $period, $asked_period, $categories, $figures (ticketsystemReport::figures), $volume (key, opened, solved,
   *_pct), $by_status, $by_category, $by_priority, $backlog, $backlog_ages, $backlog_by_category, $backlog_by_priority,
   $oldest, $workload (id, name, open, replies, tickets), $response_pct, $resolution_pct, $csat_dist, $csat_source
   (fetch|index|off), $sla_enabled, $truncated, $csv_url, the form token. Only the shared admin components (.a4-*). *}
{def $tsI = 'extension/ticketsystem'
     $tsF = $figures}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Reports'|i18n( $tsI )
         sub=concat( '%from to %to (%days days)'|i18n( $tsI,, hash( '%from', $range.from_text, '%to', $range.to_text, '%days', $range.days ) ),
                     cond( $queue, concat( ' · ', first_set( $categories[$queue], $queue )|i18n( $tsI ) ), '' ) )}

<form class="a4-section" method="post" action={'ticketsystem/reports'|ezurl} id="ts-report-form">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
    <div class="a4-filterbar">
        <div class="a4-field"><label for="ts-r-from">{'From'|i18n( $tsI )}</label><input class="a4-input" type="date" id="ts-r-from" name="ReportFrom" value="{$range.from_text|wash}" required /></div>
        <div class="a4-field"><label for="ts-r-to">{'To'|i18n( $tsI )}</label><input class="a4-input" type="date" id="ts-r-to" name="ReportTo" value="{$range.to_text|wash}" required /></div>
        <div class="a4-field"><label for="ts-r-queue">{'Queue'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-r-queue" name="ReportQueue"><option value="">{'Every queue'|i18n( $tsI )}</option>
            {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $queue|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
        <div class="a4-field"><label for="ts-r-period">{'Group by'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-r-period" name="ReportPeriod"><option value="">{'Automatic'|i18n( $tsI )}</option>
            {foreach hash( 'day', 'Day', 'week', 'Week', 'month', 'Month' ) as $tsK => $tsL}<option value="{$tsK}"{if $asked_period|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )}</option>{/foreach}</select></div>
        <div class="a4-field"><div class="a4-actions">
            <button type="submit" class="a4-btn a4-btn-primary" name="ReportRun" value="1" id="ts-report-run">{'Show'|i18n( $tsI )}</button>
            <a class="a4-btn" id="ts-csv" href={$csv_url|ezurl}>{'Download CSV'|i18n( $tsI )}</a>
        </div></div>
    </div>
</form>
{if $truncated}<p class="a4-notice is-warn" role="status">{'This period has more tickets than one report counts; choose a shorter period for exact figures.'|i18n( $tsI )}</p>{/if}

<ul class="a4-figures" id="ts-report-figures">
    <li class="a4-figure"><strong data-ts-figure="opened">{$tsF.opened}</strong> <span>{'opened'|i18n( $tsI )}</span></li>
    <li class="a4-figure is-ok"><strong data-ts-figure="resolved">{$tsF.resolved}</strong> <span>{'of these solved or closed'|i18n( $tsI )}</span></li>
    <li class="a4-figure"><strong data-ts-figure="active">{$tsF.active}</strong> <span>{'of these still open'|i18n( $tsI )}</span></li>
    <li class="a4-figure{if $tsF.unassigned|gt( 0 )} is-attention{/if}"><a href={'ticketsystem/list/(assignee)/none'|ezurl}><strong data-ts-figure="unassigned">{$tsF.unassigned}</strong> <span>{'open and unassigned'|i18n( $tsI )}</span></a></li>
    <li class="a4-figure{if $tsF.escalated|gt( 0 )} is-bad{/if}"><a href={'ticketsystem/list/(sla)/escalated'|ezurl}><strong data-ts-figure="escalated">{$tsF.escalated}</strong> <span>{'escalated'|i18n( $tsI )}</span></a></li>
    <li class="a4-figure"><a href={'ticketsystem/list'|ezurl}><strong>{$backlog.total}</strong> <span>{'not solved now'|i18n( $tsI )}{if $oldest}, {'the oldest open for %age'|i18n( $tsI,, hash( '%age', $oldest ) )|wash}{/if}</span></a></li>
</ul>

<div class="a4-grid is-wide">
<section class="a4-card is-flush" aria-labelledby="ts-r-vol-h" id="ts-report-volume-card">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-r-vol-h">{'Volume'|i18n( $tsI )}</h2></div>
    {if $volume|count}
    <div class="a4-table-wrap"><table class="a4-table is-compact" id="ts-report-volume">
        <thead><tr><th scope="col">{'Period'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Opened'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Solved'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $volume as $tsV}<tr><td class="a4-nowrap">{$tsV.key|wash}</td>
            <td class="a4-num">{$tsV.opened}<span class="a4-meter is-info" aria-hidden="true"><span class="a4-pct-{$tsV.opened_pct}"></span></span></td>
            <td class="a4-num">{$tsV.solved}<span class="a4-meter is-ok" aria-hidden="true"><span class="a4-pct-{$tsV.solved_pct}"></span></span></td></tr>{/foreach}</tbody>
    </table></div>
    {else}<p class="a4-empty">{'No tickets in this period.'|i18n( $tsI )}</p>{/if}
</section>

<section class="a4-card" aria-labelledby="ts-r-times-h" id="ts-report-times-card">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-r-times-h">{'Response and resolution'|i18n( $tsI )}</h2></div>
    <dl class="a4-facts is-rows" id="ts-report-times">
        <div class="a4-field-wide"><dt>{'First response'|i18n( $tsI )}</dt><dd data-ts-figure="first_response">{if $tsF.first_response.count}{'%avg min on average, median %median min (%count tickets)'|i18n( $tsI,, hash( '%avg', $tsF.first_response.avg_minutes, '%median', $tsF.first_response.median_minutes, '%count', $tsF.first_response.count ) )|wash}{else}&ndash;{/if}</dd></div>
        <div class="a4-field-wide"><dt>{'Resolution'|i18n( $tsI )}</dt><dd data-ts-figure="resolution">{if $tsF.resolution.count}{'%avg h on average, median %median h (%count tickets)'|i18n( $tsI,, hash( '%avg', $tsF.resolution.avg_hours, '%median', $tsF.resolution.median_hours, '%count', $tsF.resolution.count ) )|wash}{else}&ndash;{/if}</dd></div>
        <div class="a4-field-wide"><dt>{'SLA: first response met'|i18n( $tsI )}</dt><dd data-ts-figure="sla_response">{if $response_pct|is_null|not}{$response_pct} % ({$tsF.sla.response_met} / {$tsF.sla.response_total}){elseif $sla_enabled|not}{'SLA timers are off.'|i18n( $tsI )}{else}&ndash;{/if}</dd></div>
        <div class="a4-field-wide"><dt>{'SLA: resolution met'|i18n( $tsI )}</dt><dd data-ts-figure="sla_resolution">{if $resolution_pct|is_null|not}{$resolution_pct} % ({$tsF.sla.resolution_met} / {$tsF.sla.resolution_total}){elseif $sla_enabled|not}{'SLA timers are off.'|i18n( $tsI )}{else}&ndash;{/if}</dd></div>
        <div class="a4-field-wide"><dt>{'Satisfaction'|i18n( $tsI )}</dt><dd data-ts-figure="csat" data-ts-source="{$csat_source|wash}">{if $tsF.csat.count}{'%avg of 5 from %count ratings'|i18n( $tsI,, hash( '%avg', $tsF.csat.avg, '%count', $tsF.csat.count ) )|wash}{if $tsF.csat.satisfied_pct|is_null|not}, {'%pct % satisfied'|i18n( $tsI,, hash( '%pct', $tsF.csat.satisfied_pct ) )|wash}{/if}{if $tsF.csat.response_rate|is_null|not}, {'%pct % of the rating mails answered'|i18n( $tsI,, hash( '%pct', $tsF.csat.response_rate ) )|wash}{/if}{elseif $csat_source|eq( 'off' )}{'Ratings are off.'|i18n( $tsI )}{else}{'No ratings in this period.'|i18n( $tsI )}{/if}</dd></div>
    </dl>
</section>

{if $tsF.csat.count}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$csat_dist|reverse title='Ratings'|i18n( $tsI ) id='ts-report-csat'}
{/if}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$by_status title='Opened in the period by status'|i18n( $tsI ) id='ts-report-status'}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$by_category title='Opened in the period by queue'|i18n( $tsI ) id='ts-report-queue'}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$by_priority title='Opened in the period by priority'|i18n( $tsI ) id='ts-report-priority'}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$backlog_ages title='Backlog by age'|i18n( $tsI ) id='ts-report-backlog'}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$backlog_by_category title='Backlog by queue'|i18n( $tsI ) id='ts-report-backlog-queue'}
{include uri='design:ticketsystem/parts/bars.tpl' rows=$backlog_by_priority title='Backlog by priority'|i18n( $tsI ) id='ts-report-backlog-priority'}
</div>

<section class="a4-card is-flush" aria-labelledby="ts-r-work-h" id="ts-report-workload">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-r-work-h">{'Workload'|i18n( $tsI )}</h2><p class="a4-meta">{'Open tickets assigned now; replies in the period'|i18n( $tsI )}</p></div>
    {if $workload|count}
    <div class="a4-table-wrap"><table class="a4-table">
        <thead><tr><th scope="col">{'Staff member'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Open tickets'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Replies'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Tickets answered'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $workload as $tsW}<tr data-ts-staff="{$tsW.id}"><td><a href={concat( 'ticketsystem/list/(assignee)/', $tsW.id )|ezurl}>{$tsW.name|wash}</a></td>
            <td class="a4-num">{$tsW.open}</td><td class="a4-num">{$tsW.replies}</td><td class="a4-num">{$tsW.tickets}</td></tr>{/foreach}</tbody>
    </table></div>
    {else}<p class="a4-empty">{'No open tickets and no replies in this period.'|i18n( $tsI )}</p>{/if}
</section>

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI $tsF}
