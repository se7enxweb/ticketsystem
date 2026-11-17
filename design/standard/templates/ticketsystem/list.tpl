{* ticketsystem/list (doc/views.md): the tickets the user may read, newest activity first.
   Input: $tickets (ticketsystemView::row), $total, $counts (open, done, all), $tab (open, done, all), $offset, $limit, $may_create,
   $is_staff, the frame's variables. Mobile first: the table is a list of blocks on phones (katalyst.js / the
   ts-table rules). No inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}

<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1">{'Support tickets'|i18n( $tsI )}</h1>
        <p class="ktl-lede ts-lede">{if $is_staff}{'Every ticket you can work on, newest activity first.'|i18n( $tsI )}{else}{'Ask us anything about your account, sites and billing. Every reply is also sent to your e-mail, so you keep your own record.'|i18n( $tsI )}{/if}</p>
    </div>
    {if $may_create}<p class="ktl-actions ts-actions"><a class="ktl-btn is-primary ts-btn is-primary" href={'ticketsystem/new'|ezurl} id="ts-new-ticket"><i class="fa-solid fa-plus" aria-hidden="true"></i> {'New ticket'|i18n( $tsI )}</a></p>{/if}
</div>

<nav class="ktl-tabs ts-tabs" aria-label="{'Filter tickets'|i18n( $tsI )|wash}">
    <a class="ktl-tab ts-tab{if $tab|eq( 'open' )} is-current{/if}" href={'ticketsystem/list'|ezurl}{if $tab|eq( 'open' )} aria-current="page"{/if}>{'Open'|i18n( $tsI )} <span class="ktl-badge ts-badge">{$counts.open}</span></a>
    <a class="ktl-tab ts-tab{if $tab|eq( 'done' )} is-current{/if}" href={'ticketsystem/list/(status)/done'|ezurl}{if $tab|eq( 'done' )} aria-current="page"{/if}>{'Solved and closed'|i18n( $tsI )} <span class="ktl-badge ts-badge">{$counts.done}</span></a>
    <a class="ktl-tab ts-tab{if $tab|eq( 'all' )} is-current{/if}" href={'ticketsystem/list/(status)/all'|ezurl}{if $tab|eq( 'all' )} aria-current="page"{/if}>{'All'|i18n( $tsI )} <span class="ktl-badge ts-badge">{$counts.all}</span></a>
</nav>

{if $tickets|count|eq( 0 )}
<section class="ktl-card ktl-empty ts-card ts-empty" id="ts-empty">
    <span class="ktl-empty-icon ts-empty-icon" aria-hidden="true"><i class="fa-solid fa-life-ring"></i></span>
    <h2 class="ktl-h2 ts-h2">{if $tab|eq( 'open' )}{'No open tickets'|i18n( $tsI )}{else}{'No tickets here'|i18n( $tsI )}{/if}</h2>
    <p>{'When you open a ticket, it shows here with its status and every reply.'|i18n( $tsI )}</p>
    {if $may_create}<p class="ktl-actions ts-actions"><a class="ktl-btn is-primary ts-btn is-primary" href={'ticketsystem/new'|ezurl}>{'Open a ticket'|i18n( $tsI )}</a></p>{/if}
</section>
{else}
<section class="ktl-card ts-card" aria-labelledby="ts-list-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-list-h"><i class="fa-solid fa-ticket ktl-icon" aria-hidden="true"></i>{'Tickets'|i18n( $tsI )}</h2><span class="ktl-muted ts-muted">{'%count in this list'|i18n( $tsI,, hash( '%count', $total ) )|wash}</span></div>
    <div class="ktl-table-wrap ts-table-wrap">
        <table class="ktl-table ts-table" id="ts-tickets">
            <thead><tr>
                <th scope="col">{'Ticket'|i18n( $tsI )}</th>
                <th scope="col">{'Status'|i18n( $tsI )}</th>
                <th scope="col" class="ktl-col-wide">{'Category'|i18n( $tsI )}</th>
                <th scope="col">{'Last update'|i18n( $tsI )}</th>
                <th scope="col" class="ktl-num">{'Posts'|i18n( $tsI )}</th>
            </tr></thead>
            <tbody>
            {foreach $tickets as $t}
                <tr data-ts-number="{$t.number|wash}">
                    <td data-label="{'Ticket'|i18n( $tsI )|wash}"><a class="ts-ticket-link" href={concat( 'ticketsystem/view/', $t.number )|ezurl}><strong>{$t.subject|wash}</strong></a><br><span class="ktl-muted ktl-small ts-muted ts-small">{$t.number|wash}{if $is_staff} &middot; {$t.account_label|wash}{/if}</span></td>
                    <td data-label="{'Status'|i18n( $tsI )|wash}">{include uri='design:ticketsystem/parts/status.tpl' label=$t.status_label tone=$t.status_tone}{if and( $t.last_post_staff, $t.done|not, $is_staff|not )} <span class="ktl-badge ts-badge">{'New reply'|i18n( $tsI )}</span>{/if}{if and( $is_staff, is_set( $t.sla_state ) )}<br>{include uri='design:ticketsystem/parts/sla_badge.tpl' row=$t}{/if}</td>
                    <td data-label="{'Category'|i18n( $tsI )|wash}" class="ktl-col-wide">{$t.category_label|i18n( $tsI )|wash}</td>
                    <td data-label="{'Last update'|i18n( $tsI )|wash}" class="ktl-nowrap">{$t.modified|l10n( 'shortdatetime' )}</td>
                    <td data-label="{'Posts'|i18n( $tsI )|wash}" class="ktl-num">{$t.post_count}</td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
    {if $total|gt( $limit )}
    <nav class="ktl-pager ts-pager" aria-label="{'Pages'|i18n( $tsI )|wash}">
        {if $offset|gt( 0 )}<a class="ktl-btn is-ghost ts-btn is-ghost" href={concat( 'ticketsystem/list/(status)/', $tab, '/(offset)/', max( 0, $offset|sub( $limit ) ) )|ezurl}>{'Newer'|i18n( $tsI )}</a>{/if}
        {if $offset|sum( $limit )|lt( $total )}<a class="ktl-btn is-ghost ts-btn is-ghost" href={concat( 'ticketsystem/list/(status)/', $tab, '/(offset)/', $offset|sum( $limit ) )|ezurl}>{'Older'|i18n( $tsI )}</a>{/if}
    </nav>
    {/if}
</section>
{/if}

{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
