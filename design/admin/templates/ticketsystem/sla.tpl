{* ticketsystem/sla in the administration (doc/sla.md, doc/views.md): the SLA board of staff, most urgent first. Input:
   $board (ticketsystemEscalation::board(): enabled, counts, total, tickets), $state (the view parameter (state)), $log
   (the latest escalation steps), $warn_percent, $steps, $clock. Only the shared admin components (.a4-*). *}
{def $tsI = 'extension/ticketsystem'
     $tsSub = cond( $clock.business, 'Targets count business hours (%hours, %zone).'|i18n( $tsI,, hash( '%hours', $clock.hours, '%zone', $clock.timezone ) ),
                    'Targets count around the clock.'|i18n( $tsI ) )}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='SLA and escalation'|i18n( $tsI )
         sub=concat( $tsSub, ' ', 'Due soon from %pct %.'|i18n( $tsI,, hash( '%pct', $warn_percent ) ) )}

{if $board.enabled|not}
<p class="a4-notice is-info" id="ts-sla-off">{'SLA timers are switched off ([SLASettings] Enabled).'|i18n( $tsI )}</p>
{else}
<ul class="a4-figures" id="ts-sla-tabs" aria-label="{'SLA state'|i18n( $tsI )|wash}">
    <li class="a4-figure{if $state|eq( '' )} is-current{/if}"><a href={'ticketsystem/sla'|ezurl}{if $state|eq( '' )} aria-current="page"{/if}><strong>{sum( $board.counts.ok, $board.counts.warn, $board.counts.breached, $board.counts.paused )}</strong> <span>{'all'|i18n( $tsI )}</span></a></li>
    {foreach hash( 'breached', 'breached', 'warn', 'due soon', 'ok', 'on track', 'paused', 'paused' ) as $tsK => $tsL}
    <li class="a4-figure{if and( $tsK|eq( 'breached' ), $board.counts[$tsK]|gt( 0 ) )} is-bad{elseif $tsK|eq( 'ok' )} is-ok{/if}{if $state|eq( $tsK )} is-current{/if}" data-ts-count="{$board.counts[$tsK]}"><a href={concat( 'ticketsystem/sla/(state)/', $tsK )|ezurl}{if $state|eq( $tsK )} aria-current="page"{/if}><strong>{$board.counts[$tsK]}</strong> <span>{$tsL|i18n( $tsI )}</span></a></li>
    {/foreach}
</ul>

{if $board.tickets|count|eq( 0 )}
<p class="a4-empty" id="ts-sla-empty">{'No ticket here.'|i18n( $tsI )}</p>
{else}
<div class="a4-table-wrap"><table class="a4-table" id="ts-sla-tickets">
    <caption class="a4-sr-only">{'Most urgent first'|i18n( $tsI )}</caption>
    <thead><tr>
        <th scope="col">{'Number'|i18n( $tsI )}</th>
        <th scope="col">{'Subject'|i18n( $tsI )}</th>
        <th scope="col">{'SLA'|i18n( $tsI )}</th>
        <th scope="col">{'Due'|i18n( $tsI )}</th>
        <th scope="col">{'Priority'|i18n( $tsI )}</th>
        <th scope="col">{'Assigned to'|i18n( $tsI )}</th>
    </tr></thead>
    <tbody>
    {foreach $board.tickets as $t}
    <tr data-ts-number="{$t.number|wash}" data-ts-sla="{$t.state|wash}">
        <td class="a4-nowrap"><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.number|wash}</a></td>
        <td><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.subject|wash}</a> <span class="a4-meta">{$t.level_label|wash}</span></td>
        <td>{include uri='design:ticketsystem/parts/status.tpl' label=$t.label tone=$t.tone}{if $t.escalation|gt( 0 )} <span class="a4-badge is-bad">{'escalated'|i18n( $tsI )}</span>{/if}
            <span class="a4-meta">{$t.target_label|i18n( $tsI )|wash} &middot; {$t.pct} %</span></td>
        <td class="a4-nowrap"><time datetime="{$t.due|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$t.due|l10n( 'shortdatetime' )}</time> <span class="a4-meta">{$t.text|i18n( $tsI )|wash}</span></td>
        <td>{$t.priority_label|i18n( $tsI )|wash} <span class="a4-meta">{$t.category_label|i18n( $tsI )|wash}</span></td>
        <td>{if $t.assignee}{$t.assignee|wash}{else}<span class="a4-muted">{'nobody'|i18n( $tsI )}</span>{/if}</td>
    </tr>
    {/foreach}
    </tbody>
</table></div>
<p class="a4-meta">{'%count tickets'|i18n( $tsI,, hash( '%count', $board.total ) )|wash}</p>
{/if}

<section class="a4-card" aria-labelledby="ts-sla-log-h" id="ts-sla-log-card">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-sla-log-h">{'Latest escalation steps'|i18n( $tsI )}</h2>
        <p class="a4-meta">{'Steps:'|i18n( $tsI )} {foreach $steps as $tsS}{delimiter}, {/delimiter}{$tsS.percent} % {$tsS.action|i18n( $tsI )|wash}{/foreach}</p></div>
    <div class="a4-card-body">
    {if $log|count}
    <ol class="a4-timeline" id="ts-sla-log">
    {foreach $log as $tsE}
        <li class="{if $tsE.target|eq( 'manual' )}is-warn{else}is-bad{/if}">
            <time class="a4-timeline-time" datetime="{$tsE.created|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$tsE.created|l10n( 'shortdatetime' )}</time>
            <div class="a4-timeline-body"><a href={concat( 'ticketsystem/view/', $tsE.number )|ezurl}>{$tsE.number|wash}</a> &middot; {$tsE.action|i18n( $tsI )|wash}
                {if $tsE.target|ne( 'manual' )}({$tsE.target|i18n( $tsI )|wash}, {$tsE.percent} %){else}({'by staff'|i18n( $tsI )}){/if}
                {if $tsE.recipients|gt( 0 )}<span class="a4-meta">{'%sent of %count mails'|i18n( $tsI,, hash( '%sent', $tsE.sent, '%count', $tsE.recipients ) )|wash}</span>{/if}</div>
        </li>
    {/foreach}
    </ol>
    {else}
    <p class="a4-empty">{'No escalation steps yet.'|i18n( $tsI )}</p>
    {/if}
    </div>
</section>
{/if}

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI $tsSub}
