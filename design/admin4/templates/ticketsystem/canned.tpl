{* ticketsystem/canned in the administration (doc/canned.md): the canned replies and macros, and the form of one.
   Input: $canned, $edit, $values, $field_errors, $categories, $priorities, $status_choices, $placeholders. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Canned replies'|i18n( $tsI ) sub='Texts to answer with, and macros that also change the ticket.'|i18n( $tsI )}

{if $edit}
<section class="a4-card" aria-labelledby="ts-canned-form-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-canned-form-h">{if $values.id}{'Edit the canned reply'|i18n( $tsI )}{else}{'New canned reply'|i18n( $tsI )}{/if}</h2>
        <div class="a4-card-actions"><a class="a4-btn a4-btn-small a4-btn-ghost" href={'ticketsystem/canned'|ezurl}>{'Back to the list'|i18n( $tsI )}</a></div></div>
    <div class="a4-card-body">
    <form class="a4-form is-wide" method="post" action={'ticketsystem/canned'|ezurl} id="ts-canned-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <input type="hidden" name="CannedID" value="{$values.id}" />
        <div class="a4-field a4-field-wide"><label for="ts-c-title">{'Title'|i18n( $tsI )}</label>
            <input class="a4-input" type="text" id="ts-c-title" name="CannedTitle" maxlength="200" required="required" value="{$values.title|wash}"{if is_set( $field_errors.title )} aria-invalid="true" aria-describedby="ts-c-title-e"{/if} />
            {if is_set( $field_errors.title )}<p class="a4-field-error" id="ts-c-title-e">{$field_errors.title|i18n( $tsI )|wash}</p>{/if}</div>
        <div class="a4-field a4-field-wide"><label for="ts-c-body">{'Text'|i18n( $tsI )}</label>
            <textarea class="a4-input" id="ts-c-body" name="CannedBody" rows="8" required="required"{if is_set( $field_errors.body )} aria-invalid="true" aria-describedby="ts-c-body-e"{/if}>{$values.body|wash}</textarea>
            {if is_set( $field_errors.body )}<p class="a4-field-error" id="ts-c-body-e">{$field_errors.body|i18n( $tsI )|wash}</p>{/if}
            <p class="a4-help">{'Placeholders:'|i18n( $tsI )} {foreach $placeholders as $tsP}<code class="a4-code">{ldelim}{$tsP}{rdelim}</code> {/foreach}</p></div>
        <div class="a4-grid is-narrow">
            <div class="a4-field"><label for="ts-c-cat">{'Offered in the queue'|i18n( $tsI )}</label>
                <select class="a4-input" id="ts-c-cat" name="CannedCategory"><option value="">{'Every queue'|i18n( $tsI )}</option>
                {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.category|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
            <div class="a4-field"><label for="ts-c-status">{'Macro: status after the reply'|i18n( $tsI )}</label>
                <select class="a4-input" id="ts-c-status" name="CannedStatus"><option value="">{'As chosen in the form'|i18n( $tsI )}</option>
                {foreach $status_choices as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.set_status|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
            <div class="a4-field"><label for="ts-c-prio">{'Macro: priority'|i18n( $tsI )}</label>
                <select class="a4-input" id="ts-c-prio" name="CannedPriority"><option value="">{'Unchanged'|i18n( $tsI )}</option>
                {foreach $priorities as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.set_priority|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
        </div>
        <label class="a4-check"><input type="checkbox" name="CannedAssignSelf" value="1"{if $values.assign_self} checked="checked"{/if} /> {'Macro: assign the ticket to the sender'|i18n( $tsI )}</label>
        <label class="a4-check"><input type="checkbox" name="CannedShared" value="1"{if $values.shared} checked="checked"{/if} /> {'Shared with every support member (else only yours)'|i18n( $tsI )}</label>
        <div class="a4-actions"><button type="submit" class="a4-btn a4-btn-primary" name="CannedSave" value="1" id="ts-c-save">{'Save'|i18n( $tsI )}</button></div>
    </form>
    </div>
</section>
{else}
<div class="a4-toolbar"><a class="a4-btn a4-btn-small a4-btn-primary" id="ts-c-new" href={'ticketsystem/canned/(edit)/new'|ezurl}>{'New canned reply'|i18n( $tsI )}</a></div>
{if $canned|count}
<div class="a4-table-wrap"><table class="a4-table" id="ts-canned-list">
    <thead><tr><th scope="col">{'Title'|i18n( $tsI )}</th><th scope="col">{'Queue'|i18n( $tsI )}</th><th scope="col">{'Macro'|i18n( $tsI )}</th>
        <th scope="col">{'Shared'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Used'|i18n( $tsI )}</th><th scope="col"><span class="a4-sr-only">{'Actions'|i18n( $tsI )}</span></th></tr></thead>
    <tbody>
    {foreach $canned as $c}
    <tr><td><a href={concat( 'ticketsystem/canned/(edit)/', $c.id )|ezurl}>{$c.title|wash}</a></td>
        <td>{if $c.category}{$c.category|i18n( $tsI )|wash}{else}<span class="a4-muted">{'every queue'|i18n( $tsI )}</span>{/if}</td>
        <td>{if $c.status}<span class="a4-badge">{$c.status|i18n( $tsI )|wash}</span> {/if}{if $c.priority}<span class="a4-badge">{$c.priority|i18n( $tsI )|wash}</span> {/if}{if $c.assign_self}<span class="a4-badge">{'assign to me'|i18n( $tsI )}</span>{/if}</td>
        <td>{if $c.shared}{'yes'|i18n( $tsI )}{else}{'only you'|i18n( $tsI )}{/if}</td>
        <td class="a4-num">{$c.uses}</td>
        <td><form class="a4-actions" method="post" action={'ticketsystem/canned'|ezurl}><input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" /><input type="hidden" name="CannedID" value="{$c.id}" />
            <button type="submit" class="a4-btn a4-btn-small a4-btn-ghost" name="CannedDelete" value="1">{'Delete'|i18n( $tsI )}</button></form></td></tr>
    {/foreach}
    </tbody>
</table></div>
{else}
<p class="a4-empty" id="ts-canned-empty">{'No canned replies yet.'|i18n( $tsI )}</p>
{/if}
{/if}
{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
