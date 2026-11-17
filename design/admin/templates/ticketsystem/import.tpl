{* ticketsystem/import in the administration (doc/import-whmcs.md, doc/views.md): the WHMCS import for staff. Counts
   only: no ticket, name or address is shown. Input: $export_present, $export_error, $export_date, $export_counts,
   $dry, $result, $labels, $imported, the form token. Only the shared admin components (.a4-*). *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/admin_open.tpl' title='WHMCS import'|i18n( $tsI )
         sub='Past WHMCS tickets become help desk tickets of their customers, as history. Customers are found by their WHMCS client id only. Nothing is mailed while importing, and a ticket is never imported twice.'|i18n( $tsI )}

<ul class="a4-figures" id="ts-import-done" aria-label="{'Imported so far'|i18n( $tsI )|wash}">
    <li class="a4-figure"><strong data-count="ticket">{$imported.ticket|wash}</strong> <span>{'tickets imported'|i18n( $tsI )}</span></li>
    <li class="a4-figure"><strong data-count="reply">{$imported.reply|wash}</strong> <span>{'replies imported'|i18n( $tsI )}</span></li>
    <li class="a4-figure"><strong data-count="note">{$imported.note|wash}</strong> <span>{'internal notes imported'|i18n( $tsI )}</span></li>
</ul>

<div class="a4-grid is-wide">
<section class="a4-card" aria-labelledby="ts-import-export-h" id="ts-import-export">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-import-export-h">{'The export'|i18n( $tsI )}</h2>
        {if $export_present}<p class="a4-meta">{'Exported from WHMCS:'|i18n( $tsI )} {$export_date|wash}</p>{/if}</div>
    <div class="a4-card-body a4-stack">
    {if $export_present}
    <dl class="a4-facts is-rows">
        {foreach $export_counts as $k => $v}<div><dt>{$k|wash}</dt><dd data-count="{$k|wash}">{$v|wash}</dd></div>{/foreach}
    </dl>
    {else}
    <p class="a4-notice is-info">{$export_error|i18n( $tsI )|wash}</p>
    {/if}
    <form class="a4-form is-wide" method="post" action={'ticketsystem/import'|ezurl} enctype="multipart/form-data" id="ts-import-upload">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <div class="a4-field a4-field-wide">
            <label for="ts-import-file">{'Export file of bin/php/whmcs_export.php (JSON)'|i18n( $tsI )}</label>
            <input class="a4-input" type="file" id="ts-import-file" name="ImportFile" accept=".json,application/json" />
        </div>
        <div class="a4-actions"><button type="submit" class="a4-btn" name="TicketImportUpload" value="1">{'Store the export'|i18n( $tsI )}</button></div>
    </form>
    </div>
</section>

{if $dry|count}
<section class="a4-card is-flush" aria-labelledby="ts-import-dry-h" id="ts-import-dry">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-import-dry-h">{'Dry run: what an import would do now'|i18n( $tsI )}</h2></div>
    <table class="a4-table is-compact">
        <thead><tr><th scope="col">{'Step'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Count'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $labels as $k => $label}<tr><td>{$label|i18n( $tsI )|wash}</td><td class="a4-num" data-count="{$k|wash}">{$dry[$k]|wash}</td></tr>{/foreach}</tbody>
    </table>
    <div class="a4-card-foot">
    <form method="post" action={'ticketsystem/import'|ezurl} id="ts-import-run">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <button type="submit" class="a4-btn a4-btn-primary" name="TicketImportRun" value="1"{if and( $dry.new_tickets|eq( 0 ), $dry.new_replies|eq( 0 ), $dry.new_notes|eq( 0 ) )} disabled="disabled"{/if}>{'Run the import'|i18n( $tsI )}</button>
    </form>
    </div>
</section>
{/if}
</div>

{if $result|count}
<section class="a4-card is-flush is-ok" aria-labelledby="ts-import-result-h" id="ts-import-result">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-import-result-h">{'This run'|i18n( $tsI )}</h2></div>
    <table class="a4-table is-compact">
        <thead><tr><th scope="col">{'Step'|i18n( $tsI )}</th><th scope="col" class="a4-num">{'Count'|i18n( $tsI )}</th></tr></thead>
        <tbody>{foreach $labels as $k => $label}<tr><td>{$label|i18n( $tsI )|wash}</td><td class="a4-num" data-count="{$k|wash}">{$result[$k]|wash}</td></tr>{/foreach}</tbody>
    </table>
</section>
{/if}

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI}
