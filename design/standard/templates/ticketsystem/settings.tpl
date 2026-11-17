{* ticketsystem/settings on the site: a short summary; the administration (design admin4) shows every section. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead"><div><h1 class="ktl-h1 ts-h1">{'Help desk settings'|i18n( $tsI )}</h1>
    <p class="ktl-lede ts-lede">{'Read only: change them in settings/override/ticketsystem.ini.append.php.'|i18n( $tsI )}</p></div></div>
<section class="ktl-card ts-card">
<dl class="ktl-facts ts-facts">
    <dt>{'Queues'|i18n( $tsI )}</dt><dd>{foreach $queues as $tsQ}{$tsQ.label|i18n( $tsI )|wash} ({$tsQ.active}){delimiter}, {/delimiter}{/foreach}</dd>
    <dt>{'SLA'|i18n( $tsI )}</dt><dd>{if $sla.enabled}{'on'|i18n( $tsI )}{else}{'off'|i18n( $tsI )}{/if}</dd>
    <dt>{'Reply by e-mail'|i18n( $tsI )}</dt><dd>{if $mail.enabled}{'on'|i18n( $tsI )}{else}{'off'|i18n( $tsI )}{/if}</dd>
    <dt>{'Attachments'|i18n( $tsI )}</dt><dd>{if $attach.enabled}{$attach.types|wash}{else}{'off'|i18n( $tsI )}{/if}</dd>
</dl>
</section>
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
