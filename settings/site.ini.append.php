<?php /* #?ini charset="utf-8"?

[RoleSettings]
# The help desk views check their policy themselves (ticketsystemView::guard()): a visitor who is not signed in is
# sent to the sign-in page and back, a user without the policy gets "Access denied"
PolicyOmitList[]=ticketsystem/list
PolicyOmitList[]=ticketsystem/new
PolicyOmitList[]=ticketsystem/view
PolicyOmitList[]=ticketsystem/file
PolicyOmitList[]=ticketsystem/search
PolicyOmitList[]=ticketsystem/reports
PolicyOmitList[]=ticketsystem/canned
PolicyOmitList[]=ticketsystem/settings
PolicyOmitList[]=ticketsystem/sla
PolicyOmitList[]=ticketsystem/satisfaction
PolicyOmitList[]=ticketsystem/rate

*/ ?>
