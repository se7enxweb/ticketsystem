<?php /* #?ini charset="utf-8"?

# The top menu tab "TicketSystem" of the administration (admin, admin4, admin4l ...; doc/views.md): the staff desk
# ticketsystem/list with its left menu (design:parts/ticketsystem/menu.tpl: the queues, then reports, search, canned
# replies and settings), shown to users with ticketsystem/manage (the Technical Support Group, administrators). With
# Katalyst it stands next to Katalyst's tab "Tickets".
[NavigationPart]
Part[ezticketsystemnavigationpart]=TicketSystem

[TopAdminMenu]
Tabs[]=ticketsystem

[Topmenu_ticketsystem]
Icon=inbox
NavigationPartIdentifier=ezticketsystemnavigationpart
Name=TicketSystem
Tooltip=The help desk: tickets, assignments, SLA, canned replies and reports
URL[]
URL[default]=ticketsystem/list
Enabled[]
Enabled[default]=true
Enabled[browse]=false
Enabled[edit]=false
Shown[]
Shown[default]=true
Shown[edit]=true
Shown[navigation]=true
Shown[browse]=true
PolicyList[]=ticketsystem/manage

*/ ?>
