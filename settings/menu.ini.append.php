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
URL[default]=ticketsystem/list/(status)/active
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

# The left menu of the tab in admin4 and admin4l (design/admin4/templates/parts/ticketsystem/menu.tpl draws it with
# parts/ini_menu.tpl, so the current page is marked as in every other admin4 section; design/admin keeps its own
# list). "Not resolved" carries (status)/active, so it is not also marked on the other lists below ticketsystem/list.
# Import is left out unless [ImportSettings] Enabled=enabled (the template passes it as a hidden link).
[Leftmenu_ticketsystem]
Name=ticketsystem
Groups[]
Groups[]=lists
Groups[]=manage
GroupNames[]
GroupNames[lists]=Tickets
GroupNames[manage]=Manage
GroupLinks_lists[]
GroupLinks_lists[]=active
GroupLinks_lists[]=staff
GroupLinks_lists[]=mine
GroupLinks_lists[]=unassigned
GroupLinks_lists[]=urgent
GroupLinks_lists[]=escalated
GroupLinks_lists[]=done
GroupLinks_lists[]=all
GroupLinks_lists[]=new
GroupLinks_manage[]
GroupLinks_manage[]=reports
GroupLinks_manage[]=search
GroupLinks_manage[]=canned
GroupLinks_manage[]=sla
GroupLinks_manage[]=satisfaction
GroupLinks_manage[]=time
GroupLinks_manage[]=queues
GroupLinks_manage[]=mail
GroupLinks_manage[]=mailin
GroupLinks_manage[]=import
Links[]
LinkNames[]
Links[active]=ticketsystem/list/(status)/active
PolicyList_active[]=ticketsystem/manage
Links[staff]=ticketsystem/list/(reply)/staff
PolicyList_staff[]=ticketsystem/manage
Links[mine]=ticketsystem/list/(assignee)/me
PolicyList_mine[]=ticketsystem/manage
Links[unassigned]=ticketsystem/list/(assignee)/none
PolicyList_unassigned[]=ticketsystem/manage
Links[urgent]=ticketsystem/list/(priority)/urgent
PolicyList_urgent[]=ticketsystem/manage
Links[escalated]=ticketsystem/list/(sla)/escalated
PolicyList_escalated[]=ticketsystem/manage
Links[done]=ticketsystem/list/(status)/done
PolicyList_done[]=ticketsystem/manage
Links[all]=ticketsystem/list/(status)/all
PolicyList_all[]=ticketsystem/manage
Links[new]=ticketsystem/new
PolicyList_new[]=ticketsystem/create
Links[reports]=ticketsystem/reports
PolicyList_reports[]=ticketsystem/report
Links[search]=ticketsystem/search
PolicyList_search[]=ticketsystem/report
Links[canned]=ticketsystem/canned
PolicyList_canned[]=ticketsystem/manage
Links[sla]=ticketsystem/sla
PolicyList_sla[]=ticketsystem/manage
Links[satisfaction]=ticketsystem/satisfaction
PolicyList_satisfaction[]=ticketsystem/report
Links[time]=ticketsystem/time
PolicyList_time[]=ticketsystem/report
Links[queues]=ticketsystem/settings/queues
PolicyList_queues[]=ticketsystem/manage
Links[mail]=ticketsystem/settings/mail
PolicyList_mail[]=ticketsystem/manage
Links[mailin]=ticketsystem/mailin
PolicyList_mailin[]=ticketsystem/manage
Links[import]=ticketsystem/import
PolicyList_import[]=ticketsystem/manage

*/ ?>
