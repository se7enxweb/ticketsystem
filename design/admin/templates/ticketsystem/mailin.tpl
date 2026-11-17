{* ticketsystem/mailin in the administration (doc/mail-in.md, doc/views.md): reply by e-mail for staff: the settings in
   use and the latest inbound mails with their result (time, result, ticket; never an address or a text). Input:
   $enabled, $poll, $protocol, $require_token, $open_new, $refused_action, $reply_address, $rows (created, result,
   number). Only the shared admin components (.a4-*). *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Reply by e-mail'|i18n( $tsI )
         sub='Replies to ticket mails become posts on their ticket, as the customer or staff member who sent them.'|i18n( $tsI )}

<section class="a4-card" aria-labelledby="ts-mailin-state-h" id="ts-mailin-state">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-mailin-state-h">{'Settings in use'|i18n( $tsI )}</h2>
        {if $enabled}<span class="a4-badge is-ok" data-state="enabled">{'on'|i18n( $tsI )}</span>{else}<span class="a4-badge" data-state="enabled">{'off'|i18n( $tsI )}</span>{/if}</div>
    <dl class="a4-facts is-rows">
        <div><dt>{'Receives mail'|i18n( $tsI )}</dt><dd>{if $poll}{'from the mailbox'|i18n( $tsI )} ({$protocol|wash}){else}{'through the pipe script only'|i18n( $tsI )}{/if}</dd></div>
        <div><dt>{'Ticket found by'|i18n( $tsI )}</dt><dd>{if $require_token}{'the signed reference only'|i18n( $tsI )}{else}{'the signed reference or the number in the subject'|i18n( $tsI )}{/if}</dd></div>
        <div><dt>{'Reference in the reply address'|i18n( $tsI )}</dt><dd>{if $reply_address}{'yes'|i18n( $tsI )}{else}{'no'|i18n( $tsI )}{/if}</dd></div>
        <div><dt>{'New tickets by mail'|i18n( $tsI )}</dt><dd>{if $open_new}{'on'|i18n( $tsI )}{else}{'off'|i18n( $tsI )}{/if}</dd></div>
        <div><dt>{'Refused mail'|i18n( $tsI )}</dt><dd>{$refused_action|wash}</dd></div>
    </dl>
</section>

<section class="a4-card" aria-labelledby="ts-mailin-log-h" id="ts-mailin-log">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-mailin-log-h">{'Latest inbound mail'|i18n( $tsI )}</h2></div>
    {if $rows|count}
    <div class="a4-table-wrap"><table class="a4-table is-compact">
        <thead><tr><th scope="col">{'Received'|i18n( $tsI )}</th><th scope="col">{'Result'|i18n( $tsI )}</th><th scope="col">{'Ticket'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $rows as $tsR}<tr><td class="a4-nowrap">{$tsR.created|l10n( 'shortdatetime' )}</td><td>{include uri='design:ticketsystem/parts/status.tpl' label=$tsR.result tone=cond( $tsR.number, 'ok', 'warn' )}</td><td>{if $tsR.number}<a href={concat( 'ticketsystem/view/', $tsR.number )|ezurl}>{$tsR.number|wash}</a>{else}<span class="a4-muted">&ndash;</span>{/if}</td></tr>{/foreach}</tbody>
    </table></div>
    {else}
    <p class="a4-empty">{'No mail was received yet.'|i18n( $tsI )}</p>
    {/if}
</section>

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
