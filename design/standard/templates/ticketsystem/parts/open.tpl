{* The frame of a help desk view (ticketsystemChrome, doc/design.md): the Katalyst customer console for a user with a
   Katalyst customer (its components only, katalyst.css), else the extension's own frame (ticketsystem.css, ts-*).
   Every element carries both class sets; each stylesheet styles only its own frame. No inline CSS.
   Input: $ts_chrome, $ts_messages, $ts_errors. Closed by ticketsystem/parts/close.tpl. *}
{if $ts_chrome|eq( 'katalyst' )}
{include uri='design:katalyst/parts/chrome_open.tpl'}
{else}
<div class="ts-app" data-ts-app>
<div class="ts-main">
{/if}
{if $ts_errors|count}
<div class="ktl-notice is-bad ts-notice is-bad" role="alert">{foreach $ts_errors as $e}<p>{$e|i18n( 'extension/ticketsystem' )|wash}</p>{/foreach}</div>
{/if}
{if $ts_messages|count}
<div class="ktl-notice is-ok ts-notice is-ok" role="status">{foreach $ts_messages as $m}<p>{$m|i18n( 'extension/ticketsystem' )|wash}</p>{/foreach}</div>
{/if}
