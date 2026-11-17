<?php /* #?ini charset="utf-8"?

[CronjobSettings]
ExtensionDirectories[]=ticketsystem

# php runcronjobs.php ticketsystem: closes resolved tickets after [StatusSettings] AutoCloseDays and runs the SLA
# check (escalation at [SLASettings] WarnPercent, breaches, alerts); e.g. every 5 minutes
[CronjobPart-ticketsystem]
Scripts[]
Scripts[]=ticketsystem_autoclose.php
Scripts[]=ticketsystem_sla.php

# php runcronjobs.php ticketsystem_mailin: reply by e-mail from the IMAP/POP3 mailbox of ticketsystem.ini
# [MailInSettings] (every minute or two; nothing happens until it is enabled there)
[CronjobPart-ticketsystem_mailin]
Scripts[]
Scripts[]=ticketsystem_mailin.php

*/ ?>
