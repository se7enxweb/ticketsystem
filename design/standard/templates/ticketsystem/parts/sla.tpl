{* The SLA card of a ticket for staff (doc/sla.md): the level, both targets with their state and due time, the escalation
   steps that ran. Input: $sla (ticketsystemService::slaState(), or null), $ticket. No inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
<section class="ktl-card ts-card ts-sla{if $ticket.escalation|ge( 2 )} is-bad{elseif $ticket.escalation|ge( 1 )} is-warn{/if}" aria-labelledby="ts-sla-h" id="ts-sla">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-sla-h"><i class="fa-solid fa-stopwatch ktl-icon" aria-hidden="true"></i>{'SLA'|i18n( $tsI )}</h2>{if $sla}<span class="ktl-muted ts-muted">{$sla.level_label|wash}</span>{/if}</div>
    {if $sla}
    <dl class="ktl-facts is-compact ts-facts">
    {foreach hash( 'response', 'First response', 'resolution', 'Resolution' ) as $tsK => $tsL}
        <dt>{$tsL|i18n( $tsI )}</dt>
        <dd id="ts-sla-{$tsK}">{if $sla[$tsK].state|eq( 'none' )}<span class="ktl-muted ts-muted">{'no target'|i18n( $tsI )}</span>{else}
            {include uri='design:ticketsystem/parts/status.tpl' label=$sla[$tsK].label tone=cond( $sla[$tsK].state|eq( 'breached' ), 'bad', $sla[$tsK].state|eq( 'warn' ), 'warn', $sla[$tsK].state|eq( 'paused' ), 'muted', 'ok' )}
            <span class="ktl-muted ktl-small ts-muted ts-small"><time datetime="{$sla[$tsK].due|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$sla[$tsK].due|l10n( 'shortdatetime' )}</time>{if and( $sla[$tsK].state|ne( 'met' ), $sla[$tsK].text )} &middot; {$sla[$tsK].text|i18n( $tsI )|wash}{/if}</span>{/if}</dd>
    {/foreach}
        <dt>{'Escalation'|i18n( $tsI )}</dt>
        <dd id="ts-sla-escalation">{if $ticket.escalation|ge( 2 )}{'level 2: breached'|i18n( $tsI )}{elseif $ticket.escalation|ge( 1 )}{'level 1: due soon'|i18n( $tsI )}{else}{'none'|i18n( $tsI )}{/if}{if $sla.escalated} &middot; {'marked escalated %date'|i18n( $tsI,, hash( '%date', $sla.escalated|l10n( 'shortdatetime' ) ) )|wash}{/if}</dd>
    </dl>
    {if $sla.log|count}
    <ol class="ktl-rows ts-rows ts-escalations" id="ts-sla-log">
    {foreach $sla.log as $tsE}
        <li><span class="ktl-muted ktl-small ts-muted ts-small">{$tsE.created|l10n( 'shortdatetime' )}</span> {$tsE.action|i18n( $tsI )|wash}{if $tsE.target|ne( 'manual' )} &middot; {$tsE.target|i18n( $tsI )|wash} {$tsE.percent} %{else} &middot; {'by staff'|i18n( $tsI )}{/if}{if $tsE.recipients|gt( 0 )} &middot; {'%sent of %count mails'|i18n( $tsI,, hash( '%sent', $tsE.sent, '%count', $tsE.recipients ) )|wash}{/if}</li>
    {/foreach}
    </ol>
    {/if}
    {else}
    <p class="ktl-muted ts-muted">{'This ticket has no SLA target.'|i18n( $tsI )}</p>
    {/if}
</section>
{undef $tsI}
