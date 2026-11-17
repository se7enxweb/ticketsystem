{* ticketsystem/new (doc/views.md): the form of a new ticket.
   Input: $values (subject, body, category, priority, subscription_id), $field_errors, $categories, $priorities,
   $sites (Katalyst: id, label), $ts_token, $ts_token_field, the frame's variables. No inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}

<p class="ktl-crumbs ts-crumbs"><a href={'ticketsystem/list'|ezurl}><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {'All tickets'|i18n( $tsI )}</a></p>
<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1">{'New ticket'|i18n( $tsI )}</h1>
        <p class="ktl-lede ts-lede">{'Tell us what you need. You get a copy of your message and of every reply by e-mail.'|i18n( $tsI )}</p>
    </div>
</div>

<section class="ktl-card ts-card" aria-labelledby="ts-new-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-new-h"><i class="fa-solid fa-pen ktl-icon" aria-hidden="true"></i>{'Your request'|i18n( $tsI )}</h2></div>
    <form class="ktl-form ts-form" method="post" action={'ticketsystem/new'|ezurl} id="ts-new-form" enctype="multipart/form-data">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if and( is_set( $is_staff ), $is_staff )}
        {* 1.2: staff open a ticket for a customer (doc/staff-opened-tickets.md) *}
        <div class="ktl-field ts-field" id="ts-for">
            {if $for.customer|gt( 0 )}
            <input type="hidden" name="TicketForCustomer" value="{$for.customer}" />
            <p id="ts-for-label">{'The ticket is opened for: %customer'|i18n( $tsI,, hash( '%customer', $for.label ) )|wash}</p>
            {else}
            <label for="ts-for-user">{'For a customer (user id or login)'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="text" id="ts-for-user" name="TicketForUser" value="{$for.user|wash}" maxlength="150" autocomplete="off" aria-describedby="ts-for-help" />
            <p class="ts-small ts-muted" id="ts-for-help">{'Leave it empty to open the ticket for yourself.'|i18n( $tsI )}</p>
            {/if}
            {if is_set( $field_errors.customer )}<p class="ktl-field-error ts-field-error" id="ts-for-error">{$field_errors.customer|i18n( $tsI )|wash}</p>{/if}
            <label><input type="hidden" name="TicketNotify" value="0" /><input type="checkbox" id="ts-for-notify" name="TicketNotify" value="1"{if $for.notify} checked="checked"{/if} /> {'Mail the opening post to the customer'|i18n( $tsI )}</label>
        </div>
        {/if}
        <div class="ktl-field ts-field">
            <label for="ts-subject">{'Subject'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input{if is_set( $field_errors.subject )} is-invalid{/if}" type="text" id="ts-subject" name="TicketSubject" value="{$values.subject|wash}" maxlength="200" required autocomplete="off"{if is_set( $field_errors.subject )} aria-invalid="true" aria-describedby="ts-subject-error"{/if} />
            {if is_set( $field_errors.subject )}<p class="ktl-field-error ts-field-error" id="ts-subject-error">{$field_errors.subject|i18n( $tsI )|wash}</p>{/if}
        </div>
        <div class="ktl-fields-2 ts-fields-2">
            <div class="ktl-field ts-field">
                <label for="ts-category">{'Category'|i18n( $tsI )}</label>
                <select class="ktl-input ts-input" id="ts-category" name="TicketCategory">
                {foreach $categories as $id => $label}<option value="{$id|wash}"{if $values.category|eq( $id )} selected="selected"{/if}>{$label|i18n( $tsI )|wash}</option>{/foreach}
                </select>
            </div>
            <div class="ktl-field ts-field">
                <label for="ts-priority">{'Priority'|i18n( $tsI )}</label>
                <select class="ktl-input ts-input" id="ts-priority" name="TicketPriority">
                {foreach $priorities as $id => $label}<option value="{$id|wash}"{if $values.priority|eq( $id )} selected="selected"{/if}>{$label|i18n( $tsI )|wash}</option>{/foreach}
                </select>
            </div>
        </div>
        {if $sites|count}
        <div class="ktl-field ts-field">
            <label for="ts-site">{'Which site is it about?'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-site" name="TicketSubscription">
                <option value="0">{'No particular site'|i18n( $tsI )}</option>
                {foreach $sites as $s}<option value="{$s.id}"{if $values.subscription_id|eq( $s.id )} selected="selected"{/if}>{$s.label|wash}</option>{/foreach}
            </select>
        </div>
        {/if}
        <div class="ktl-field ts-field">
            <label for="ts-body">{'Message'|i18n( $tsI )}</label>
            <textarea class="ktl-input ts-input ts-textarea{if is_set( $field_errors.body )} is-invalid{/if}" id="ts-body" name="TicketBody" rows="8" required{if is_set( $field_errors.body )} aria-invalid="true" aria-describedby="ts-body-error"{/if}>{$values.body|wash}</textarea>
            {if is_set( $field_errors.body )}<p class="ktl-field-error ts-field-error" id="ts-body-error">{$field_errors.body|i18n( $tsI )|wash}</p>{/if}
            <p class="ktl-muted ktl-small ts-muted ts-small">{'Please never send passwords here. If we need access, we tell you how to share it safely.'|i18n( $tsI )}</p>
        </div>
        {if $attach.enabled}
        <div class="ktl-field ts-field">
            <label for="ts-files">{'Attachments'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="file" id="ts-files" name="TicketFiles[]" multiple="multiple" accept="{$attach.accept|wash}" />
            <p class="ktl-muted ktl-small ts-muted ts-small">{'Up to %count files, at most %size MB each: %types.'|i18n( $tsI,, hash( '%count', $attach.max_files, '%size', $attach.max_mb, '%types', $attach.types ) )|wash}</p>
        </div>
        {/if}
        <p class="ktl-actions ts-actions">
            <button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="TicketCreate" value="1" id="ts-create">{'Open the ticket'|i18n( $tsI )}</button>
            <a class="ktl-btn is-ghost ts-btn is-ghost" href={'ticketsystem/list'|ezurl}>{'Cancel'|i18n( $tsI )}</a>
        </p>
    </form>
</section>

{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
