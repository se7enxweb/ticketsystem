{* One entry of a ticket's history (ticketsystemService::history()). Input: $event (kind, actor, old, new). *}
{def $tsWho = cond( $event.actor, $event.actor, 'The help desk'|i18n( 'extension/ticketsystem' ) )}
<strong>{$tsWho|wash}</strong>
{switch match=$event.kind}
{case match='open'}{'opened the ticket'|i18n( 'extension/ticketsystem' )}{if $event.new|ne( 'web' )} ({$event.new|wash}){/if}{/case}
{case match='status'}{'set the status'|i18n( 'extension/ticketsystem' )}: {$event.old|i18n( 'extension/ticketsystem' )|wash} &rarr; {$event.new|i18n( 'extension/ticketsystem' )|wash}{/case}
{case match='priority'}{'set the priority'|i18n( 'extension/ticketsystem' )}: {$event.old|i18n( 'extension/ticketsystem' )|wash} &rarr; {$event.new|i18n( 'extension/ticketsystem' )|wash}{/case}
{case match='category'}{'moved it to another queue'|i18n( 'extension/ticketsystem' )}: {$event.old|i18n( 'extension/ticketsystem' )|wash} &rarr; {$event.new|i18n( 'extension/ticketsystem' )|wash}{/case}
{case match='claim'}{'took the ticket'|i18n( 'extension/ticketsystem' )}{/case}
{case match='assign'}{'assigned it to'|i18n( 'extension/ticketsystem' )} {$event.new|wash}{/case}
{case match='unassign'}{'took it from'|i18n( 'extension/ticketsystem' )} {$event.old|wash}{/case}
{case match='escalate'}{'escalated it'|i18n( 'extension/ticketsystem' )} ({$event.new|wash}){/case}
{case match='note'}{'wrote an internal note'|i18n( 'extension/ticketsystem' )}{/case}
{case match='rate'}{'rated the ticket'|i18n( 'extension/ticketsystem' )}: {$event.new|wash} / 5{/case}
{case match='mailin'}{'replied by e-mail'|i18n( 'extension/ticketsystem' )}{/case}
{case match='import'}{'imported it'|i18n( 'extension/ticketsystem' )} ({$event.new|wash}){/case}
{case}{$event.kind|wash}{/case}
{/switch}
{undef $tsWho}
