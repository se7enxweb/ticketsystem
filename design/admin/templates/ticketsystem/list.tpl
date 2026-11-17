{* ticketsystem/list in the administration (design admin4, doc/views.md "The staff desk"). Staff ($desk): the status
   figures, the filter bar (view parameters), the sortable and paged table. Others: their tickets with three tabs.
   Only shared admin components (.a4-*), no inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Tickets'|i18n( $tsI )
         sub=cond( $desk, 'Every ticket you can work on: the queue, assignments, SLA and replies.'|i18n( $tsI ), 'Your tickets and those of your account.'|i18n( $tsI ) )}

{if $desk}
<ul class="a4-figures" id="ts-a4-figures" aria-label="{'Tickets per status'|i18n( $tsI )|wash}">
    <li class="a4-figure is-info{if is_set( $filter.status )|not} is-current{/if}"><a href={$figures.urls.active|ezurl}{if is_set( $filter.status )|not} aria-current="page"{/if}><strong>{$figures.active}</strong> <span>{'not resolved'|i18n( $tsI )}</span></a></li>
    {foreach $figures.statuses as $tsF}
    <li class="a4-figure{if $tsF.badge} {$tsF.badge}{/if}{if and( is_set( $filter.status ), $filter.status|eq( $tsF.id ) )} is-current{/if}" data-status="{$tsF.id|wash}"><a href={$tsF.url|ezurl}><strong>{$tsF.count}</strong> <span>{$tsF.label|i18n( $tsI )|wash}</span></a></li>
    {/foreach}
    <li class="a4-figure{if and( is_set( $filter.status ), $filter.status|eq( 'all' ) )} is-current{/if}"><a href={$figures.urls.all|ezurl}><strong>{$figures.all}</strong> <span>{'in all'|i18n( $tsI )}</span></a></li>
</ul>

