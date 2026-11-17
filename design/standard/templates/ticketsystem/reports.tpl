{* ticketsystem/reports (doc/reports.md): the figures of a period; the administration has its own (design admin4).
   Input: $range (from, to, from_text, to_text, days), $queue, $period, $asked_period, $categories, $figures
   (ticketsystemReport::figures), $volume (key, opened, solved, *_pct), $by_status, $by_category, $by_priority,
   $by_assignee, $backlog, $backlog_ages, $backlog_by_category, $backlog_by_priority, $oldest, $workload (id, name, open,
   replies, tickets), $response_pct, $resolution_pct, $csat_dist, $csat_source (fetch|index|off), $sla_enabled,
   $csat_enabled, $truncated, $csv_url, the form token. Bars are <meter> elements: no inline CSS. *}
{def $tsI = 'extension/ticketsystem'
     $tsF = $figures}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead"><div><h1 class="ktl-h1 ts-h1">{'Reports'|i18n( $tsI )}</h1>
    <p class="ktl-lede ts-lede" id="ts-report-range">{'%from to %to (%days days)'|i18n( $tsI,, hash( '%from', $range.from_text, '%to', $range.to_text, '%days', $range.days ) )|wash}{if $queue} &middot; {$categories[$queue]|i18n( $tsI )|wash}{/if}</p></div>
    <p class="ktl-actions ts-actions"><a class="ktl-btn ts-btn" id="ts-csv" href={$csv_url|ezurl}>{'Download CSV'|i18n( $tsI )}</a></p></div>

