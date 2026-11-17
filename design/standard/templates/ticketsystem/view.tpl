{* ticketsystem/view/<number> (doc/views.md): one ticket, its posts oldest first, the reply form and the status actions.
   Input: $ticket (ticketsystemView::row), $posts (ticketsystemService::posts), $may_reply, $may_resolve, $may_reopen,
   $is_staff, $staff_choices, $staff_default, $draft, $field_errors, $following, the form token, the frame's
   variables. No inline CSS. *}
{def $tsI = 'extension/ticketsystem'
     $last = $posts|count|dec}
{include uri='design:ticketsystem/parts/open.tpl'}

<p class="ktl-crumbs ts-crumbs"><a href={'ticketsystem/list'|ezurl}><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {'All tickets'|i18n( $tsI )}</a></p>
<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1" id="ts-subject">{$ticket.subject|wash}</h1>
        <p class="ktl-lede ts-lede"><span class="ktl-nowrap" id="ts-number">{$ticket.number|wash}</span> &middot; {'opened %date'|i18n( $tsI,, hash( '%date', $ticket.created|l10n( 'shortdatetime' ) ) )|wash}</p>
    </div>
    <p class="ktl-actions ts-actions" id="ts-status">{include uri='design:ticketsystem/parts/status.tpl' label=$ticket.status_label tone=$ticket.status_tone}</p>
</div>

<div class="ktl-grid is-2-1 ts-grid">
<div class="ts-thread">
<section class="ktl-card ts-card" aria-labelledby="ts-thread-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-thread-h"><i class="fa-solid fa-comments ktl-icon" aria-hidden="true"></i>{'Conversation'|i18n( $tsI )}</h2><span class="ktl-muted ts-muted">{'%count posts'|i18n( $tsI,, hash( '%count', $posts|count ) )|wash}</span></div>
    <ol class="ktl-rows ts-posts">
    {foreach $posts as $i => $p}
        <li class="ts-post{if $p.is_staff} is-staff{/if}{if $p.is_self} is-self{/if}" id="{if $i|eq( $last )}ts-latest{else}ts-post-{$p.id}{/if}">
            <p class="ts-post-head"><strong>{if $p.is_self}{'You'|i18n( $tsI )}{else}{$p.author|wash}{/if}</strong>
                {if $p.is_staff} <span class="ktl-badge ts-badge">{'Support'|i18n( $tsI )}</span>{/if}
                <span class="ktl-muted ktl-small ts-muted ts-small"> &middot; <time datetime="{$p.created|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$p.created|l10n( 'shortdatetime' )}</time></span></p>
            <div class="ts-post-body">{if $p.attribute}{attribute_view_gui attribute=$p.attribute}{/if}</div>
        </li>
    {/foreach}
    </ol>
</section>

{if $may_reply}
<section class="ktl-card ts-card" aria-labelledby="ts-reply-h" id="ts-reply">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-reply-h"><i class="fa-solid fa-reply ktl-icon" aria-hidden="true"></i>{'Reply'|i18n( $tsI )}</h2></div>
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-reply-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <div class="ktl-field ts-field">
            <label for="ts-body">{'Your reply'|i18n( $tsI )}</label>
            <textarea class="ktl-input ts-input ts-textarea{if is_set( $field_errors.body )} is-invalid{/if}" id="ts-body" name="TicketBody" rows="6" required{if is_set( $field_errors.body )} aria-invalid="true" aria-describedby="ts-body-error"{/if}>{$draft|wash}</textarea>
            {if is_set( $field_errors.body )}<p class="ktl-field-error ts-field-error" id="ts-body-error">{$field_errors.body|i18n( $tsI )|wash}</p>{/if}
        </div>
        {if $is_staff}
        <div class="ktl-field ts-field">
            <label for="ts-reply-status">{'Status after this reply'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-reply-status" name="TicketStatus">
            {foreach $staff_choices as $c}<option value="{$c.id|wash}"{if $c.id|eq( $staff_default )} selected="selected"{/if}>{$c.label|i18n( $tsI )|wash}</option>{/foreach}
            </select>
        </div>
        {/if}
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="TicketReply" value="1" id="ts-send">{'Send the reply'|i18n( $tsI )}</button></p>
    </form>
</section>
{else}
<section class="ktl-card ktl-empty is-inline ts-card ts-empty" id="ts-closed">
    <p class="ktl-empty-line">{'This ticket is closed. If you need more help, open a new ticket and mention %number.'|i18n( $tsI,, hash( '%number', $ticket.number ) )|wash}</p>
    <p class="ktl-actions ts-actions"><a class="ktl-btn ts-btn" href={'ticketsystem/new'|ezurl}>{'Open a new ticket'|i18n( $tsI )}</a></p>
</section>
{/if}
</div>

<div class="ts-side">
<section class="ktl-card ts-card" aria-labelledby="ts-facts-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-facts-h"><i class="fa-solid fa-circle-info ktl-icon" aria-hidden="true"></i>{'Details'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts is-compact ts-facts">
        <dt>{'Ticket'|i18n( $tsI )}</dt><dd>{$ticket.number|wash}</dd>
        <dt>{'Status'|i18n( $tsI )}</dt><dd>{$ticket.status_label|i18n( $tsI )|wash}</dd>
        {if $ticket.category_label}<dt>{'Category'|i18n( $tsI )}</dt><dd>{$ticket.category_label|i18n( $tsI )|wash}</dd>{/if}
        {if $ticket.priority_label}<dt>{'Priority'|i18n( $tsI )}</dt><dd>{$ticket.priority_label|i18n( $tsI )|wash}</dd>{/if}
        {if $is_staff}<dt>{'Account'|i18n( $tsI )}</dt><dd>{$ticket.account_label|wash}</dd>{/if}
        <dt>{'Last update'|i18n( $tsI )}</dt><dd>{$ticket.modified|l10n( 'shortdatetime' )}</dd>
        <dt>{'E-mail copies'|i18n( $tsI )}</dt><dd>{if $following}{'You get every post by e-mail.'|i18n( $tsI )}{else}{'You do not follow this ticket.'|i18n( $tsI )}{/if}</dd>
    </dl>
    {if or( $may_resolve, $may_reopen, $is_staff )}
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-status-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if $is_staff}
        <div class="ktl-field ts-field">
            <label for="ts-set-status">{'Change the status'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-set-status" name="TicketStatus">
            {foreach $staff_choices as $c}<option value="{$c.id|wash}"{if $c.id|eq( $ticket.status )} selected="selected"{/if}>{$c.label|i18n( $tsI )|wash}</option>{/foreach}
            </select>
        </div>
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketSetStatus" value="1" id="ts-set">{'Save the status'|i18n( $tsI )}</button></p>
        {elseif $may_resolve}
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketResolve" value="1" id="ts-resolve"><i class="fa-solid fa-check" aria-hidden="true"></i> {'Mark as solved'|i18n( $tsI )}</button></p>
        {elseif $may_reopen}
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketReopen" value="1" id="ts-reopen"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> {'Reopen the ticket'|i18n( $tsI )}</button></p>
        {/if}
    </form>
    {/if}
</section>
</div>
</div>

{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI $last}
