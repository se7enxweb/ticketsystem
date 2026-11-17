{* The time fields of the staff reply and the internal note (1.2, doc/time-tracking.md): the time spent, billable or not
   (the default: not when an SLA or warranty covers the account), and a remark. Input: $prefix (id prefix), $error (a
   message or ''), $draft (spent, checked: billable ticked, remark), $covered (the covering level or ''). *}
{def $tsI = 'extension/ticketsystem'
     $tsBill = $draft.checked}
<div class="a4-grid is-narrow">
    <div class="a4-field">
        <label for="{$prefix}-time">{'Time spent'|i18n( $tsI )}</label>
        <input class="a4-input" type="text" id="{$prefix}-time" name="TicketTimeSpent" value="{$draft.spent|wash}" maxlength="12" inputmode="text" autocomplete="off" placeholder="0:30"{if $error} aria-invalid="true" aria-describedby="{$prefix}-time-error"{else} aria-describedby="{$prefix}-time-help"{/if} />
        {if $error}<p class="a4-field-error" id="{$prefix}-time-error">{$error|i18n( $tsI )|wash}</p>{else}<p class="a4-help" id="{$prefix}-time-help">{'Minutes (45), 1:30, 1h 30m or 1.5h; empty records nothing.'|i18n( $tsI )}</p>{/if}
    </div>
    <div class="a4-field">
        <input type="hidden" name="TicketTimeBillableSent" value="1" />
        <label class="a4-check"><input type="hidden" name="TicketTimeBillable" value="0" /><input type="checkbox" id="{$prefix}-billable" name="TicketTimeBillable" value="1"{if $tsBill} checked="checked"{/if} /> <span>{'Billable'|i18n( $tsI )}{if $covered}<span class="a4-help">{'Covered by %level: not billable unless the work is outside it.'|i18n( $tsI,, hash( '%level', $covered ) )|wash}</span>{/if}</span></label>
    </div>
    <div class="a4-field">
        <label for="{$prefix}-remark">{'Remark (staff only)'|i18n( $tsI )}</label>
        <input class="a4-input" type="text" id="{$prefix}-remark" name="TicketTimeRemark" value="{$draft.remark|wash}" maxlength="255" autocomplete="off" />
    </div>
</div>
{undef $tsI $tsBill}
