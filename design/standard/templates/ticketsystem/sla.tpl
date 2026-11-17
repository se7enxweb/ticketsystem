{* ticketsystem/sla (doc/sla.md): the SLA board of staff. Input: $board (ticketsystemEscalation::board()), $state, $log
   (the latest escalation steps), $warn_percent, $steps, $clock, the frame's variables. The administration restyles it
   (design admin4). Mobile first; no inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1">{'SLA and escalation'|i18n( $tsI )}</h1>
        <p class="ktl-lede ts-lede">{if $clock.business}{'Targets count business hours (%hours, %zone).'|i18n( $tsI,, hash( '%hours', $clock.hours, '%zone', $clock.timezone ) )|wash}{else}{'Targets count around the clock.'|i18n( $tsI )}{/if}
            {'Due soon from %pct %.'|i18n( $tsI,, hash( '%pct', $warn_percent ) )|wash}</p>
    </div>
</div>
{if $board.enabled|not}
<section class="ktl-card ktl-empty ts-card ts-empty" id="ts-sla-off"><p>{'SLA timers are switched off ([SLASettings] Enabled).'|i18n( $tsI )}</p></section>
{else}
<nav class="ktl-tabs ts-tabs" aria-label="{'SLA state'|i18n( $tsI )|wash}" id="ts-sla-tabs">
    <a class="ktl-tab ts-tab{if $state|eq( '' )} is-current{/if}" href={'ticketsystem/sla'|ezurl}>{'All'|i18n( $tsI )} <span class="ktl-badge ts-badge">{sum( $board.counts.ok, $board.counts.warn, $board.counts.breached, $board.counts.paused )}</span></a>
    {foreach hash( 'breached', 'Breached', 'warn', 'Due soon', 'ok', 'On track', 'paused', 'Paused' ) as $tsK => $tsL}
    <a class="ktl-tab ts-tab{if $state|eq( $tsK )} is-current{/if}" href={concat( 'ticketsystem/sla/(state)/', $tsK )|ezurl} data-ts-count="{$board.counts[$tsK]}">{$tsL|i18n( $tsI )} <span class="ktl-badge ts-badge">{$board.counts[$tsK]}</span></a>
    {/foreach}
</nav>
{if $board.tickets|count|eq( 0 )}
<section class="ktl-card ktl-empty ts-card ts-empty" id="ts-sla-empty"><p>{'No ticket here.'|i18n( $tsI )}</p></section>
{else}
<section class="ktl-card ts-card" aria-labelledby="ts-sla-list-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-sla-list-h"><i class="fa-solid fa-stopwatch ktl-icon" aria-hidden="true"></i>{'Most urgent first'|i18n( $tsI )}</h2><span class="ktl-muted ts-muted">{'%count tickets'|i18n( $tsI,, hash( '%count', $board.total ) )|wash}</span></div>
    <div class="ktl-table-wrap ts-table-wrap">
        <table class="ktl-table ts-table" id="ts-sla-tickets">
            <thead><tr>
                <th scope="col">{'Ticket'|i18n( $tsI )}</th>
                <th scope="col">{'SLA'|i18n( $tsI )}</th>
                <th scope="col">{'Due'|i18n( $tsI )}</th>
                <th scope="col" class="ktl-col-wide">{'Priority'|i18n( $tsI )}</th>
                <th scope="col" class="ktl-col-wide">{'Assigned to'|i18n( $tsI )}</th>
            </tr></thead>
            <tbody>
            {foreach $board.tickets as $t}
                <tr data-ts-number="{$t.number|wash}" data-ts-sla="{$t.state|wash}">
                    <td data-label="{'Ticket'|i18n( $tsI )|wash}"><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}><strong>{$t.subject|wash}</strong></a><br><span class="ktl-muted ktl-small ts-muted ts-small">{$t.number|wash} &middot; {$t.level_label|wash}</span></td>
                    <td data-label="{'SLA'|i18n( $tsI )|wash}">{include uri='design:ticketsystem/parts/status.tpl' label=$t.label tone=$t.tone}{if $t.escalation|gt( 0 )} <span class="ktl-badge ts-badge ts-escalated">{'escalated'|i18n( $tsI )}</span>{/if}<br><span class="ktl-muted ktl-small ts-muted ts-small">{$t.target_label|i18n( $tsI )|wash} &middot; {$t.pct} %</span></td>
                    <td data-label="{'Due'|i18n( $tsI )|wash}" class="ktl-nowrap"><time datetime="{$t.due|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$t.due|l10n( 'shortdatetime' )}</time><br><span class="ktl-muted ktl-small ts-muted ts-small">{$t.text|i18n( $tsI )|wash}</span></td>
                    <td data-label="{'Priority'|i18n( $tsI )|wash}" class="ktl-col-wide">{$t.priority_label|i18n( $tsI )|wash}<br><span class="ktl-muted ktl-small ts-muted ts-small">{$t.category_label|i18n( $tsI )|wash}</span></td>
                    <td data-label="{'Assigned to'|i18n( $tsI )|wash}" class="ktl-col-wide">{if $t.assignee}{$t.assignee|wash}{else}<span class="ktl-muted ts-muted">{'nobody'|i18n( $tsI )}</span>{/if}</td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</section>
{/if}
<section class="ktl-card ts-card" aria-labelledby="ts-sla-log-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-sla-log-h"><i class="fa-solid fa-bell ktl-icon" aria-hidden="true"></i>{'Latest escalation steps'|i18n( $tsI )}</h2></div>
    {if $log|count}
    <ol class="ktl-rows ts-rows ts-escalations" id="ts-sla-log">
    {foreach $log as $tsE}
        <li><a href={concat( 'ticketsystem/view/', $tsE.number )|ezurl}>{$tsE.number|wash}</a> &middot; {$tsE.action|i18n( $tsI )|wash}{if $tsE.target|ne( 'manual' )} ({$tsE.target|i18n( $tsI )|wash}, {$tsE.percent} %){else} ({'by staff'|i18n( $tsI )}){/if}{if $tsE.recipients|gt( 0 )} &middot; {'%sent of %count mails'|i18n( $tsI,, hash( '%sent', $tsE.sent, '%count', $tsE.recipients ) )|wash}{/if} <span class="ktl-muted ktl-small ts-muted ts-small">{$tsE.created|l10n( 'shortdatetime' )}</span></li>
    {/foreach}
    </ol>
    {else}
    <p class="ktl-muted ts-muted">{'No escalation steps yet.'|i18n( $tsI )}</p>
    {/if}
    <p class="ktl-muted ktl-small ts-muted ts-small">{'Steps:'|i18n( $tsI )} {foreach $steps as $tsS sequence array( '', ', ' ) as $tsSep}{$tsSep}{$tsS.percent} % {$tsS.action|i18n( $tsI )|wash}{/foreach}</p>
</section>
{/if}
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
