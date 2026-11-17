<?php /* #?ini charset="utf-8"?

# The original ticketsystem views of ticket objects in content/view and content/edit (the administration).
# The views of the help desk (ticketsystem/list, ticketsystem/view/<number>) do not use them.

[edit_state_ticket]
Source=content/datatype/edit/ezselection.tpl
MatchFile=content/datatype/edit/state.tpl
Subdir=templates
Match[attribute_identifier]=state
Match[class_identifier]=ticket

[edit_assigned_ticket]
Source=content/datatype/edit/ezobjectrelation.tpl
MatchFile=content/datatype/edit/assigned.tpl
Subdir=templates
Match[attribute_identifier]=assigned
Match[class_identifier]=ticket

[ticket]
Source=node/view/full.tpl
MatchFile=ticket.tpl
Subdir=templates
Match[class_identifier]=ticket

[edit_ticket]
Source=content/edit.tpl
MatchFile=edit_ticket.tpl
Subdir=templates
Match[class_identifier]=ticket

[edit_ticket_comment]
Source=content/edit.tpl
MatchFile=edit_comment.tpl
Subdir=templates
Match[class_identifier]=ticket_comment

[ticket_list]
Source=node/view/full.tpl
MatchFile=ticketsystem/ticketsystem_full.tpl
Subdir=templates
Match[class_identifier]=ticketsystem

[assigned_to]
Source=content/datatype/view/ezobjectrelation.tpl
MatchFile=content/datatype/view/assigned.tpl
Subdir=templates
Match[attribute_identifier]=assigned
Match[class_identifier]=ticket

[notification_ticket]
Source=notification/handler/ezsubtree/view/plain.tpl
MatchFile=notification/handler/ezsubtree/view/ticket.tpl
Subdir=templates
Match[class_identifier]=ticket

[notification_ticket_comment]
Source=notification/handler/ezsubtree/view/plain.tpl
MatchFile=notification/handler/ezsubtree/view/ticket_comment.tpl
Subdir=templates
Match[class_identifier]=ticket_comment

*/ ?>
