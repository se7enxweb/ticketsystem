{* One SLA target (ticketsystemSla::target() plus text). Input: $target (state, due, text). *}
{switch match=$target.state}
{case match='none'}<span class="a4-muted">{'no target'|i18n( 'extension/ticketsystem' )}</span>{/case}
{case match='met'}<span class="a4-badge is-ok">{'met'|i18n( 'extension/ticketsystem' )}</span>{/case}
{case match='breached'}<span class="a4-badge is-bad">{'missed'|i18n( 'extension/ticketsystem' )}</span> <span class="a4-meta">{$target.text|i18n( 'extension/ticketsystem' )|wash}</span>{/case}
{case match='warn'}<span class="a4-badge is-warn">{'due soon'|i18n( 'extension/ticketsystem' )}</span> <span class="a4-meta">{$target.text|i18n( 'extension/ticketsystem' )|wash}</span>{/case}
{case match='paused'}<span class="a4-badge">{'paused'|i18n( 'extension/ticketsystem' )}</span> <span class="a4-meta">{$target.text|i18n( 'extension/ticketsystem' )|wash}</span>{/case}
{case}<span class="a4-badge is-info">{'on track'|i18n( 'extension/ticketsystem' )}</span> <span class="a4-meta">{$target.text|i18n( 'extension/ticketsystem' )|wash}</span>{/case}
{/switch}{if $target.due} <time class="a4-meta" datetime="{$target.due|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$target.due|l10n( 'shortdatetime' )}</time>{/if}
