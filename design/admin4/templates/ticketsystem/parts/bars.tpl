{* A count per label as bars (a4-meter). Input: $rows (label, count, pct in steps of 4), $title, $id. *}
<section class="a4-card" aria-labelledby="{$id|wash}-h" id="{$id|wash}">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="{$id|wash}-h">{$title|wash}</h2></div>
    <div class="a4-card-body">
    {if $rows|count}
    <table class="a4-table is-compact">
        <tbody>
        {foreach $rows as $tsR}
        <tr><th scope="row">{$tsR.label|i18n( 'extension/ticketsystem' )|wash}</th>
            <td><span class="a4-meter" role="img" aria-label="{$tsR.count}"><span class="a4-pct-{$tsR.pct}"></span></span></td>
            <td class="a4-num">{$tsR.count}</td></tr>
        {/foreach}
        </tbody>
    </table>
    {else}<p class="a4-empty">{'No tickets in this period.'|i18n( 'extension/ticketsystem' )}</p>{/if}
    </div>
</section>
