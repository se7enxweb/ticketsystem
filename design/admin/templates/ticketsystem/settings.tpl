{* ticketsystem/settings/<section> in the administration (doc/settings.md): what the help desk runs with, read only
   (the settings are in ticketsystem.ini). Sections: queues, sla, mail, import. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='Help desk settings'|i18n( $tsI ) sub='Read only: change them in settings/override/ticketsystem.ini.append.php.'|i18n( $tsI )}
<ul class="a4-tabs" aria-label="{'Sections'|i18n( $tsI )|wash}">
{foreach hash( 'queues', 'Queues and statuses', 'sla', 'SLA and escalation', 'mail', 'Mail, files and ratings' ) as $tsK => $tsL}
    <li>{if $section|eq( $tsK )}<span class="is-current" aria-current="page">{$tsL|i18n( $tsI )}</span>{else}<a href={concat( 'ticketsystem/settings/', $tsK )|ezurl}>{$tsL|i18n( $tsI )}</a>{/if}</li>
{/foreach}
{if $ts_admin.import}<li>{if $section|eq( 'import' )}<span class="is-current" aria-current="page">{'Import'|i18n( $tsI )}</span>{else}<a href={'ticketsystem/settings/import'|ezurl}>{'Import'|i18n( $tsI )}</a>{/if}</li>{/if}
</ul>

