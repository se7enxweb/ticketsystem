{* ticketsystem/view/<number> (doc/views.md): one ticket, its posts oldest first, the reply form and the status actions.
   Input: $ticket (ticketsystemView::row), $posts (ticketsystemService::posts), $may_reply, $may_resolve, $may_reopen,
   $is_staff, $staff_choices, $staff_default, $draft, $field_errors, $following, the form token, the frame's
   variables. No inline CSS. *}
{def $tsI = 'extension/ticketsystem'
     $last = $posts|count|dec}
{include uri='design:ticketsystem/parts/open.tpl'}

<p class="ktl-crumbs ts-crumbs"><a href={'ticketsystem/list'|ezurl}><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {'All tickets'|i18n( $tsI )}</a></p>
<div class="ktl-pagehead ts-pagehead">
    <div>
        <h1 class="ktl-h1 ts-h1" id="ts-subject">{$ticket.subject|wash}</h1>
        <p class="ktl-lede ts-lede"><span class="ktl-nowrap" id="ts-number">{$ticket.number|wash}</span> &middot; {'opened %date'|i18n( $tsI,, hash( '%date', $ticket.created|l10n( 'shortdatetime' ) ) )|wash}</p>
    </div>
    <p class="ktl-actions ts-actions" id="ts-status">{include uri='design:ticketsystem/parts/status.tpl' label=$ticket.status_label tone=$ticket.status_tone}</p>
</div>

<div class="ktl-grid is-2-1 ts-grid">
<div class="ts-thread">
<section class="ktl-card ts-card" aria-labelledby="ts-thread-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-thread-h"><i class="fa-solid fa-comments ktl-icon" aria-hidden="true"></i>{'Conversation'|i18n( $tsI )}</h2><span class="ktl-muted ts-muted">{'%count posts'|i18n( $tsI,, hash( '%count', $posts|count ) )|wash}</span></div>
    <ol class="ktl-rows ts-posts">
    {foreach $posts as $i => $p}
        <li class="ts-post{if $p.is_staff} is-staff{/if}{if $p.is_self} is-self{/if}" id="{if $i|eq( $last )}ts-latest{else}ts-post-{$p.id}{/if}">
            <p class="ts-post-head"><strong>{if $p.is_self}{'You'|i18n( $tsI )}{else}{$p.author|wash}{/if}</strong>
                {if $p.is_staff} <span class="ktl-badge ts-badge">{'Support'|i18n( $tsI )}</span>{/if}
                <span class="ktl-muted ktl-small ts-muted ts-small"> &middot; <time datetime="{$p.created|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$p.created|l10n( 'shortdatetime' )}</time></span></p>
            <div class="ts-post-body">{if $p.attribute}{attribute_view_gui attribute=$p.attribute}{/if}</div>
            {if is_set( $attachments.post[$p.id] )}{include uri='design:ticketsystem/parts/files.tpl' files=$attachments.post[$p.id]}{/if}
        </li>
    {/foreach}
    </ol>
</section>