<form method="post" action={'ticketsystem/list'|ezurl} id="ts-a4-filter">
<input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
<div class="a4-filterbar">
    <div class="a4-field a4-field-wide">
        <label for="ts-f-q">{'Search'|i18n( $tsI )}</label>
        <input class="a4-input" type="search" id="ts-f-q" name="Filter[q]" value="{first_set( $filter.q, '' )|wash}" maxlength="100" />
        <span class="a4-help">{'A ticket number or words of the subject.'|i18n( $tsI )}</span>
    </div>
    <div class="a4-field"><label for="ts-f-status">{'Status'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-f-status" name="Filter[status]">
        {foreach $status_options as $tsK => $tsL}<option value="{if $tsK|ne( 'active' )}{$tsK|wash}{/if}"{if first_set( $filter.status, 'active' )|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}
        </select></div>
    <div class="a4-field"><label for="ts-f-assignee">{'Assigned to'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-f-assignee" name="Filter[assignee]">
            <option value="">{'Anyone'|i18n( $tsI )}</option>
            <option value="me"{if first_set( $filter.assignee, '' )|eq( 'me' )} selected="selected"{/if}>{'Me'|i18n( $tsI )}</option>
            <option value="none"{if first_set( $filter.assignee, '' )|eq( 'none' )} selected="selected"{/if}>{'Unassigned'|i18n( $tsI )}</option>
            {foreach $staff as $tsK => $tsL}<option value="{$tsK}"{if first_set( $filter.assignee, '' )|eq( $tsK )} selected="selected"{/if}>{$tsL|wash}</option>{/foreach}
        </select></div>
    <div class="a4-field"><label for="ts-f-priority">{'Priority'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-f-priority" name="Filter[priority]"><option value="">{'Any'|i18n( $tsI )}</option>
        {foreach $priorities as $tsK => $tsL}<option value="{$tsK|wash}"{if first_set( $filter.priority, '' )|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}
        </select></div>
    <div class="a4-field"><label for="ts-f-category">{'Queue'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-f-category" name="Filter[category]"><option value="">{'Any'|i18n( $tsI )}</option>
        {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if first_set( $filter.category, '' )|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}
        </select></div>
    <div class="a4-field"><label for="ts-f-reply">{'Last post'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-f-reply" name="Filter[reply]"><option value="">{'Any'|i18n( $tsI )}</option>
        {foreach $reply_options as $tsK => $tsL}<option value="{$tsK|wash}"{if first_set( $filter.reply, '' )|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}
        </select></div>
    <div class="a4-field"><label for="ts-f-sla">{'SLA'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-f-sla" name="Filter[sla]"><option value="">{'Any'|i18n( $tsI )}</option>
        {foreach $sla_options as $tsK => $tsL}<option value="{$tsK|wash}"{if first_set( $filter.sla, '' )|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}
        </select></div>
    <div class="a4-actions">
        <button type="submit" class="a4-btn a4-btn-primary" name="TicketFilter" value="1">{'Filter'|i18n( $tsI )}</button>
        <button type="submit" class="a4-btn a4-btn-ghost" name="TicketReset" value="1">{'Reset'|i18n( $tsI )}</button>
        <input type="hidden" name="TicketFilter" value="1" />
    </div>
</div>
</form>
{if $chips|count}
<ul class="a4-chips" aria-label="{'Active filters'|i18n( $tsI )|wash}">
    {foreach $chips as $tsC}<li><a class="a4-tag" href={$tsC.url|ezurl} title="{'Remove this filter'|i18n( $tsI )|wash}">{$tsC.name|i18n( $tsI )|wash}: {$tsC.value|i18n( $tsI )|wash} <span aria-hidden="true">&times;</span></a></li>{/foreach}
</ul>
{/if}

{if $tickets|count}
<div class="a4-table-wrap"><table class="a4-table" id="ts-tickets">
    <thead><tr>
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='number' text='Number'|i18n( $tsI )}
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='subject' text='Subject'|i18n( $tsI )}
        <th scope="col">{'Account'|i18n( $tsI )}</th>
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='status' text='Status'|i18n( $tsI )}
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='priority' text='Priority'|i18n( $tsI )}
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='category' text='Queue'|i18n( $tsI )}
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='assignee' text='Assigned to'|i18n( $tsI )}
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='due' text='SLA'|i18n( $tsI )}
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='posts' text='Posts'|i18n( $tsI ) cls='a4-num'}
        {include uri='design:ticketsystem/parts/sort_th.tpl' key='last_post' text='Last post'|i18n( $tsI )}
    </tr></thead>
    <tbody>
    {foreach $tickets as $t}
    <tr data-ts-number="{$t.number|wash}">
        <td class="a4-nowrap"><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.number|wash}</a></td>
        <td><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.subject|wash}</a>
            {if $t.attachment_count} <span class="a4-badge" title="{'Attachments'|i18n( $tsI )|wash}">{$t.attachment_count} {'files'|i18n( $tsI )}</span>{/if}
            {if $t.note_count} <span class="a4-badge is-accent" title="{'Internal notes'|i18n( $tsI )|wash}">{$t.note_count} {'notes'|i18n( $tsI )}</span>{/if}</td>
        <td>{$t.account_label|wash}</td>
        <td>{include uri='design:ticketsystem/parts/status.tpl' label=$t.status_label tone=$t.status_tone}</td>
        <td>{if $t.priority_high}<span class="a4-badge is-bad">{$t.priority_label|i18n( $tsI )|wash}</span>{else}{$t.priority_label|i18n( $tsI )|wash}{/if}</td>
        <td>{$t.category_label|i18n( $tsI )|wash}</td>
        <td>{if $t.assignee_id}{$t.assignee|wash}{else}<span class="a4-muted">{'nobody'|i18n( $tsI )}</span>{/if}</td>
        <td class="a4-nowrap">{if $t.escalation|ge( 2 )}<span class="a4-badge is-bad">{'breached'|i18n( $tsI )}</span>{elseif $t.escalation|ge( 1 )}<span class="a4-badge is-warn">{'escalated'|i18n( $tsI )}</span>{/if}
            {if $t.sla_due}<span class="a4-meta">{$t.sla_due.text|i18n( $tsI )|wash}</span>{elseif $t.done|not}<span class="a4-muted">&ndash;</span>{/if}</td>
        <td class="a4-num">{$t.post_count}</td>
        <td><time datetime="{$t.last_post|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$t.last_post|l10n( 'shortdatetime' )}</time>
            <span class="a4-meta">{if $t.last_post_staff}{'staff'|i18n( $tsI )}{else}{'customer'|i18n( $tsI )}, {'waiting'|i18n( $tsI )} {$t.waiting|wash}{/if}</span></td>
    </tr>
    {/foreach}
    </tbody>
