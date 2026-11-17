{* The left menu of the tab TicketSystem (navigation part ezticketsystemnavigationpart, doc/views.md): the queues of the
   staff desk as view-parameter links, then the staff pages by policy. Users without ticketsystem/manage see their
   own lists only. *}
{def $tsI = 'extension/ticketsystem'
     $tsStaff = fetch( 'user', 'has_access_to', hash( 'module', 'ticketsystem', 'function', 'manage' ) )
     $tsReport = fetch( 'user', 'has_access_to', hash( 'module', 'ticketsystem', 'function', 'report' ) )
     $tsImport = ezini( 'ImportSettings', 'Enabled', 'ticketsystem.ini' )|eq( 'enabled' )}
<div class="box-header"><h4>{'Tickets'|i18n( $tsI )}</h4></div>
<div class="box-content">
<ul class="leftmenu-items" id="ts-leftmenu">
    {if $tsStaff}
    <li><a href={'ticketsystem/list'|ezurl}>{'Not resolved'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(reply)/staff'|ezurl}>{'Needs a staff reply'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(assignee)/me'|ezurl}>{'Assigned to me'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(assignee)/none'|ezurl}>{'Unassigned'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(priority)/urgent'|ezurl}>{'Urgent'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(sla)/escalated'|ezurl}>{'Escalated'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(status)/done'|ezurl}>{'Resolved or closed'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(status)/all'|ezurl}>{'All tickets'|i18n( $tsI )}</a></li>
    {else}
    <li><a href={'ticketsystem/list'|ezurl}>{'Open'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(status)/done'|ezurl}>{'Solved and closed'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/list/(status)/all'|ezurl}>{'All'|i18n( $tsI )}</a></li>
    {/if}
    <li><a href={'ticketsystem/new'|ezurl}>{'New ticket'|i18n( $tsI )}</a></li>
</ul>
</div>
{if or( $tsStaff, $tsReport )}
<div class="box-header"><h4>{'Manage'|i18n( $tsI )}</h4></div>
<div class="box-content">
<ul class="leftmenu-items" id="ts-leftmenu-manage">
    {if $tsReport}
    <li><a href={'ticketsystem/reports'|ezurl}>{'Reports'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/search'|ezurl}>{'Search'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/satisfaction'|ezurl}>{'Satisfaction'|i18n( $tsI )}</a></li>
    {/if}
    {if $tsStaff}
    <li><a href={'ticketsystem/canned'|ezurl}>{'Canned replies'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/sla'|ezurl}>{'SLA and escalation'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/settings/queues'|ezurl}>{'Queues and categories'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/settings/mail'|ezurl}>{'Mail, files and ratings'|i18n( $tsI )}</a></li>
    <li><a href={'ticketsystem/mailin'|ezurl}>{'Mail-in'|i18n( $tsI )}</a></li>
    {if $tsImport}<li><a href={'ticketsystem/import'|ezurl}>{'Import'|i18n( $tsI )}</a></li>{/if}
    {/if}
</ul>
</div>
{/if}
{undef $tsI $tsStaff $tsReport $tsImport}
