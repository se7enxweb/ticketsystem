{* ticketsystem/mailin (doc/mail-in.md): reply by e-mail for staff: the settings in use and the latest inbound
   mails with their result (time, result, ticket; never an address or a text). A plain standard page the
   administration designs restyle.
   Input: $enabled, $poll, $protocol, $require_token, $open_new, $refused_action, $reply_address, $rows (created,
   result, number), the frame's variables. No inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}

<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1">{'Reply by e-mail'|i18n( $tsI )}</h1>
        <p class="ktl-lede ts-lede">{'Replies to ticket mails become posts on their ticket, as the customer or staff member who sent them.'|i18n( $tsI )}</p>
    </div>
</div>

<section class="ktl-card ts-card" aria-labelledby="ts-mailin-state-h" id="ts-mailin-state">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-mailin-state-h">{'Settings'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts ts-facts">
        <dt>{'Reply by e-mail'|i18n( $tsI )}</dt><dd data-state="enabled">{if $enabled}{'On'|i18n( $tsI )}{else}{'Off'|i18n( $tsI )}{/if}</dd>
        <dt>{'Receives mail'|i18n( $tsI )}</dt><dd>{if $poll}{'from the mailbox'|i18n( $tsI )} ({$protocol|wash}){else}{'through the pipe script only'|i18n( $tsI )}{/if}</dd>
        <dt>{'Ticket found by'|i18n( $tsI )}</dt><dd>{if $require_token}{'the signed reference only'|i18n( $tsI )}{else}{'the signed reference or the number in the subject'|i18n( $tsI )}{/if}</dd>
        <dt>{'Reference in the reply address'|i18n( $tsI )}</dt><dd>{if $reply_address}{'Yes'|i18n( $tsI )}{else}{'No'|i18n( $tsI )}{/if}</dd>
        <dt>{'New tickets by mail'|i18n( $tsI )}</dt><dd>{if $open_new}{'On'|i18n( $tsI )}{else}{'Off'|i18n( $tsI )}{/if}</dd>
        <dt>{'Refused mail'|i18n( $tsI )}</dt><dd>{$refused_action|wash}</dd>
    </dl>
</section>

<section class="ktl-card ts-card" aria-labelledby="ts-mailin-log-h" id="ts-mailin-log">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-mailin-log-h">{'Latest inbound mail'|i18n( $tsI )}</h2></div>
    {if $rows|count}
    <div class="ktl-table-wrap ts-table-wrap">
        <table class="ktl-table ts-table">
            <thead><tr><th scope="col">{'Received'|i18n( $tsI )}</th><th scope="col">{'Result'|i18n( $tsI )}</th><th scope="col">{'Ticket'|i18n( $tsI )}</th></tr></thead>
            <tbody>
            {foreach $rows as $r}<tr><td>{$r.created|l10n( 'shortdatetime' )}</td><td>{$r.result|wash}</td><td>{if $r.number}<a href={concat( 'ticketsystem/view/', $r.number )|ezurl}>{$r.number|wash}</a>{/if}</td></tr>{/foreach}
            </tbody>
        </table>
    </div>
    {else}
    <p class="ts-muted">{'No mail was received yet.'|i18n( $tsI )}</p>
    {/if}
</section>

{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
