{* ticketsystem/view/<number> in the administration (design admin4, doc/views.md): the ticket's details and the staff's
   work on it (assign or claim, priority, queue, escalation, SLA), the conversation with its files, the internal notes
   (staff only, never shown to customers), the reply with canned replies, the rating and the history.
   Input: see modules/ticketsystem/view.php. Only shared admin components (.a4-*) and .a4-ticketsystem-post; no
   inline CSS. *}
{def $tsI = 'extension/ticketsystem'
     $tsLast = $posts|count|dec}
{include uri='design:ticketsystem/parts/admin_open.tpl' title=$ticket.subject sub=concat( $ticket.number, ' · ', $ticket.category_label|i18n( $tsI ) )}

<div class="a4-grid is-wide">

<section class="a4-card" aria-labelledby="ts-a4-facts-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-facts-h">{'Ticket'|i18n( $tsI )} <span id="ts-number">{$ticket.number|wash}</span></h2>
        <div class="a4-card-actions"><a class="a4-btn a4-btn-small a4-btn-ghost" href={'ticketsystem/list'|ezurl}>{'All tickets'|i18n( $tsI )}</a></div></div>
    <div class="a4-card-body">
    <dl class="a4-facts is-rows" id="ts-facts">
        <div><dt>{'Status'|i18n( $tsI )}</dt><dd id="ts-status">{include uri='design:ticketsystem/parts/status.tpl' label=$ticket.status_label tone=$ticket.status_tone}</dd></div>
        <div><dt>{'Priority'|i18n( $tsI )}</dt><dd id="ts-priority-value">{if $ticket.priority_high}<span class="a4-badge is-bad">{$ticket.priority_label|i18n( $tsI )|wash}</span>{else}{$ticket.priority_label|i18n( $tsI )|wash}{/if}</dd></div>
        <div><dt>{'Queue'|i18n( $tsI )}</dt><dd id="ts-queue-value">{$ticket.category_label|i18n( $tsI )|wash}</dd></div>
        {if $is_staff}
        <div><dt>{'Assigned to'|i18n( $tsI )}</dt><dd id="ts-assignee">{if $ticket.assignee_id}{$ticket.assignee|wash}{else}<span class="a4-muted">{'nobody'|i18n( $tsI )}</span>{/if}</dd></div>
        <div><dt>{'Account'|i18n( $tsI )}</dt><dd>{$ticket.account_label|wash}</dd></div>
        <div><dt>{'Opened by'|i18n( $tsI )}</dt><dd>{$opener|wash}</dd></div>
        {/if}
        <div><dt>{'Opened'|i18n( $tsI )}</dt><dd>{$ticket.created|l10n( 'shortdatetime' )}{if $ticket.source|ne( 'web' )} <span class="a4-badge">{$ticket.source|wash}</span>{/if}</dd></div>
        <div><dt>{'Last post'|i18n( $tsI )}</dt><dd>{$ticket.last_post|l10n( 'shortdatetime' )} <span class="a4-meta">{if $ticket.last_post_staff}{'staff'|i18n( $tsI )}{else}{'customer'|i18n( $tsI )}, {'waiting'|i18n( $tsI )} {$ticket.waiting|wash}{/if}</span></dd></div>
        <div><dt>{'E-mail copies'|i18n( $tsI )}</dt><dd>{if $following}{'You get every post by e-mail.'|i18n( $tsI )}{else}{'You do not follow this ticket.'|i18n( $tsI )}{/if}</dd></div>
    </dl>
    </div>
    {if or( $may_resolve, $may_reopen, $is_staff )}
    <div class="a4-card-foot">
    <form class="a4-actions" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-status-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if $is_staff}
        <label class="a4-sr-only" for="ts-set-status">{'Change the status'|i18n( $tsI )}</label>
        <select class="a4-input" id="ts-set-status" name="TicketStatus">
        {foreach $staff_choices as $c}<option value="{$c.id|wash}"{if $c.id|eq( $ticket.status )} selected="selected"{/if}>{$c.label|i18n( $tsI )|wash}</option>{/foreach}
        </select>
        <button type="submit" class="a4-btn a4-btn-small" name="TicketSetStatus" value="1" id="ts-set">{'Set status'|i18n( $tsI )}</button>
        {elseif $may_resolve}
        <button type="submit" class="a4-btn a4-btn-small" name="TicketResolve" value="1" id="ts-resolve">{'Mark as solved'|i18n( $tsI )}</button>
        {elseif $may_reopen}
        <button type="submit" class="a4-btn a4-btn-small" name="TicketReopen" value="1" id="ts-reopen">{'Reopen the ticket'|i18n( $tsI )}</button>
        {/if}
    </form>
    </div>
    {/if}
