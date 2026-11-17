{* The SLA hint of a customer (doc/sla.md): while support has not answered, when the first answer is due. Nothing for
   staff, after the first answer, or without a target. Input: $ticket_id. No inline CSS. *}{def $tsH = fetch( 'ticketsystem', 'sla', hash( 'ticket_id', $ticket_id ) )}{if and( $tsH, is_set( $tsH.hint ), $tsH.hint )}
<dt>{'First answer'|i18n( 'extension/ticketsystem' )}</dt><dd id="ts-sla-hint">{'expected by %date'|i18n( 'extension/ticketsystem',, hash( '%date', $tsH.response_due|l10n( 'shortdatetime' ) ) )|wash}</dd>
{/if}{undef $tsH}
