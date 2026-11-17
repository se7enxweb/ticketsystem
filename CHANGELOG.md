# Changelog

## 1.0.0 (2026-11-16)

The first release after 7x took over the extension from xrow GbR.

- Added: Exponential 6.0.15 and PHP 8.0 to 8.5 support; the extension now sits at the root of its repository and
  installs with Composer (`se7enxweb/ticketsystem`).
- Added: The help desk views `ticketsystem/list`, `ticketsystem/new` and `ticketsystem/view/<number>`: mobile first,
  light and dark, no inline CSS; inside the Katalyst customer console they use its frame and components.
- Added: Readable ticket numbers (`[NumberSettings]`, e.g. `7X-2026-00042`), kept with every ticket in the index
  table `ticketsystem_ticket`.
- Added: Status sets: the 7x set by default and the original set; the cronjob part `ticketsystem` closes resolved
  tickets after `AutoCloseDays`.
- Added: The policy functions `read`, `reply` (limitation Scope: Own, Account, All), `create` and `manage`; the
  roles "Ticket Customer" and "Ticket Support" and the user group "Technical Support Group" (bin/php/install.php).
- Added: A mail of every post to its followers and to the poster, HTML and text with the full post, threaded.
- Added: The opener and everyone who posts follow the ticket node (Exponential notification subscriptions); the
  kernel's own subtree mails are not made for tickets, so a ticket never reaches someone who may not read it.
- Added: bin/php/install.php creates the tables, the classes (or adds what an existing 1.x class lacks), the group
  and the roles; idempotent, with --dry-run.
- Updated: The workflow event "Automatic ticket notification" is ported to Exponential 6 and also keeps the index of
  tickets edited in the administration.
- Updated: The original override rules now match the class ticket only (they changed every attribute named
  "state" or "assigned" before), and their templates live in override/templates as the kernel expects.
- Updated: The original templates use classes instead of inline styles.
- Removed: The setting `AvailableOperations=content_publish` of workflow.ini, which replaced the kernel's list.
- Updated: The help desk views check their policy themselves and send a visitor who is not signed in to the sign-in
  page and back (they are in the PolicyOmitList).
