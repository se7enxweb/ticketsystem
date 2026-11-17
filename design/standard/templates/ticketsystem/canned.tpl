{* ticketsystem/canned (doc/canned-replies.md): the canned replies and macros, the form to write and change one; the
   administration has its own (design admin4).
   Input: $canned (id, title, shared, category, status, priority, assign_self, uses, modified, may_edit), $edit, $values,
   $field_errors, $queue, $categories, $priorities, $status_choices, $may_personal, $may_shared, $macros, $placeholders,
   $placeholder_help (name, what), the form token. No inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead"><div><h1 class="ktl-h1 ts-h1">{'Canned replies'|i18n( $tsI )}</h1>
    <p class="ktl-lede ts-lede">{'Texts you use often, with placeholders; a macro also sets the status, the priority or the assignee when the reply is sent.'|i18n( $tsI )}</p></div>
    {if $edit|not}<p class="ktl-actions ts-actions"><a class="ktl-btn is-primary ts-btn is-primary" id="ts-canned-new" href={'ticketsystem/canned/(edit)/new'|ezurl}>{'New canned reply'|i18n( $tsI )}</a></p>{/if}</div>

{if $edit}
<section class="ktl-card ts-card" aria-labelledby="ts-canned-form-h">
<div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-canned-form-h">{if $values.id}{'Change the canned reply'|i18n( $tsI )}{else}{'New canned reply'|i18n( $tsI )}{/if}</h2></div>
<form class="ktl-form ts-form" method="post" action={'ticketsystem/canned'|ezurl} id="ts-canned-form">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" /><input type="hidden" name="CannedID" value="{$values.id}" />
    <div class="ktl-field ts-field"><label for="ts-c-title">{'Title'|i18n( $tsI )}</label>
        <input class="ktl-input ts-input{if is_set( $field_errors.title )} is-invalid{/if}" type="text" id="ts-c-title" name="CannedTitle" maxlength="200" required value="{$values.title|wash}"{if is_set( $field_errors.title )} aria-invalid="true" aria-describedby="ts-c-title-e"{/if} />
        {if is_set( $field_errors.title )}<p class="ktl-field-error ts-field-error" id="ts-c-title-e">{$field_errors.title|i18n( $tsI )|wash}</p>{/if}</div>
    <div class="ktl-field ts-field"><label for="ts-c-body">{'Text'|i18n( $tsI )}</label>
        <textarea class="ktl-input ts-input ts-textarea{if is_set( $field_errors.body )} is-invalid{/if}" id="ts-c-body" name="CannedBody" rows="8" required aria-describedby="ts-c-help{if is_set( $field_errors.body )} ts-c-body-e{/if}"{if is_set( $field_errors.body )} aria-invalid="true"{/if}>{$values.body|wash}</textarea>
        {if is_set( $field_errors.body )}<p class="ktl-field-error ts-field-error" id="ts-c-body-e">{$field_errors.body|i18n( $tsI )|wash}</p>{/if}
        <div class="ktl-muted ts-muted" id="ts-c-help"><p>{'Placeholders, filled in when the reply is inserted:'|i18n( $tsI )}</p>
            <dl class="ktl-facts is-compact ts-facts" id="ts-c-placeholders">{foreach $placeholder_help as $tsP}<dt><code>{ldelim}{$tsP.name}{rdelim}</code></dt><dd>{$tsP.what|i18n( $tsI )|wash}</dd>{/foreach}</dl></div></div>
    <div class="ktl-field ts-field"><label for="ts-c-queue">{'Queue'|i18n( $tsI )}</label>
        <select class="ktl-input ts-input" id="ts-c-queue" name="CannedCategory"><option value="">{'Every queue'|i18n( $tsI )}</option>
        {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.category|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
    {if $macros}
    <fieldset class="ktl-field ts-field"><legend>{'Macro: when the reply is sent'|i18n( $tsI )}</legend>
        <div class="ktl-field ts-field"><label for="ts-c-status">{'Set the status'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-c-status" name="CannedStatus"><option value="">{'Do not change'|i18n( $tsI )}</option>
            {foreach $status_choices as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.set_status|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
        <div class="ktl-field ts-field"><label for="ts-c-priority">{'Set the priority'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-c-priority" name="CannedPriority"><option value="">{'Do not change'|i18n( $tsI )}</option>
            {foreach $priorities as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.set_priority|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
        <label><input type="checkbox" name="CannedAssignSelf" value="1"{if $values.assign_self} checked="checked"{/if} /> {'Assign the ticket to the sender'|i18n( $tsI )}</label>
    </fieldset>
    {/if}
    {if and( $may_personal, $may_shared )}
    <label><input type="checkbox" name="CannedShared" value="1" id="ts-c-shared"{if $values.shared} checked="checked"{/if} /> {'Shared with the whole support team (else only yours)'|i18n( $tsI )}</label>
    {elseif $may_shared}<p class="ktl-muted ts-muted">{'Shared with the whole support team.'|i18n( $tsI )}</p>
    {else}<p class="ktl-muted ts-muted">{'Only for you.'|i18n( $tsI )}</p>{/if}
    <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="CannedSave" value="1" id="ts-c-save">{'Save'|i18n( $tsI )}</button> <a class="ktl-btn is-ghost ts-btn is-ghost" href={'ticketsystem/canned'|ezurl}>{'Cancel'|i18n( $tsI )}</a></p>
</form>
</section>
{else}
<section class="ktl-card ts-card" aria-label="{'Queue'|i18n( $tsI )|wash}">
<form class="ktl-form ts-form" method="post" action={'ticketsystem/canned'|ezurl} id="ts-canned-filter">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
    <div class="ktl-field ts-field"><label for="ts-c-filter">{'Show the canned replies of'|i18n( $tsI )}</label>
        <select class="ktl-input ts-input" id="ts-c-filter" name="CannedQueue"><option value="">{'Every queue'|i18n( $tsI )}</option>
        {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $queue|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
    <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="CannedFilter" value="1">{'Show'|i18n( $tsI )}</button></p>
</form>
</section>
<section class="ktl-card ts-card" aria-labelledby="ts-canned-list-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-canned-list-h">{"Your canned replies and the team's"|i18n( $tsI )}</h2></div>
{if $canned|count}
    <div class="ktl-table-wrap ts-table-wrap"><table class="ktl-table ts-table" id="ts-canned-list">
        <thead><tr><th scope="col">{'Title'|i18n( $tsI )}</th><th scope="col">{'Queue'|i18n( $tsI )}</th><th scope="col">{'Macro'|i18n( $tsI )}</th><th scope="col" class="ktl-num">{'Used'|i18n( $tsI )}</th><th scope="col"><span class="ktl-muted ts-muted">{'Actions'|i18n( $tsI )}</span></th></tr></thead>
        <tbody>{foreach $canned as $c}<tr data-ts-canned-id="{$c.id}">
            <td data-label="{'Title'|i18n( $tsI )|wash}"><strong>{$c.title|wash}</strong> <span class="ktl-badge ts-badge">{if $c.shared}{'shared'|i18n( $tsI )}{else}{'personal'|i18n( $tsI )}{/if}</span></td>
            <td data-label="{'Queue'|i18n( $tsI )|wash}">{if $c.category}{$c.category|i18n( $tsI )|wash}{else}{'Every queue'|i18n( $tsI )}{/if}</td>
            <td data-label="{'Macro'|i18n( $tsI )|wash}">{if $c.status}{'Status: %s'|i18n( $tsI,, hash( '%s', $c.status ) )|wash}<br>{/if}{if $c.priority}{'Priority: %s'|i18n( $tsI,, hash( '%s', $c.priority ) )|wash}<br>{/if}{if $c.assign_self}{'Assigns the sender'|i18n( $tsI )}{/if}</td>
            <td data-label="{'Used'|i18n( $tsI )|wash}" class="ktl-num">{$c.uses}</td>
            <td data-label="{'Actions'|i18n( $tsI )|wash}">{if $c.may_edit}<a class="ktl-btn is-ghost ts-btn is-ghost" href={concat( 'ticketsystem/canned/(edit)/', $c.id )|ezurl}>{'Change'|i18n( $tsI )}</a>
                <form class="ts-inline-form" method="post" action={'ticketsystem/canned'|ezurl}><input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" /><input type="hidden" name="CannedID" value="{$c.id}" />
                <button type="submit" class="ktl-btn is-ghost ts-btn is-ghost" name="CannedDelete" value="1">{'Delete'|i18n( $tsI )}</button></form>{/if}</td></tr>{/foreach}</tbody>
    </table></div>
{else}<p class="ktl-empty-line ts-empty" id="ts-canned-empty">{'No canned replies yet.'|i18n( $tsI )}</p>{/if}
</section>
{/if}
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
