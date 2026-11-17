# Coming from ticketsystem 1.x

- The extension is now at the root of the repository; install it with Composer or clone it to
  `extension/ticketsystem`.
- `settings/ticketsystem.ini.append.php` became `settings/ticketsystem.ini`. `[TicketSettings] TicketNodeID` keeps
  its meaning for `ticketsystem/tickets` (default 0: none).
- Run `bin/php/install.php`: your classes are kept and get `ticket_number` and `subscription`; the tables and roles
  are added. Set `[StatusSettings] StatusSet=original` to keep the 1.x statuses.
- Tickets made before have no index row yet: add the workflow event ([workflow.md](workflow.md)) and publish them
  again, or open new tickets through `ticketsystem/new`.
- The override rules for the attributes `state` and `assigned` now match the class ticket only.
- `workflow.ini` no longer sets `[OperationSettings] AvailableOperations` (it replaced the kernel's list).
- The policy functions `use` and `deligate` stay; the new views need `read`, `reply`, `create` and `manage`.