<section class="ktl-card ts-card" aria-label="{'Period and queue'|i18n( $tsI )|wash}">
<form class="ktl-form ts-form" method="post" action={'ticketsystem/reports'|ezurl} id="ts-report-form">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
    <div class="ktl-grid is-3 ts-grid">
        <div class="ktl-field ts-field"><label for="ts-r-from">{'From'|i18n( $tsI )}</label><input class="ktl-input ts-input" type="date" id="ts-r-from" name="ReportFrom" value="{$range.from_text|wash}" required /></div>
        <div class="ktl-field ts-field"><label for="ts-r-to">{'To'|i18n( $tsI )}</label><input class="ktl-input ts-input" type="date" id="ts-r-to" name="ReportTo" value="{$range.to_text|wash}" required /></div>
        <div class="ktl-field ts-field"><label for="ts-r-queue">{'Queue'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-r-queue" name="ReportQueue"><option value="">{'Every queue'|i18n( $tsI )}</option>
            {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $queue|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
        <div class="ktl-field ts-field"><label for="ts-r-period">{'Group by'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-r-period" name="ReportPeriod"><option value="">{'Automatic'|i18n( $tsI )}</option>
            {foreach hash( 'day', 'Day', 'week', 'Week', 'month', 'Month' ) as $tsK => $tsL}<option value="{$tsK}"{if $asked_period|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )}</option>{/foreach}</select></div>
    </div>
    <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="ReportRun" value="1" id="ts-report-run">{'Show'|i18n( $tsI )}</button></p>
</form>
</section>
{if $truncated}<div class="ktl-notice is-warn ts-notice is-warn" role="status"><p>{'This period has more tickets than one report counts; choose a shorter period for exact figures.'|i18n( $tsI )}</p></div>{/if}

<section class="ktl-card ts-card" aria-labelledby="ts-r-sum-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-r-sum-h">{'Volume'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts ts-facts" id="ts-report-figures">
        <dt>{'Opened'|i18n( $tsI )}</dt><dd data-ts-figure="opened">{$tsF.opened}</dd>
        <dt>{'Of these solved or closed'|i18n( $tsI )}</dt><dd data-ts-figure="resolved">{$tsF.resolved}</dd>
        <dt>{'Of these still open'|i18n( $tsI )}</dt><dd data-ts-figure="active">{$tsF.active}</dd>
        <dt>{'Open and unassigned'|i18n( $tsI )}</dt><dd data-ts-figure="unassigned">{$tsF.unassigned}</dd>
        <dt>{'Escalated'|i18n( $tsI )}</dt><dd data-ts-figure="escalated">{$tsF.escalated}</dd>
    </dl>
    <div class="ktl-table-wrap ts-table-wrap"><table class="ktl-table ts-table" id="ts-report-volume">
        <thead><tr><th scope="col">{'Period'|i18n( $tsI )}</th><th scope="col" class="ktl-num">{'Opened'|i18n( $tsI )}</th><th scope="col" class="ktl-num">{'Solved'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $volume as $tsV}<tr><th scope="row">{$tsV.key|wash}</th>
            <td class="ktl-num"><meter min="0" max="100" value="{$tsV.opened_pct}"></meter> {$tsV.opened}</td>
            <td class="ktl-num"><meter min="0" max="100" value="{$tsV.solved_pct}"></meter> {$tsV.solved}</td></tr>{/foreach}</tbody>
    </table></div>
</section>

<section class="ktl-card ts-card" aria-labelledby="ts-r-times-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-r-times-h">{'Response and resolution'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts ts-facts" id="ts-report-times">
        <dt>{'First response'|i18n( $tsI )}</dt><dd data-ts-figure="first_response">{if $tsF.first_response.count}{'%avg min on average, median %median min (%count tickets)'|i18n( $tsI,, hash( '%avg', $tsF.first_response.avg_minutes, '%median', $tsF.first_response.median_minutes, '%count', $tsF.first_response.count ) )|wash}{else}&ndash;{/if}</dd>
        <dt>{'Resolution'|i18n( $tsI )}</dt><dd data-ts-figure="resolution">{if $tsF.resolution.count}{'%avg h on average, median %median h (%count tickets)'|i18n( $tsI,, hash( '%avg', $tsF.resolution.avg_hours, '%median', $tsF.resolution.median_hours, '%count', $tsF.resolution.count ) )|wash}{else}&ndash;{/if}</dd>
        <dt>{'SLA: first response met'|i18n( $tsI )}</dt><dd data-ts-figure="sla_response">{if $response_pct|is_null|not}{$response_pct} % ({$tsF.sla.response_met} / {$tsF.sla.response_total}){elseif $sla_enabled|not}{'SLA timers are off.'|i18n( $tsI )}{else}&ndash;{/if}</dd>
        <dt>{'SLA: resolution met'|i18n( $tsI )}</dt><dd data-ts-figure="sla_resolution">{if $resolution_pct|is_null|not}{$resolution_pct} % ({$tsF.sla.resolution_met} / {$tsF.sla.resolution_total}){elseif $sla_enabled|not}{'SLA timers are off.'|i18n( $tsI )}{else}&ndash;{/if}</dd>
        <dt>{'Satisfaction'|i18n( $tsI )}</dt><dd data-ts-figure="csat" data-ts-source="{$csat_source|wash}">{if $tsF.csat.count}{'%avg of 5 from %count ratings'|i18n( $tsI,, hash( '%avg', $tsF.csat.avg, '%count', $tsF.csat.count ) )|wash}{if $tsF.csat.satisfied_pct|is_null|not}, {'%pct % satisfied'|i18n( $tsI,, hash( '%pct', $tsF.csat.satisfied_pct ) )|wash}{/if}{if $tsF.csat.response_rate|is_null|not}, {'%pct % of the rating mails answered'|i18n( $tsI,, hash( '%pct', $tsF.csat.response_rate ) )|wash}{/if}{elseif $csat_source|eq( 'off' )}{'Ratings are off.'|i18n( $tsI )}{else}{'No ratings in this period.'|i18n( $tsI )}{/if}</dd>
    </dl>
    {if $tsF.csat.count}
    <table class="ktl-table ts-table" id="ts-report-csat"><tbody>
    {foreach $csat_dist|reverse as $tsR}<tr><th scope="row">{$tsR.label} / 5</th><td><meter min="0" max="100" value="{$tsR.pct}"></meter></td><td class="ktl-num">{$tsR.count}</td></tr>{/foreach}
    </tbody></table>
    {/if}
</section>

{foreach array( hash( 'rows', $by_status, 'title', 'Opened in the period by status', 'id', 'ts-report-status' ),
                hash( 'rows', $by_category, 'title', 'Opened in the period by queue', 'id', 'ts-report-queue' ),
                hash( 'rows', $by_priority, 'title', 'Opened in the period by priority', 'id', 'ts-report-priority' ),
                hash( 'rows', $backlog_ages, 'title', 'Backlog by age', 'id', 'ts-report-backlog' ),
                hash( 'rows', $backlog_by_category, 'title', 'Backlog by queue', 'id', 'ts-report-backlog-queue' ),
                hash( 'rows', $backlog_by_priority, 'title', 'Backlog by priority', 'id', 'ts-report-backlog-priority' ) ) as $tsB}
<section class="ktl-card ts-card" aria-labelledby="{$tsB.id}-h" id="{$tsB.id}">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="{$tsB.id}-h">{$tsB.title|i18n( $tsI )|wash}</h2>
        {if $tsB.id|eq( 'ts-report-backlog' )}<span class="ktl-muted ts-muted">{'%count tickets not solved now'|i18n( $tsI,, hash( '%count', $backlog.total ) )|wash}{if $oldest}, {'the oldest open for %age'|i18n( $tsI,, hash( '%age', $oldest ) )|wash}{/if}</span>{/if}</div>
    {if $tsB.rows|count}
    <table class="ktl-table ts-table"><tbody>
    {foreach $tsB.rows as $tsR}<tr><th scope="row">{$tsR.label|i18n( $tsI )|wash}</th><td><meter min="0" max="100" value="{$tsR.pct}"></meter></td><td class="ktl-num">{$tsR.count}</td></tr>{/foreach}
    </tbody></table>
    {else}<p class="ktl-empty-line ts-empty">{'No tickets.'|i18n( $tsI )}</p>{/if}
</section>
{/foreach}

<section class="ktl-card ts-card" aria-labelledby="ts-r-work-h" id="ts-report-workload">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-r-work-h">{'Workload'|i18n( $tsI )}</h2><span class="ktl-muted ts-muted">{'Open tickets assigned now; replies in the period'|i18n( $tsI )}</span></div>
    {if $workload|count}
    <div class="ktl-table-wrap ts-table-wrap"><table class="ktl-table ts-table">
        <thead><tr><th scope="col">{'Staff member'|i18n( $tsI )}</th><th scope="col" class="ktl-num">{'Open tickets'|i18n( $tsI )}</th><th scope="col" class="ktl-num">{'Replies'|i18n( $tsI )}</th><th scope="col" class="ktl-num">{'Tickets answered'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $workload as $tsW}<tr data-ts-staff="{$tsW.id}"><th scope="row" data-label="{'Staff member'|i18n( $tsI )|wash}">{$tsW.name|wash}</th>
            <td class="ktl-num" data-label="{'Open tickets'|i18n( $tsI )|wash}">{$tsW.open}</td><td class="ktl-num" data-label="{'Replies'|i18n( $tsI )|wash}">{$tsW.replies}</td><td class="ktl-num" data-label="{'Tickets answered'|i18n( $tsI )|wash}">{$tsW.tickets}</td></tr>{/foreach}</tbody>
    </table></div>
    {else}<p class="ktl-empty-line ts-empty">{'No open tickets and no replies in this period.'|i18n( $tsI )}</p>{/if}
</section>
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI $tsF}
