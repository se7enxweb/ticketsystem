{* ticketsystem/canned in the administration (doc/canned-replies.md, doc/views.md): the canned replies and macros per
   queue, personal or shared, and the form of one. The queue filter posts and is redirected to view parameters.
   Input: $canned (id, title, shared, category, status, priority, assign_self, uses, modified, may_edit), $edit,
   $values, $field_errors, $queue, $categories, $priorities, $status_choices, $may_personal, $may_shared, $macros,
   $placeholder_help (name, what), the form token. Only the shared admin components (.a4-*). *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Canned replies'|i18n( $tsI )
         sub='Texts you use often, with placeholders; a macro also sets the status, the priority or the assignee when the reply is sent.'|i18n( $tsI )}

{if $edit}
<section class="a4-card" aria-labelledby="ts-canned-form-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-canned-form-h">{if $values.id}{'Change the canned reply'|i18n( $tsI )}{else}{'New canned reply'|i18n( $tsI )}{/if}</h2>
        <div class="a4-card-actions"><a class="a4-btn a4-btn-small a4-btn-ghost" href={'ticketsystem/canned'|ezurl}>{'Back to the list'|i18n( $tsI )}</a></div></div>
    <div class="a4-card-body">
    <form class="a4-form is-wide" method="post" action={'ticketsystem/canned'|ezurl} id="ts-canned-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <input type="hidden" name="CannedID" value="{$values.id}" />
        <div class="a4-field a4-field-wide{if is_set( $field_errors.title )} a4-field-error{/if}"><label for="ts-c-title">{'Title'|i18n( $tsI )}</label>
            <input class="a4-input" type="text" id="ts-c-title" name="CannedTitle" maxlength="200" required="required" value="{$values.title|wash}"{if is_set( $field_errors.title )} aria-invalid="true" aria-describedby="ts-c-title-e"{/if} />
            {if is_set( $field_errors.title )}<p class="a4-bad-text" id="ts-c-title-e">{$field_errors.title|i18n( $tsI )|wash}</p>{/if}</div>
        <div class="a4-field a4-field-wide{if is_set( $field_errors.body )} a4-field-error{/if}"><label for="ts-c-body">{'Text'|i18n( $tsI )}</label>
            <textarea class="a4-input" id="ts-c-body" name="CannedBody" rows="8" required="required" aria-describedby="ts-c-help{if is_set( $field_errors.body )} ts-c-body-e{/if}"{if is_set( $field_errors.body )} aria-invalid="true"{/if}>{$values.body|wash}</textarea>
            {if is_set( $field_errors.body )}<p class="a4-bad-text" id="ts-c-body-e">{$field_errors.body|i18n( $tsI )|wash}</p>{/if}</div>
        <details class="a4-fold a4-field-wide" id="ts-c-help">
            <summary>{'Placeholders, filled in when the reply is inserted'|i18n( $tsI )}</summary>
            <div class="a4-fold-body"><dl class="a4-facts is-rows" id="ts-c-placeholders">{foreach $placeholder_help as $tsP}<div><dt><code class="a4-code">{ldelim}{$tsP.name}{rdelim}</code></dt><dd>{$tsP.what|i18n( $tsI )|wash}</dd></div>{/foreach}</dl></div>
        </details>
        <div class="a4-grid is-narrow">
            <div class="a4-field"><label for="ts-c-cat">{'Queue'|i18n( $tsI )}</label>
                <select class="a4-input" id="ts-c-cat" name="CannedCategory"><option value="">{'Every queue'|i18n( $tsI )}</option>
                {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.category|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
            {if $macros}
            <div class="a4-field"><label for="ts-c-status">{'Macro: set the status'|i18n( $tsI )}</label>
                <select class="a4-input" id="ts-c-status" name="CannedStatus"><option value="">{'Do not change'|i18n( $tsI )}</option>
                {foreach $status_choices as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.set_status|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
            <div class="a4-field"><label for="ts-c-prio">{'Macro: set the priority'|i18n( $tsI )}</label>
                <select class="a4-input" id="ts-c-prio" name="CannedPriority"><option value="">{'Do not change'|i18n( $tsI )}</option>
                {foreach $priorities as $tsK => $tsL}<option value="{$tsK|wash}"{if $values.set_priority|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
            {/if}
        </div>
        {if $macros}<label class="a4-check"><input type="checkbox" name="CannedAssignSelf" value="1"{if $values.assign_self} checked="checked"{/if} /> <span>{'Macro: assign the ticket to the sender'|i18n( $tsI )}</span></label>{/if}
        {if and( $may_personal, $may_shared )}
        <label class="a4-check"><input type="checkbox" name="CannedShared" value="1" id="ts-c-shared"{if $values.shared} checked="checked"{/if} /> <span>{'Shared with the whole support team (else only yours)'|i18n( $tsI )}</span></label>
        {elseif $may_shared}<p class="a4-help">{'Shared with the whole support team.'|i18n( $tsI )}</p>
        {else}<p class="a4-help">{'Only for you.'|i18n( $tsI )}</p>{/if}
        <div class="a4-actions"><button type="submit" class="a4-btn a4-btn-primary" name="CannedSave" value="1" id="ts-c-save">{'Save'|i18n( $tsI )}</button>
            <a class="a4-btn a4-btn-ghost" href={'ticketsystem/canned'|ezurl}>{'Cancel'|i18n( $tsI )}</a></div>
    </form>
    </div>
</section>
{else}
<form class="a4-section" method="post" action={'ticketsystem/canned'|ezurl} id="ts-canned-filter">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
    <div class="a4-filterbar">
        <div class="a4-field"><label for="ts-c-filter">{'Show the canned replies of'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-c-filter" name="CannedQueue"><option value="">{'Every queue'|i18n( $tsI )}</option>
            {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $queue|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select></div>
        <div class="a4-field"><div class="a4-actions">
            <button type="submit" class="a4-btn" name="CannedFilter" value="1">{'Show'|i18n( $tsI )}</button>
            {if or( $may_personal, $may_shared )}<a class="a4-btn a4-btn-primary" id="ts-c-new" href={'ticketsystem/canned/(edit)/new'|ezurl}>{'New canned reply'|i18n( $tsI )}</a>{/if}
        </div></div>
    </div>
</form>
{if $canned|count}
<div class="a4-table-wrap"><table class="a4-table" id="ts-canned-list">
    <caption class="a4-sr-only">{"Your canned replies and the team's"|i18n( $tsI )}</caption>
    <thead><tr><th scope="col">{'Title'|i18n( $tsI )}</th><th scope="col">{'Scope'|i18n( $tsI )}</th><th scope="col">{'Queue'|i18n( $tsI )}</th><th scope="col">{'Macro'|i18n( $tsI )}</th>
        <th scope="col" class="a4-num">{'Used'|i18n( $tsI )}</th><th scope="col"><span class="a4-sr-only">{'Actions'|i18n( $tsI )}</span></th></tr></thead>
    <tbody>
    {foreach $canned as $c}
    <tr data-ts-canned-id="{$c.id}">
        <td>{if $c.may_edit}<a href={concat( 'ticketsystem/canned/(edit)/', $c.id )|ezurl}>{$c.title|wash}</a>{else}{$c.title|wash}{/if}</td>
        <td>{if $c.shared}<span class="a4-badge is-info">{'shared'|i18n( $tsI )}</span>{else}<span class="a4-tag">{'personal'|i18n( $tsI )}</span>{/if}</td>
        <td>{if $c.category}{first_set( $categories[$c.category], $c.category )|i18n( $tsI )|wash}{else}<span class="a4-muted">{'every queue'|i18n( $tsI )}</span>{/if}</td>
        <td>{if $c.status}<span class="a4-badge">{first_set( $status_choices[$c.status], $c.status )|i18n( $tsI )|wash}</span> {/if}{if $c.priority}<span class="a4-badge">{first_set( $priorities[$c.priority], $c.priority )|i18n( $tsI )|wash}</span> {/if}{if $c.assign_self}<span class="a4-badge">{'assigns the sender'|i18n( $tsI )}</span>{/if}{if and( $c.status|not, $c.priority|not, $c.assign_self|not )}<span class="a4-muted">&ndash;</span>{/if}</td>
        <td class="a4-num">{$c.uses}</td>
        <td>{if $c.may_edit}<form class="a4-actions" method="post" action={'ticketsystem/canned'|ezurl}><input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" /><input type="hidden" name="CannedID" value="{$c.id}" />
            <a class="a4-btn a4-btn-small" href={concat( 'ticketsystem/canned/(edit)/', $c.id )|ezurl}>{'Change'|i18n( $tsI )}</a>
            <button type="submit" class="a4-btn a4-btn-small a4-btn-outline-danger" name="CannedDelete" value="1">{'Delete'|i18n( $tsI )}</button></form>{/if}</td>
    </tr>
    {/foreach}
    </tbody>
</table></div>
{else}
<p class="a4-empty" id="ts-canned-empty">{'No canned replies yet.'|i18n( $tsI )}</p>
{/if}
{/if}

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