</section>

{if $is_staff}
<section class="a4-card" aria-labelledby="ts-a4-work-h" id="ts-work">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-work-h">{'Work on it'|i18n( $tsI )}</h2>
        <p class="a4-meta">{'Changes are recorded in the history; they send no mail to the customer.'|i18n( $tsI )}</p></div>
    <div class="a4-card-body a4-stack">
        <form class="a4-actions" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-assign-form">
            <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
            {if $ticket.assignee_id|ne( $me )}<button type="submit" class="a4-btn a4-btn-small a4-btn-primary" name="TicketClaim" value="1" id="ts-claim">{'Take it'|i18n( $tsI )}</button>{/if}
            <label class="a4-sr-only" for="ts-assignee-select">{'Assign to'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-assignee-select" name="TicketAssignee">
                <option value="none"{if $ticket.assignee_id|eq( 0 )} selected="selected"{/if}>{'Nobody'|i18n( $tsI )}</option>
                {foreach $staff as $tsK => $tsL}<option value="{$tsK}"{if $ticket.assignee_id|eq( $tsK )} selected="selected"{/if}>{$tsL|wash}</option>{/foreach}
            </select>
            <button type="submit" class="a4-btn a4-btn-small" name="TicketAssign" value="1" id="ts-assign">{'Assign'|i18n( $tsI )}</button>
        </form>
        <form class="a4-actions" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-priority-form">
            <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
            <label class="a4-sr-only" for="ts-priority-select">{'Priority'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-priority-select" name="TicketPriorityValue">
            {foreach $priorities as $tsK => $tsL}<option value="{$tsK|wash}"{if $ticket.priority|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}
            </select>
            <button type="submit" class="a4-btn a4-btn-small" name="TicketPriority" value="1" id="ts-priority">{'Set priority'|i18n( $tsI )}</button>
        </form>
        <form class="a4-actions" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-queue-form">
            <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
            <label class="a4-sr-only" for="ts-queue-select">{'Queue'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-queue-select" name="TicketQueueValue">
            {foreach $categories as $tsK => $tsL}<option value="{$tsK|wash}"{if $ticket.category|eq( $tsK )} selected="selected"{/if}>{$tsL|i18n( $tsI )|wash}</option>{/foreach}
            </select>
            <button type="submit" class="a4-btn a4-btn-small" name="TicketQueue" value="1" id="ts-queue">{'Move'|i18n( $tsI )}</button>
        </form>
    </div>
</section>

<section class="a4-card{if $ticket.escalation|ge( 2 )} is-bad{elseif $ticket.escalation|ge( 1 )} is-attention{/if}" aria-labelledby="ts-a4-sla-h" id="ts-sla">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-sla-h">{'SLA'|i18n( $tsI )}</h2>
        {if $sla}<p class="a4-meta">{$sla.level_label|wash}</p>{/if}</div>
    <div class="a4-card-body">
    {if $sla}
    <dl class="a4-facts is-rows">
        <div><dt>{'First response'|i18n( $tsI )}</dt>
        <dd>{include uri='design:ticketsystem/parts/sla_state.tpl' target=$sla.response}</dd></div>
        <div><dt>{'Resolution'|i18n( $tsI )}</dt>
        <dd>{include uri='design:ticketsystem/parts/sla_state.tpl' target=$sla.resolution}</dd></div>
        <div><dt>{'Escalation'|i18n( $tsI )}</dt>
        <dd>{if $ticket.escalation|ge( 2 )}<span class="a4-badge is-bad">{'level 2: breached'|i18n( $tsI )}</span>{elseif $ticket.escalation|ge( 1 )}<span class="a4-badge is-warn">{'level 1'|i18n( $tsI )}</span>{else}{'none'|i18n( $tsI )}{/if}</dd></div>
    </dl>
    {else}
    <p class="a4-empty">{'This ticket has no SLA target.'|i18n( $tsI )}</p>
    {/if}
    </div>
    {if and( $ticket.escalation|lt( 2 ), $ticket.done|not )}
    <div class="a4-card-foot">
    <form class="a4-actions" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-escalate-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <button type="submit" class="a4-btn a4-btn-small a4-btn-outline-danger" name="TicketEscalate" value="1" id="ts-escalate">{'Escalate'|i18n( $tsI )}</button>
    </form>
    </div>
    {/if}
</section>
{/if}

</div>

<section class="a4-card" aria-labelledby="ts-a4-thread-h">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-thread-h">{'Conversation'|i18n( $tsI )}</h2>
        <p class="a4-meta">{'%count posts, oldest first. Every post was also e-mailed to the followers.'|i18n( $tsI,, hash( '%count', $posts|count ) )|wash}</p></div>
    <div class="a4-card-body">
    <ol class="a4-timeline" id="ts-posts">
    {foreach $posts as $tsI2 => $p}
        <li class="{if $p.is_staff}is-ok{else}is-info{/if}" id="{if $tsI2|eq( $tsLast )}ts-latest{else}ts-post-{$p.id}{/if}"{if $p.is_staff} data-staff="1"{/if}>
            <time class="a4-timeline-time" datetime="{$p.created|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$p.created|l10n( 'shortdatetime' )}</time>
            <div class="a4-timeline-body">
                <strong>{if $p.is_self}{'You'|i18n( $tsI )}{else}{$p.author|wash}{/if}</strong>
                {if $p.is_staff} <span class="a4-badge is-ok">{'Support'|i18n( $tsI )}</span>{/if}
                {if $p.opening} <span class="a4-badge">{'Opening post'|i18n( $tsI )}</span>{/if}
                <div class="a4-ticketsystem-post">{if $p.attribute}{attribute_view_gui attribute=$p.attribute}{/if}</div>
                {if is_set( $attachments.post[$p.id] )}{include uri='design:ticketsystem/parts/files.tpl' files=$attachments.post[$p.id]}{/if}
            </div>
        </li>
    {/foreach}
    </ol>
    </div>
</section>

{if $is_staff}
<section class="a4-card is-soft" aria-labelledby="ts-a4-notes-h" id="ts-notes">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-notes-h">{'Internal notes'|i18n( $tsI )} <span class="a4-badge is-warn">{'staff only'|i18n( $tsI )}</span></h2>
        <p class="a4-meta">{'Customers never see a note: it is not shown to them, never mailed and never in their search.'|i18n( $tsI )}</p></div>
    <div class="a4-card-body a4-stack">
    {if $notes|count}
    <ol class="a4-timeline" id="ts-note-list">
    {foreach $notes as $n}
        <li class="is-warn" id="ts-note-{$n.id}">
            <time class="a4-timeline-time" datetime="{$n.created|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$n.created|l10n( 'shortdatetime' )}</time>
            <div class="a4-timeline-body"><strong>{$n.author|wash}</strong>
                <div class="a4-ticketsystem-post">{foreach $n.paragraphs as $tsPara}<p>{foreach $tsPara as $tsN => $tsLine}{if $tsN|gt( 0 )}<br />{/if}{$tsLine|wash}{/foreach}</p>{/foreach}</div>
                {if is_set( $attachments.note[$n.id] )}{include uri='design:ticketsystem/parts/files.tpl' files=$attachments.note[$n.id]}{/if}
            </div>
        </li>
    {/foreach}
    </ol>
    {else}
    <p class="a4-empty">{'No internal notes yet.'|i18n( $tsI )}</p>
    {/if}
    <form class="a4-form is-wide" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-note-form" enctype="multipart/form-data">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <div class="a4-field a4-field-wide">
            <label for="ts-note-body">{'New internal note'|i18n( $tsI )}</label>
            <textarea class="a4-input" id="ts-note-body" name="TicketNoteBody" rows="4"{if is_set( $field_errors.note )} aria-invalid="true" aria-describedby="ts-note-error"{/if}>{$note_draft|wash}</textarea>
            {if is_set( $field_errors.note )}<p class="a4-field-error" id="ts-note-error">{$field_errors.note|i18n( $tsI )|wash}</p>{/if}
        </div>
        {if $attach.enabled}<div class="a4-field a4-field-wide"><label for="ts-note-files">{'Files for staff'|i18n( $tsI )}</label>
            <input class="a4-input" type="file" id="ts-note-files" name="NoteFiles[]" multiple="multiple" accept="{$attach.accept|wash}" /></div>{/if}
        <div class="a4-actions"><button type="submit" class="a4-btn" name="TicketNote" value="1" id="ts-add-note">{'Save the note'|i18n( $tsI )}</button></div>
    </form>
    </div>
</section>
{/if}

{if $may_reply}
<section class="a4-card" aria-labelledby="ts-a4-reply-h" id="ts-reply">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-reply-h">{if $is_staff}{'Reply to the customer'|i18n( $tsI )}{else}{'Reply'|i18n( $tsI )}{/if}</h2>
        <p class="a4-meta">{'The reply is e-mailed to the customer and everyone following the ticket.'|i18n( $tsI )}</p></div>
    <div class="a4-card-body">
    <form class="a4-form is-wide" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-reply-form" enctype="multipart/form-data">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        {if and( $is_staff, $canned|count )}
        <div class="a4-actions">
            <label for="ts-canned">{'Canned reply'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-canned" name="TicketCanned">
                <option value="0">{'None'|i18n( $tsI )}</option>
                {foreach $canned as $tsC}<option value="{$tsC.id}"{if $tsC.id|eq( $canned_chosen )} selected="selected"{/if}>{$tsC.title|wash}{if $tsC.macro} ({'macro'|i18n( $tsI )}){/if}</option>{/foreach}
            </select>
            <button type="submit" class="a4-btn a4-btn-small" name="TicketInsertCanned" value="1" id="ts-insert-canned">{'Insert the text'|i18n( $tsI )}</button>
        </div>
        {/if}
        <div class="a4-field a4-field-wide">
            <label for="ts-body">{'Your reply'|i18n( $tsI )}</label>
            <textarea class="a4-input" id="ts-body" name="TicketBody" rows="8"{if is_set( $field_errors.body )} aria-invalid="true" aria-describedby="ts-body-error"{/if}>{$draft|wash}</textarea>
            {if is_set( $field_errors.body )}<p class="a4-field-error" id="ts-body-error">{$field_errors.body|i18n( $tsI )|wash}</p>{/if}
        </div>
        {if $attach.enabled}
        <div class="a4-field a4-field-wide">
            <label for="ts-files">{'Attachments'|i18n( $tsI )}</label>
            <input class="a4-input" type="file" id="ts-files" name="TicketFiles[]" multiple="multiple" accept="{$attach.accept|wash}" />
            <p class="a4-help">{'Up to %count files, at most %size MB each: %types.'|i18n( $tsI,, hash( '%count', $attach.max_files, '%size', $attach.max_mb, '%types', $attach.types ) )|wash}</p>
        </div>
        {/if}
        {if $is_staff}
        <div class="a4-field">
            <label for="ts-reply-status">{'Status after this reply'|i18n( $tsI )}</label>
            <select class="a4-input" id="ts-reply-status" name="TicketStatus">
            {foreach $staff_choices as $c}<option value="{$c.id|wash}"{if $c.id|eq( $staff_default )} selected="selected"{/if}>{$c.label|i18n( $tsI )|wash}</option>{/foreach}
            </select>
        </div>
        {/if}
        <div class="a4-actions"><button type="submit" class="a4-btn a4-btn-primary" name="TicketReply" value="1" id="ts-send">{'Send the reply'|i18n( $tsI )}</button></div>
    </form>
    </div>
</section>
{else}
<p class="a4-notice is-info" id="ts-closed">{'This ticket is closed. If more help is needed, open a new ticket and mention %number.'|i18n( $tsI,, hash( '%number', $ticket.number ) )|wash}</p>
{/if}

{if or( $may_rate, and( $rating.enabled, $rating.score|gt( 0 ) ) )}
<section class="a4-card" aria-labelledby="ts-a4-rate-h" id="ts-rate">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-rate-h">{'Satisfaction'|i18n( $tsI )}</h2></div>
    <div class="a4-card-body">
    {if $rating.score|gt( 0 )}<p id="ts-rating">{'Rated %score of 5'|i18n( $tsI,, hash( '%score', $rating.score ) )|wash}{if $rating.comment} &middot; {$rating.comment|wash}{/if}</p>{/if}
    {if $may_rate}
    <form class="a4-form" method="post" action={concat( 'ticketsystem/view/', $ticket.number )|ezurl} id="ts-rate-form">
        <input type="hidden" name="{$ts_token_field|wash}" value="{$ts_token|wash}" />
        <fieldset class="a4-radios"><legend>{'How did we do?'|i18n( $tsI )}</legend>
        {foreach array( 5, 4, 3, 2, 1 ) as $tsS}<label class="a4-check"><input type="radio" name="TicketRating" value="{$tsS}"{if $rating.score|eq( $tsS )} checked="checked"{/if} /> {$tsS}</label>{/foreach}
        </fieldset>
        <div class="a4-field a4-field-wide"><label for="ts-rate-comment">{'Comment (optional)'|i18n( $tsI )}</label>
            <input class="a4-input" type="text" id="ts-rate-comment" name="TicketRatingComment" maxlength="1000" value="{$rating.comment|wash}" /></div>
        <div class="a4-actions"><button type="submit" class="a4-btn" name="TicketRate" value="1" id="ts-rate-send">{'Send the rating'|i18n( $tsI )}</button></div>
    </form>
    {/if}
    </div>
</section>
{/if}

{if and( $is_staff, $history|count )}
<section class="a4-card" aria-labelledby="ts-a4-history-h" id="ts-history">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ts-a4-history-h">{'History'|i18n( $tsI )}</h2></div>
    <div class="a4-card-body">
    <ol class="a4-timeline">
    {foreach $history as $h}
        <li class="{if $h.kind|eq( 'escalate' )}is-bad{elseif $h.kind|eq( 'note' )}is-warn{else}is-info{/if}">
            <time class="a4-timeline-time" datetime="{$h.created|datetime( 'custom', '%Y-%m-%dT%H:%i:%s' )}">{$h.created|l10n( 'shortdatetime' )}</time>
            <div class="a4-timeline-body">{include uri='design:ticketsystem/parts/event.tpl' event=$h}</div>
        </li>
    {/foreach}
    </ol>
    </div>
</section>
{/if}

{include uri='design:ticketsystem/parts/admin_close.tpl'}
{undef $tsI $tsLast}
