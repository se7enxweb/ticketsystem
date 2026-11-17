<?php /* #?ini charset="utf-8"?

[CronjobSettings]
ExtensionDirectories[]=ticketsystem

# php runcronjobs.php ticketsystem: closes resolved tickets after [StatusSettings] AutoCloseDays
[CronjobPart-ticketsystem]
Scripts[]
Scripts[]=ticketsystem_autoclose.php

*/ ?>