{switch match=$section}
{case match='sla'}
<section class="a4-card" aria-labelledby="ts-s-sla-h" id="ts-settings-sla">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-s-sla-h">{'SLA and escalation'|i18n( $tsI )}</h2>
        <p class="a4-meta">{if $sla.enabled}{'On.'|i18n( $tsI )}{else}{'Off.'|i18n( $tsI )}{/if} {'Escalation at %pct % of a target; paused while: %paused.'|i18n( $tsI,, hash( '%pct', $sla.warn, '%paused', $sla.paused|implode( ', ' ) ) )|wash}</p></div>
    <div class="a4-card-body a4-stack">
    <dl class="a4-facts is-rows">
        <div><dt>{'Escalated now'|i18n( $tsI )}</dt><dd><a href={'ticketsystem/list/(sla)/escalated'|ezurl}>{$sla.escalated}</a></dd></div>
        <div><dt>{'Katalyst SLA entitlements'|i18n( $tsI )}</dt><dd>{if $sla.katalyst}{'used'|i18n( $tsI )}{else}{'not used'|i18n( $tsI )}{/if}</dd></div>
        <div><dt>{'Escalation raises the priority'|i18n( $tsI )}</dt><dd>{if $sla.raise}{'yes'|i18n( $tsI )}{else}{'no'|i18n( $tsI )}{/if}</dd></div>
    </dl>
    {foreach $sla.levels as $tsLvl}
    <div class="a4-table-wrap"><table class="a4-table is-compact">
        <caption>{$tsLvl.label|wash}{if $tsLvl.default} ({'default'|i18n( $tsI )}){/if}</caption>
        <thead><tr><th scope="col">{'Priority'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'First response (min)'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Resolution (min)'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $tsLvl.targets as $tsT}<tr><td>{$tsT.priority|i18n( $tsI )|wash}</td><td class="a4-num">{$tsT.first}</td><td class="a4-num">{$tsT.resolution}</td></tr>{/foreach}</tbody>
    </table></div>
    {/foreach}
    </div>
</section>
{/case}
{case match='mail'}
<div class="a4-grid is-wide">
<section class="a4-card" aria-labelledby="ts-s-mail-h" id="ts-settings-mail">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-s-mail-h">{'Reply by e-mail'|i18n( $tsI )}</h2></div>
    <div class="a4-card-body"><dl class="a4-facts is-rows">
        <div><dt>{'Status'|i18n( $tsI )}</dt><dd>{if $mail.enabled}<span class="a4-badge is-ok">{'on'|i18n( $tsI )}</span>{else}<span class="a4-badge">{'off'|i18n( $tsI )}</span>{/if}</dd></div>
        <div><dt>{'Sender'|i18n( $tsI )}</dt><dd>{$mail.sender|wash}</dd></div>
        <div><dt>{'Reply address'|i18n( $tsI )}</dt><dd>{if $mail.reply_address}{$mail.reply_address|wash}{else}<span class="a4-muted">{'none: the reference is in the text of the mails'|i18n( $tsI )}</span>{/if}</dd></div>
        <div><dt>{'Signed reference required'|i18n( $tsI )}</dt><dd>{if $mail.require_token}{'yes'|i18n( $tsI )}{else}{'no: the number in the subject is enough'|i18n( $tsI )}{/if}</dd></div>
        <div><dt>{'Mail without a ticket opens one'|i18n( $tsI )}</dt><dd>{if $mail.open_new}{'yes'|i18n( $tsI )}{else}{'no'|i18n( $tsI )}{/if}</dd></div>
        <div><dt>{'Own secret set'|i18n( $tsI )}</dt><dd>{if $mail.secret_set}{'yes'|i18n( $tsI )}{else}<span class="a4-badge is-warn">{'no: one derived from the installation'|i18n( $tsI )}</span>{/if}</dd></div>
    </dl></div>
</section>
<section class="a4-card" aria-labelledby="ts-s-files-h" id="ts-settings-files">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-s-files-h">{'Attachments'|i18n( $tsI )}</h2></div>
    <div class="a4-card-body"><dl class="a4-facts is-rows">
        <div><dt>{'Status'|i18n( $tsI )}</dt><dd>{if $attach.enabled}<span class="a4-badge is-ok">{'on'|i18n( $tsI )}</span>{else}<span class="a4-badge">{'off'|i18n( $tsI )}</span>{/if}</dd></div>
        <div><dt>{'Limits'|i18n( $tsI )}</dt><dd>{'%count files of at most %size MB'|i18n( $tsI,, hash( '%count', $attach.max_files, '%size', $attach.max_mb ) )|wash}</dd></div>
        <div><dt>{'Types'|i18n( $tsI )}</dt><dd>{$attach.types|wash}</dd></div>
        <div><dt>{'Virus scan'|i18n( $tsI )}</dt><dd>{if $attach.scan}{'on'|i18n( $tsI )}{else}<span class="a4-badge is-warn">{'no scanner set'|i18n( $tsI )}</span>{/if}</dd></div>
        <div><dt>{'Storage'|i18n( $tsI )}</dt><dd>{if $attach.outside}{'outside the installation'|i18n( $tsI )}{else}<span class="a4-badge is-warn">{'inside the installation (not served directly)'|i18n( $tsI )}</span>{/if}</dd></div>
    </dl></div>
</section>
<section class="a4-card" aria-labelledby="ts-s-csat-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-s-csat-h">{'Satisfaction ratings'|i18n( $tsI )}</h2></div>
    <div class="a4-card-body"><dl class="a4-facts is-rows">
        <div><dt>{'Status'|i18n( $tsI )}</dt><dd>{if $csat.enabled}{'on'|i18n( $tsI )}{else}{'off'|i18n( $tsI )}{/if}</dd></div>
        <div><dt>{'Asked in the mail of a solving reply'|i18n( $tsI )}</dt><dd>{if $csat.ask}{'yes'|i18n( $tsI )}{else}{'no'|i18n( $tsI )}{/if}</dd></div>
    </dl></div>
</section>
</div>
{/case}
{case match='import'}
<section class="a4-card" aria-labelledby="ts-s-import-h" id="ts-settings-import">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-s-import-h">{'WHMCS import'|i18n( $tsI )}</h2>
        <p class="a4-meta">{'Imported tickets are closed history. Run it on the page WHMCS import or on the command line (doc/import-whmcs.md).'|i18n( $tsI )}</p></div>
    <div class="a4-card-body">
    <ul class="a4-figures">
        <li class="a4-figure"><strong>{$import.ticket}</strong> <span>{'tickets imported'|i18n( $tsI )}</span></li>
        <li class="a4-figure"><strong>{$import.reply}</strong> <span>{'replies'|i18n( $tsI )}</span></li>
        <li class="a4-figure"><strong>{$import.note}</strong> <span>{'notes'|i18n( $tsI )}</span></li>
    </ul>
    <pre class="a4-pre">php extension/ticketsystem/bin/php/import_whmcs.php -s &lt;admin siteaccess&gt; --file=tickets.json
php extension/ticketsystem/bin/php/import_whmcs.php -s &lt;admin siteaccess&gt; --file=tickets.json --apply</pre>
    </div>
</section>
{/case}
{case}
<section class="a4-card" aria-labelledby="ts-s-queues-h" id="ts-settings-queues">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-s-queues-h">{'Queues'|i18n( $tsI )}</h2>
        <p class="a4-meta">{'Staff group "%group": %count members can be assigned.'|i18n( $tsI,, hash( '%group', $staff_group, '%count', $staff_count ) )|wash}{if $claim_on_reply} {'A staff reply to an unassigned ticket assigns it to the replier.'|i18n( $tsI )}{/if}</p></div>
    <div class="a4-table-wrap"><table class="a4-table">
        <thead><tr><th scope="col">{'Queue'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Not resolved'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Unassigned'|i18n( $tsI )}</th><th scope="col">{'Round-robin'|i18n( $tsI )}</th></tr></thead>
        <tbody>
        {foreach $queues as $tsQ}<tr><td><a href={concat( 'ticketsystem/list/(category)/', $tsQ.id )|ezurl}>{$tsQ.label|i18n( $tsI )|wash}</a></td>
            <td class="a4-num">{$tsQ.active}</td><td class="a4-num"><a href={concat( 'ticketsystem/list/(category)/', $tsQ.id, '/(assignee)/none' )|ezurl}>{$tsQ.unassigned}</a></td>
            <td>{if $tsQ.auto}<span class="a4-badge is-ok">{'on'|i18n( $tsI )}</span>{else}<span class="a4-muted">{'off'|i18n( $tsI )}</span>{/if}</td></tr>{/foreach}
        </tbody>
    </table></div>
</section>
<div class="a4-grid is-wide">
<section class="a4-card" aria-labelledby="ts-s-status-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-s-status-h">{'Statuses'|i18n( $tsI )} ({$status_set|wash})</h2></div>
    <div class="a4-card-body"><ul class="a4-badges">{foreach $statuses as $tsS}<li>{include uri='design:ticketsystem/parts/status.tpl' label=$tsS.label tone=$tsS.tone}</li>{/foreach}</ul></div>
</section>
<section class="a4-card" aria-labelledby="ts-s-prio-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-s-prio-h">{'Priorities'|i18n( $tsI )}</h2></div>
    <div class="a4-card-body"><ul class="a4-badges">{foreach $priorities as $tsP}<li><span class="a4-badge">{$tsP|i18n( $tsI )|wash}</span></li>{/foreach}</ul></div>
</section>
</div>
{/case}
{/switch}
{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
