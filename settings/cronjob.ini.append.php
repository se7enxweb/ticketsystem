<?php /* #?ini charset="utf-8"?

[CronjobSettings]
ExtensionDirectories[]=ticketsystem

# php runcronjobs.php ticketsystem: closes resolved tickets after [StatusSettings] AutoCloseDays and runs the SLA
# check (escalation at [SLASettings] WarnPercent, breaches, alerts); e.g. every 5 minutes
[CronjobPart-ticketsystem]
Scripts[]
Scripts[]=ticketsystem_autoclose.php
Scripts[]=ticketsystem_sla.php

*/ ?>