{if $is_staff}
<section class="ktl-card ts-card ts-notes" aria-labelledby="ts-notes-h" id="ts-notes">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-notes-h"><i class="fa-solid fa-lock ktl-icon" aria-hidden="true"></i>{'Internal notes'|i18n( $tsI )}</h2><span class="ktl-badge ts-badge">{'staff only'|i18n( $tsI )}</span></div>
    <p class="ktl-muted ts-muted">{'Customers never see a note: it is not shown to them, never mailed and never in their search.'|i18n( $tsI )}</p>
    {if $notes|count}
    <ol class="ktl-rows ts-posts">
    {foreach $notes as $n}
        <li class="ts-post ts-note" id="ts-note-{$n.id}"><p class="ts-post-head"><strong>{$n.author|wash}</strong> <span class="ktl-muted ktl-small ts-muted ts-small">&middot; {$n.created|l10n( 'shortdatetime' )}</span></p>
            <div class="ts-post-body">{foreach $n.paragraphs as $tsPara}<p>{foreach $tsPara as $tsN => $tsLine}{if $tsN|gt( 0 )}<br />{/if}{$tsLine|wash}{/foreach}</p>{/foreach}</div>
            {if is_set( $attachments.note[$n.id] )}{include uri='design:ticketsystem/parts/files.tpl' files=$attachments.note[$n.id]}{/if}</li>
    {/foreach}
    </ol>
    {/if}
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-note-form" enctype="multipart/form-data">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <div class="ktl-field ts-field"><label for="ts-note-body">{'New internal note'|i18n( $tsI )}</label>
            <textarea class="ktl-input ts-input ts-textarea" id="ts-note-body" name="TicketNoteBody" rows="3">{$note_draft|wash}</textarea>
            {if is_set( $field_errors.note )}<p class="ktl-field-error ts-field-error">{$field_errors.note|i18n( $tsI )|wash}</p>{/if}</div>
        {if $attach.enabled}<div class="ktl-field ts-field"><label for="ts-note-files">{'Files for staff'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="file" id="ts-note-files" name="NoteFiles[]" multiple="multiple" accept="{$attach.accept|wash}" /></div>{/if}
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketNote" value="1" id="ts-add-note">{'Save the note'|i18n( $tsI )}</button></p>
    </form>
</section>
{/if}

{if $may_reply}
<section class="ktl-card ts-card" aria-labelledby="ts-reply-h" id="ts-reply">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-reply-h"><i class="fa-solid fa-reply ktl-icon" aria-hidden="true"></i>{'Reply'|i18n( $tsI )}</h2></div>
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-reply-form" enctype="multipart/form-data">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if and( $is_staff, $canned|count )}
        {* a plain select and a submit button that work without JavaScript; ticketsystem-canned.js inserts the text at
           the cursor instead and chooses the macro's status (data-ts-canned-*) *}
        {ezscript_require( array( 'ticketsystem-canned.js' ) )}
        <div class="ktl-field ts-field" data-ts-canned data-ts-canned-target="ts-body" data-ts-canned-status="ts-reply-status">
            <label for="ts-canned">{'Canned reply'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-canned" name="TicketCanned"><option value="0">{'None'|i18n( $tsI )}</option>
            {foreach $canned as $tsC}<option value="{$tsC.id}" data-ts-text="{$tsC.text|wash}" data-ts-status="{$tsC.set_status|wash}"{if $tsC.id|eq( $canned_chosen )} selected="selected"{/if}>{$tsC.title|wash}{if $tsC.macro} ({'macro'|i18n( $tsI )}){/if}</option>{/foreach}</select>
            <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-ghost ts-btn is-ghost" name="TicketInsertCanned" value="1" id="ts-insert-canned" data-ts-canned-insert>{'Insert the text'|i18n( $tsI )}</button></p>
        </div>
        {/if}
        <div class="ktl-field ts-field">
            <label for="ts-body">{'Your reply'|i18n( $tsI )}</label>
            <textarea class="ktl-input ts-input ts-textarea{if is_set( $field_errors.body )} is-invalid{/if}" id="ts-body" name="TicketBody" rows="6"{if is_set( $field_errors.body )} aria-invalid="true" aria-describedby="ts-body-error"{/if}>{$draft|wash}</textarea>
            {if is_set( $field_errors.body )}<p class="ktl-field-error ts-field-error" id="ts-body-error">{$field_errors.body|i18n( $tsI )|wash}</p>{/if}
        </div>
        {if $attach.enabled}
        <div class="ktl-field ts-field">
            <label for="ts-files">{'Attachments'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="file" id="ts-files" name="TicketFiles[]" multiple="multiple" accept="{$attach.accept|wash}" />
            <p class="ktl-muted ktl-small ts-muted ts-small">{'Up to %count files, at most %size MB each: %types.'|i18n( $tsI,, hash( '%count', $attach.max_files, '%size', $attach.max_mb, '%types', $attach.types ) )|wash}</p>
        </div>
        {/if}
        {if $is_staff}
        <div class="ktl-field ts-field">
            <label for="ts-reply-status">{'Status after this reply'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-reply-status" name="TicketStatus">
            {foreach $staff_choices as $c}<option value="{$c.id|wash}"{if $c.id|eq( $staff_default )} selected="selected"{/if}>{$c.label|i18n( $tsI )|wash}</option>{/foreach}
            </select>
        </div>
        {/if}
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="TicketReply" value="1" id="ts-send">{'Send the reply'|i18n( $tsI )}</button></p>
    </form>
</section>
{else}
<section class="ktl-card ktl-empty is-inline ts-card ts-empty" id="ts-closed">
    <p class="ktl-empty-line">{'This ticket is closed. If you need more help, open a new ticket and mention %number.'|i18n( $tsI,, hash( '%number', $ticket.number ) )|wash}</p>
    <p class="ktl-actions ts-actions"><a class="ktl-btn ts-btn" href={'ticketsystem/new'|ezurl}>{'Open a new ticket'|i18n( $tsI )}</a></p>
</section>
{/if}
</div>

<div class="ts-side">
<section class="ktl-card ts-card" aria-labelledby="ts-facts-h">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-facts-h"><i class="fa-solid fa-circle-info ktl-icon" aria-hidden="true"></i>{'Details'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts is-compact ts-facts">
        <dt>{'Ticket'|i18n( $tsI )}</dt><dd>{$ticket.number|wash}</dd>
        <dt>{'Status'|i18n( $tsI )}</dt><dd>{$ticket.status_label|i18n( $tsI )|wash}</dd>
        {if $ticket.category_label}<dt>{'Category'|i18n( $tsI )}</dt><dd>{$ticket.category_label|i18n( $tsI )|wash}</dd>{/if}
        {if $ticket.priority_label}<dt>{'Priority'|i18n( $tsI )}</dt><dd>{$ticket.priority_label|i18n( $tsI )|wash}</dd>{/if}
        {if $is_staff}<dt>{'Account'|i18n( $tsI )}</dt><dd>{$ticket.account_label|wash}</dd>{/if}
        <dt>{'Last update'|i18n( $tsI )}</dt><dd>{$ticket.modified|l10n( 'shortdatetime' )}</dd>
        {if $is_staff|not}{include uri='design:ticketsystem/parts/sla_hint.tpl' ticket_id=$ticket.id}{/if}
        <dt>{'E-mail copies'|i18n( $tsI )}</dt><dd>{if $following}{'You get every post by e-mail.'|i18n( $tsI )}{else}{'You do not follow this ticket.'|i18n( $tsI )}{/if}</dd>
    </dl>
    {if or( $may_resolve, $may_reopen, $is_staff )}
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-status-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if $is_staff}
        <div class="ktl-field ts-field">
            <label for="ts-set-status">{'Change the status'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-set-status" name="TicketStatus">
            {foreach $staff_choices as $c}<option value="{$c.id|wash}"{if $c.id|eq( $ticket.status )} selected="selected"{/if}>{$c.label|i18n( $tsI )|wash}</option>{/foreach}
            </select>
        </div>
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketSetStatus" value="1" id="ts-set">{'Save the status'|i18n( $tsI )}</button></p>
        {elseif $may_resolve}
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketResolve" value="1" id="ts-resolve"><i class="fa-solid fa-check" aria-hidden="true"></i> {'Mark as solved'|i18n( $tsI )}</button></p>
        {elseif $may_reopen}
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketReopen" value="1" id="ts-reopen"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> {'Reopen the ticket'|i18n( $tsI )}</button></p>
        {/if}
    </form>
    {/if}
</section>

{if $is_staff}
<section class="ktl-card ts-card" aria-labelledby="ts-work-h" id="ts-work">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-work-h"><i class="fa-solid fa-user-gear ktl-icon" aria-hidden="true"></i>{'Work on it'|i18n( $tsI )}</h2></div>
    <dl class="ktl-facts is-compact ts-facts">
        <dt>{'Assigned to'|i18n( $tsI )}</dt><dd id="ts-assignee">{if $ticket.assignee_id}{$ticket.assignee|wash}{else}{'nobody'|i18n( $tsI )}{/if}</dd>
        {if $sla}<dt>{'SLA'|i18n( $tsI )}</dt><dd>{$sla.level_label|wash}: {$sla.worst_label|i18n( $tsI )|wash}{if $ticket.escalation|gt( 0 )} &middot; {'escalated'|i18n( $tsI )}{/if} &middot; <a href="#ts-sla">{'details'|i18n( $tsI )}</a></dd>{/if}
    </dl>
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-work-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if $ticket.assignee_id|ne( $me )}<p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-primary ts-btn is-primary" name="TicketClaim" value="1" id="ts-claim">{'Take it'|i18n( $tsI )}</button></p>{/if}
        <div class="ktl-field ts-field"><label for="ts-assignee-select">{'Assign to'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-assignee-select" name="TicketAssignee"><option value="none">{'Nobody'|i18n( $tsI )}</option>
            {foreach $staff as $tsK => $tsL}<option value="{$tsK}"{if $ticket.assignee_id|eq( $tsK )} selected="selected"{/if}>{$tsL|wash}</option>{/foreach}</select>
            <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketAssign" value="1">{'Assign'|i18n( $tsI )}</button></p></div>
        <div class="ktl-field ts-field"><label for="ts-priority-select">{'Priority'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-priority-select" name="TicketPriorityValue">{foreach $priorities as $tsK => $tsL}<option value="{$tsK|wash}"{if $ticket.priority|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select>
            <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketPriority" value="1">{'Set priority'|i18n( $tsI )}</button></p></div>
        <div class="ktl-field ts-field"><label for="ts-queue-select">{'Queue'|i18n( $tsI )}</label>
            <select class="ktl-input ts-input" id="ts-queue-select" name="TicketQueueValue">{foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $ticket.category|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}</select>
            <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketQueue" value="1">{'Move'|i18n( $tsI )}</button></p></div>
        {if and( $ticket.escalation|lt( 2 ), $ticket.done|not )}<p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-ghost ts-btn is-ghost" name="TicketEscalate" value="1" id="ts-escalate">{'Escalate'|i18n( $tsI )}</button></p>{/if}
    </form>
</section>
{include uri='design:ticketsystem/parts/sla.tpl' sla=$sla ticket=$ticket}
{/if}

{* 1.2: e-mail copies: following and CC addresses (doc/watchers.md) *}
<section class="ktl-card ts-card" aria-labelledby="ts-watch-h" id="ts-watch">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-watch-h"><i class="fa-solid fa-envelope ktl-icon" aria-hidden="true"></i>{'E-mail copies'|i18n( $tsI )}</h2></div>
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-follow-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if $following}<p>{'You get every post of this ticket by e-mail.'|i18n( $tsI )}</p>
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn is-ghost ts-btn is-ghost" name="TicketUnfollow" value="1" id="ts-unfollow">{'Stop the e-mails'|i18n( $tsI )}</button></p>
        {else}<p>{'You do not get the posts of this ticket by e-mail.'|i18n( $tsI )}</p>
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketFollow" value="1" id="ts-follow">{'Follow by e-mail'|i18n( $tsI )}</button></p>{/if}
    </form>
    {if or( $cc|count, $may_cc )}
    <h3 class="ts-h3" id="ts-cc-h">{'Copies to others (CC)'|i18n( $tsI )}</h3>
    {if $cc|count}
    <ul class="ts-cc" id="ts-cc" aria-labelledby="ts-cc-h">
    {foreach $cc as $c}<li>{$c.email|wash}{if $may_cc}
        <form class="ts-inline-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl}><input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" /><input type="hidden" name="TicketCcID" value="{$c.id}" />
        <button type="submit" class="ktl-btn is-ghost ts-btn is-ghost" name="TicketCcRemove" value="1" aria-label="{'Remove %address'|i18n( $tsI,, hash( '%address', $c.email ) )|wash}">{'Remove'|i18n( $tsI )}</button></form>{/if}</li>{/foreach}
    </ul>
    {/if}
    {if and( $may_cc, $cc|count|lt( $max_cc ) )}
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-cc-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <div class="ktl-field ts-field"><label for="ts-cc-address">{'Add an e-mail address'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="email" id="ts-cc-address" name="TicketCcAddress" maxlength="254" autocomplete="email" aria-describedby="ts-cc-help" />
            <p class="ts-small ts-muted" id="ts-cc-help">{'It gets a copy of every new post. It cannot open the ticket, and answers from it are not added.'|i18n( $tsI )}</p></div>
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketCcAdd" value="1" id="ts-cc-add">{'Add'|i18n( $tsI )}</button></p>
    </form>
    {/if}
    {/if}
</section>

{if or( $may_rate, and( $rating.enabled, $rating.score|gt( 0 ) ) )}
<section class="ktl-card ts-card" aria-labelledby="ts-rate-h" id="ts-rate">
    <div class="ktl-card-head ts-card-head"><h2 class="ktl-h2 ts-h2" id="ts-rate-h"><i class="fa-solid fa-star ktl-icon" aria-hidden="true"></i>{'How did we do?'|i18n( $tsI )}</h2></div>
    {if $rating.score|gt( 0 )}<p id="ts-rating">{'Rated %score of 5'|i18n( $tsI,, hash( '%score', $rating.score ) )|wash}{if $rating.comment} &middot; {$rating.comment|wash}{/if}</p>{/if}
    {if $may_rate}
    <form class="ktl-form ts-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-rate-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <fieldset class="ktl-field ts-field ts-stars"><legend>{'Your rating (5 = very good)'|i18n( $tsI )}</legend>
        {foreach array( 5, 4, 3, 2, 1 ) as $tsS}<label><input type="radio" name="TicketRating" value="{$tsS}"{if $rating.score|eq( $tsS )} checked="checked"{/if} /> {$tsS}</label> {/foreach}
        </fieldset>
        <div class="ktl-field ts-field"><label for="ts-rate-comment">{'Comment (optional)'|i18n( $tsI )}</label>
            <input class="ktl-input ts-input" type="text" id="ts-rate-comment" name="TicketRatingComment" maxlength="1000" value="{$rating.comment|wash}" /></div>
        <p class="ktl-actions ts-actions"><button type="submit" class="ktl-btn ts-btn" name="TicketRate" value="1" id="ts-rate-send">{'Send the rating'|i18n( $tsI )}</button></p>
    </form>
    {/if}
</section>
{/if}
</div>
</div>

{include uri='design:ticketsystem/parts/close.tpl'}
{undef $tsI $last}
