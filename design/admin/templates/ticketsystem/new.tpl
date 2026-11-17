{* ticketsystem/new in the administration (design admin4, doc/views.md): the form of a new ticket. Same input as
   design:ticketsystem/new.tpl of the standard design; only shared admin components (.a4-*), no inline CSS. *}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='New ticket'|i18n( 'extension/ticketsystem' )
         sub='The opener gets a copy of the message and of every reply by e-mail.'|i18n( 'extension/ticketsystem' )}

<section class="a4-card" aria-labelledby="ts-a4-new-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-new-h">{'The request'|i18n( 'extension/ticketsystem' )}</h2>
        <div class="a4-card-actions"><a class="a4-btn a4-btn-small a4-btn-ghost" href={'ticketsystem/list'|ezurl}>{'All tickets'|i18n( 'extension/ticketsystem' )}</a></div></div>
    <div class="a4-card-body">
    <form class="a4-form is-wide" method="post" action={'ticketsystem/new'|ezurl} id="ts-new-form" enctype="multipart/form-data">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if and( is_set( $is_staff ), $is_staff )}
        {* 1.2: staff open a ticket for a customer (doc/staff-opened-tickets.md) *}
        <div class="a4-stack" id="ts-for" role="group" aria-labelledby="ts-for-h">
            <h3 class="a4-h3" id="ts-for-h">{'For a customer'|i18n( 'extension/ticketsystem' )}</h3>
            {if $for.customer|gt( 0 )}
            <input type="hidden" name="TicketForCustomer" value="{$for.customer}" />
            <p class="a4-meta" id="ts-for-label">{'The ticket is opened for: %customer'|i18n( 'extension/ticketsystem',, hash( '%customer', $for.label ) )|wash}</p>
            {else}
            <div class="a4-field a4-field-wide">
                <label for="ts-for-user">{'Customer (user id or login)'|i18n( 'extension/ticketsystem' )}</label>
                <input class="a4-input" type="text" id="ts-for-user" name="TicketForUser" value="{$for.user|wash}" maxlength="150" autocomplete="off"{if is_set( $field_errors.customer )} aria-invalid="true" aria-describedby="ts-for-error"{else} aria-describedby="ts-for-help"{/if} />
                <p class="a4-help" id="ts-for-help">{'Leave it empty to open the ticket for yourself. The customer becomes the opener: the ticket is in their list, and you write the opening post.'|i18n( 'extension/ticketsystem' )}</p>
            </div>
            {/if}
            {if is_set( $field_errors.customer )}<p class="a4-field-error" id="ts-for-error">{$field_errors.customer|i18n( 'extension/ticketsystem' )|wash}</p>{/if}
            <label class="a4-check"><input type="hidden" name="TicketNotify" value="0" /><input type="checkbox" id="ts-for-notify" name="TicketNotify" value="1"{if $for.notify} checked="checked"{/if} /> {'Mail the opening post to the customer'|i18n( 'extension/ticketsystem' )}</label>
        </div>
        {/if}
        <div class="a4-field a4-field-wide">
            <label for="ts-subject">{'Subject'|i18n( 'extension/ticketsystem' )}</label>
            <input class="a4-input" type="text" id="ts-subject" name="TicketSubject" value="{$values.subject|wash}" maxlength="200" required="required" autocomplete="off"{if is_set( $field_errors.subject )} aria-invalid="true" aria-describedby="ts-subject-error"{/if} />
            {if is_set( $field_errors.subject )}<p class="a4-field-error" id="ts-subject-error">{$field_errors.subject|i18n( 'extension/ticketsystem' )|wash}</p>{/if}
        </div>
        <div class="a4-grid is-narrow">
            <div class="a4-field">
                <label for="ts-category">{'Queue'|i18n( 'extension/ticketsystem' )}</label>
                <select class="a4-input" id="ts-category" name="TicketCategory">
                {foreach $categories as $id => $label}<option value="{$id|wash}"{if $values.category|eq( $id )} selected="selected"{/if}>{$label|i18n( 'extension/ticketsystem' )|wash}</option>{/foreach}
                </select>
            </div>
            <div class="a4-field">
                <label for="ts-priority">{'Priority'|i18n( 'extension/ticketsystem' )}</label>
                <select class="a4-input" id="ts-priority" name="TicketPriority">
                {foreach $priorities as $id => $label}<option value="{$id|wash}"{if $values.priority|eq( $id )} selected="selected"{/if}>{$label|i18n( 'extension/ticketsystem' )|wash}</option>{/foreach}
                </select>
            </div>
            {if $sites|count}
            <div class="a4-field">
                <label for="ts-site">{'Site'|i18n( 'extension/ticketsystem' )}</label>
                <select class="a4-input" id="ts-site" name="TicketSubscription">
                    <option value="0">{'No particular site'|i18n( 'extension/ticketsystem' )}</option>
                    {foreach $sites as $s}<option value="{$s.id}"{if $values.subscription_id|eq( $s.id )} selected="selected"{/if}>{$s.label|wash}</option>{/foreach}
                </select>
            </div>
            {/if}
        </div>
        <div class="a4-field a4-field-wide">
            <label for="ts-body">{'Message'|i18n( 'extension/ticketsystem' )}</label>
            <textarea class="a4-input" id="ts-body" name="TicketBody" rows="10" required="required"{if is_set( $field_errors.body )} aria-invalid="true" aria-describedby="ts-body-error"{/if}>{$values.body|wash}</textarea>
            {if is_set( $field_errors.body )}<p class="a4-field-error" id="ts-body-error">{$field_errors.body|i18n( 'extension/ticketsystem' )|wash}</p>{/if}
            <p class="a4-help">{'Never send passwords in a ticket.'|i18n( 'extension/ticketsystem' )}</p>
        </div>
        {if and( is_set( $attach ), $attach.enabled )}
        <div class="a4-field a4-field-wide">
            <label for="ts-files">{'Attachments'|i18n( 'extension/ticketsystem' )}</label>
            <input class="a4-input" type="file" id="ts-files" name="TicketFiles[]" multiple="multiple" accept="{$attach.accept|wash}" />
            <p class="a4-help">{'Up to %count files, at most %size MB each: %types.'|i18n( 'extension/ticketsystem',, hash( '%count', $attach.max_files, '%size', $attach.max_mb, '%types', $attach.types ) )|wash}</p>
        </div>
        {/if}
        <div class="a4-actions">
            <button type="submit" class="a4-btn a4-btn-primary" name="TicketCreate" value="1" id="ts-create">{'Open the ticket'|i18n( 'extension/ticketsystem' )}</button>
            <a class="a4-btn a4-btn-ghost" href={'ticketsystem/list'|ezurl}>{'Cancel'|i18n( 'extension/ticketsystem' )}</a>
        </div>
    </form>
    </div>
</section>

{include uri='design:ticketsystem/parts/admin_close.tpl'}