</table></div>
{if or( $pager_prev, $pager_next )}
<nav class="a4-pager a4-actions" aria-label="{'Pages'|i18n( $tsI )|wash}">
    <span class="a4-meta">{'%from to %to of %total'|i18n( $tsI,, hash( '%from', $offset|inc, '%to', min( $offset|sum( $limit ), $total ), '%total', $total ) )|wash}</span>
    {if $pager_prev}<a class="a4-btn a4-btn-small" href={$pager_prev|ezurl}>{'Newer'|i18n( $tsI )}</a>{/if}
    {if $pager_next}<a class="a4-btn a4-btn-small" href={$pager_next|ezurl}>{'Older'|i18n( $tsI )}</a>{/if}
</nav>
{/if}
{else}
<p class="a4-empty" id="ts-empty">{'No ticket matches the filter.'|i18n( $tsI )}</p>
{/if}

{else}
<ul class="a4-tabs" aria-label="{'Filter tickets'|i18n( $tsI )|wash}">
    <li>{if $tab|eq( 'open' )}<span class="is-current" aria-current="page">{'Open'|i18n( $tsI )} ({$counts.open})</span>{else}<a href={'ticketsystem/list'|ezurl}>{'Open'|i18n( $tsI )} ({$counts.open})</a>{/if}</li>
    <li>{if $tab|eq( 'done' )}<span class="is-current" aria-current="page">{'Solved and closed'|i18n( $tsI )} ({$counts.done})</span>{else}<a href={'ticketsystem/list/(status)/done'|ezurl}>{'Solved and closed'|i18n( $tsI )} ({$counts.done})</a>{/if}</li>
    <li>{if $tab|eq( 'all' )}<span class="is-current" aria-current="page">{'All'|i18n( $tsI )} ({$counts.all})</span>{else}<a href={'ticketsystem/list/(status)/all'|ezurl}>{'All'|i18n( $tsI )} ({$counts.all})</a>{/if}</li>
</ul>
{if $tickets|count}
<div class="a4-table-wrap"><table class="a4-table" id="ts-tickets">
    <thead><tr><th scope="col">{'Number'|i18n( $tsI )}</th><th scope="col">{'Subject'|i18n( $tsI )}</th><th scope="col">{'Status'|i18n( $tsI )}</th>
        <th scope="col">{'Queue'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Posts'|i18n( $tsI )}</th><th scope="col">{'Last update'|i18n( $tsI )}</th></tr></thead>
    <tbody>
    {foreach $tickets as $t}
    <tr data-ts-number="{$t.number|wash}">
        <td class="a4-nowrap"><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.number|wash}</a></td>
        <td><a href={concat( 'ticketsystem/view/', $t.number )|ezurl}>{$t.subject|wash}</a>{if and( $t.last_post_staff, $t.done|not )} <span class="a4-badge is-accent">{'New reply'|i18n( $tsI )}</span>{/if}</td>
        <td>{include uri='design:ticketsystem/parts/status.tpl' label=$t.status_label tone=$t.status_tone}</td>
        <td>{$t.category_label|i18n( $tsI )|wash}</td>
        <td class="a4-num">{$t.post_count}</td>
        <td class="a4-nowrap">{$t.modified|l10n( 'shortdatetime' )}</td>
    </tr>
    {/foreach}
    </tbody>
</table></div>
{if $total|gt( $limit )}
<nav class="a4-pager a4-actions" aria-label="{'Pages'|i18n( $tsI )|wash}">
    {if $offset|gt( 0 )}<a class="a4-btn a4-btn-small" href={concat( 'ticketsystem/list/(status)/', $tab, '/(offset)/', max( 0, $offset|sub( $limit ) ) )|ezurl}>{'Newer'|i18n( $tsI )}</a>{/if}
    {if $offset|sum( $limit )|lt( $total )}<a class="a4-btn a4-btn-small" href={concat( 'ticketsystem/list/(status)/', $tab, '/(offset)/', $offset|sum( $limit ) )|ezurl}>{'Older'|i18n( $tsI )}</a>{/if}
</nav>
{/if}
{else}
<p class="a4-empty" id="ts-empty">{'No tickets here.'|i18n( $tsI )}</p>
{/if}
{/if}

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
