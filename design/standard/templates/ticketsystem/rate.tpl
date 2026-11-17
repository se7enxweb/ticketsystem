{* ticketsystem/rate/<token>/<score> (doc/csat.md): the rating page of the mail "How did we do?", without sign-in.
   Input: $state (form, thanks, refused), $reason_text, $number, $token, $score, $comment, $labels, the form token,
   the frame's variables. Shows the ticket's number only. Mobile first, light and dark (the frame's tokens); no inline
   CSS. *}
{def $tsI = 'extension/ticketsystem'}
{include uri='design:ticketsystem/parts/open.tpl'}
<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1">{'How did we do?'|i18n( $tsI )}</h1>
        {if $number}<p class="ktl-lede ts-lede">{'Ticket %number'|i18n( $tsI,, hash( '%number', $number ) )|wash}</p>{/if}
    </div>
</div>
{if $state|eq( 'thanks' )}
<section class="ktl-card ktl-empty ts-card ts-empty" id="ts-rate-thanks">
    <span class="ktl-empty-icon ts-empty-icon" aria-hidden="true"><i class="fa-solid fa-heart"></i></span>
    <h2 class="ktl-h2 ts-h2">{'Thank you for your rating.'|i18n( $tsI )}</h2>
    <p>{'It helps us to get better. You can close this page.'|i18n( $tsI )}</p>
</section>
{elseif $state|eq( 'refused' )}
<section class="ktl-card ktl-empty ts-card ts-empty" id="ts-rate-refused">
    <p>{$reason_text|i18n( $tsI )|wash}</p>
    {if $number}<p class="ktl-actions ts-actions"><a class="ktl-btn ts-btn" href={concat( 'ticketsystem/view/', $number )|ezurl}>{'Open the ticket'|i18n( $tsI )}</a></p>{/if}
</section>
{else}
<section class="ktl-card ts-card" aria-labelledby="ts-rate-h" id="ts-rate">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-rate-h">{'Your rating'|i18n( $tsI )}</h2></div>
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/rate/', $token, '/', $score )|ezurl} id="ts-rate-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <fieldset class="ktl-field ts-field ts-rate-scores"><legend>{'How happy are you with our help?'|i18n( $tsI )}</legend>
        {foreach $labels as $tsS => $tsL}
            <label class="ts-rate-score" for="ts-rate-{$tsS}"><input type="radio" id="ts-rate-{$tsS}" name="TicketRating" value="{$tsS}" required{if $score|eq( $tsS )} checked="checked"{/if} /> <span>{$tsS} &middot; {$tsL|i18n( $tsI )|wash}</span></label>
        {/foreach}
        </fieldset>
        <div class="ktl-field ts-field"><label for="ts-rate-comment">{'Comment (optional)'|i18n( $tsI )}</label>
            <textarea class="ktl-input ts-input ts-textarea" id="ts-rate-comment" name="TicketRatingComment" rows="3" maxlength="1000">{$comment|wash}</textarea></div>
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="TicketRate" value="1" id="ts-rate-send">{'Send the rating'|i18n( $tsI )}</button></p>
        <p class="ktl-muted ktl-small ts-muted ts-small">{'The rating link works once. No sign-in needed.'|i18n( $tsI )}</p>
    </form>
</section>
{/if}
{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI}
