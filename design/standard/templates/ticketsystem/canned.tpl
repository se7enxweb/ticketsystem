{* ticketsystem/canned on the site (doc/canned.md): the list; the form is the administration's (design admin4) or
   this simple one. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead"><div><h1 class="ktl-h1 ts-h1">{'Canned replies'|i18n( $tsI )}</h1></div>
    {if $edit|not}<p class="ktl-actions ts-actions"><a class="ktl-btn is-primary ts-btn is-primary" href={'ticketsystem/canned/(edit)/new'|ezurl}>{'New canned reply'|i18n( $tsI )}</a></p>{/if}</div>
<section class="ktl-card ts-card">
{if $edit}
<form class="ktl-form ts-form" method="post" action={'ticketsystem/canned'|ezurl} id="ts-canned-form">
    <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" /><input type="hidden" name="CannedID" value="{$values.id}" />
    <div class="ktl-field ts-field"><label for="ts-c-title">{'Title'|i18n( $tsI )}</label><input class="ktl-input ts-input" type="text" id="ts-c-title" name="CannedTitle" maxlength="200" value="{$values.title|wash}" /></div>
    <div class="ktl-field ts-field"><label for="ts-c-body">{'Text'|i18n( $tsI )}</label><textarea class="ktl-input ts-input ts-textarea" id="ts-c-body" name="CannedBody" rows="6">{$values.body|wash}</textarea></div>
    <label><input type="checkbox" name="CannedShared" value="1"{if $values.shared} checked="checked"{/if} /> {'Shared with every support member (else only yours)'|i18n( $tsI )}</label>
    <input type="hidden" name="CannedCategory" value="{$values.category|wash}" /><input type="hidden" name="CannedStatus" value="{$values.set_status|wash}" /><input type="hidden" name="CannedPriority" value="{$values.set_priority|wash}" />
    {if $values.assign_self}<input type="hidden" name="CannedAssignSelf" value="1" />{/if}
    <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="CannedSave" value="1">{'Save'|i18n( $tsI )}</button> <a class="ktl-btn is-ghost ts-btn is-ghost" href={'ticketsystem/canned'|ezurl}>{'Cancel'|i18n( $tsI )}</a></p>
</form>
{elseif $canned|count}
<ul class="ktl-rows ts-posts" id="ts-canned-list">{foreach $canned as $c}<li><a href={concat( 'ticketsystem/canned/(edit)/', $c.id )|ezurl}>{$c.title|wash}</a>{if $c.shared} <span class="ktl-badge ts-badge">{'shared'|i18n( $tsI )}</span>{/if}</li>{/foreach}</ul>
{else}<p class="ktl-empty-line ts-empty">{'No canned replies yet.'|i18n( $tsI )}</p>{/if}
</section>
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
