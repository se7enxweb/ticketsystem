{* ticketsystem/import (doc/import-whmcs.md): the WHMCS import for staff. A plain standard page the administration
   designs restyle. Counts only: no ticket, name or address is shown.
   Input: $export_present, $export_error, $export_date, $export_counts, $dry, $result, $labels, $imported, $ts_token,
   $ts_token_field, the frame's variables. No inline CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}

<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1">{'WHMCS import'|i18n( $tsI )}</h1>
        <p class="ktl-lede ts-lede">{'Past WHMCS tickets become help desk tickets of their customers, as history. Customers are found by their WHMCS client id only. Nothing is mailed while importing, and a ticket is never imported twice.'|i18n( $tsI )}</p>
    </div>
</div>

<section class="ktl-card ts-card" aria-labelledby="ts-import-export-h" id="ts-import-export">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-import-export-h">{'The export'|i18n( $tsI )}</h2></div>
    {if $export_present}
    <p class="ts-muted">{'Exported from WHMCS:'|i18n( $tsI )} {$export_date|wash}</p>
    <dl class="ktl-facts ts-facts">
        {foreach $export_counts as $k => $v}<dt>{$k|wash}</dt><dd data-count="{$k|wash}">{$v|wash}</dd>{/foreach}
    </dl>
    {else}
    <p class="ts-muted">{$export_error|i18n( $tsI )|wash}</p>
    {/if}
    <form class="ktl-form ts-form" method="post" action={'ticketsystem/import'|ezurl} enctype="multipart/form-data" id="ts-import-upload">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <div class="ktl-field ts-field">
            <label for="ts-import-file">{'Export file of bin/php/whmcs_export.php (JSON)'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="file" id="ts-import-file" name="ImportFile" accept=".json,application/json" />
        </div>
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketImportUpload" value="1">{'Store the export'|i18n( $tsI )}</button></p>
    </form>
</section>

{if $result|count}
<section class="ktl-card ts-card" aria-labelledby="ts-import-result-h" id="ts-import-result">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-import-result-h">{'This run'|i18n( $tsI )}</h2></div>
    <table class="ktl-table ts-table">
        <tbody>
        {foreach $labels as $k => $label}<tr><th scope="row">{$label|i18n( $tsI )|wash}</th><td data-count="{$k|wash}">{$result[$k]|wash}</td></tr>{/foreach}
        </tbody>
    </table>
</section>
{/if}

{if $dry|count}
<section class="ktl-card ts-card" aria-labelledby="ts-import-dry-h" id="ts-import-dry">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-import-dry-h">{'Dry run: what an import would do now'|i18n( $tsI )}</h2></div>
    <table class="ktl-table ts-table">
        <tbody>
        {foreach $labels as $k => $label}<tr><th scope="row">{$label|i18n( $tsI )|wash}</th><td data-count="{$k|wash}">{$dry[$k]|wash}</td></tr>{/foreach}
        </tbody>
    </table>
    <form class="ktl-form ts-form" method="post" action={'ticketsystem/import'|ezurl} id="ts-import-run">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <p class="ktl-actions ts-actions">
            <button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="TicketImportRun" value="1"{if and( $dry.new_tickets|eq( 0 ), $dry.new_replies|eq( 0 ), $dry.new_notes|eq( 0 ) )} disabled="disabled"{/if}>{'Run the import'|i18n( $tsI )}</button>
        </p>
    </form>
</section>
{/if}

<section class="ktl-card ts-card" aria-labelledby="ts-import-done-h" id="ts-import-done">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-import-done-h">{'Imported so far'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts ts-facts">
        <dt>{'Tickets'|i18n( $tsI )}</dt><dd data-count="ticket">{$imported.ticket|wash}</dd>
        <dt>{'Replies'|i18n( $tsI )}</dt><dd data-count="reply">{$imported.reply|wash}</dd>
        <dt>{'Internal notes'|i18n( $tsI )}</dt><dd data-count="note">{$imported.note|wash}</dd>
    </dl>
</section>

{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
