# Changelog

## Unreleased

Work in progress for 1.1 (on main, not released).

- Added: Assigning and claiming tickets: an assignee in the index, "Take it" and "Assign" for staff, a staff reply to an
  unassigned ticket assigns it to the replier ([AssignmentSettings] ClaimOnReply), optional round-robin per queue
  ([AssignmentSettings] AutoAssign[]); the staff list filters "assigned to me" and "unassigned".
- Added: Internal staff notes (ticketsystem_note): shown to staff only, never mailed, never in a customer's view or
  search; files of a note are served to staff only.
- Added: Changing the priority and the queue of an open ticket (staff); the ticket object's attributes follow.
- Added: Attachments of new tickets, replies and notes ([AttachmentSettings]): size, type and content checks, an
  optional virus scanner, stored under random names outside the public storage, served only by ticketsystem/file to
  readers of the ticket.
- Added: A history of every change of a ticket (ticketsystem_event) for staff.
- Added: The staff desk (ticketsystemDesk): status figures, filters (search, status, priority, queue, last post,
  assigned to, SLA, account) and sorting as view parameters, for ticketsystem/list and Katalyst's ticket manager.
- Added (first parts, off by default): SLA timers and escalation ([SLASettings], the cronjob part ticketsystem),
  satisfaction ratings ([CSATSettings]), canned replies and macros (ticketsystem/canned), reports with a CSV export
  and the staff search (ticketsystem/reports, ticketsystem/search, policy function report), reply by e-mail
  (bin/php/mailin.php, [MailInSettings]) and the WHMCS import (bin/php/whmcs_export.php, bin/php/import_whmcs.php,
  [ImportSettings]).
- Added: Administration pages in the design admin4 and the tab TicketSystem with its left menu (settings/menu.ini).
- Updated: Every table of the help desk is defined in ticketsystemSchema and created (or completed with the columns
  of a newer release) by the installer on MySQL, PostgreSQL, SQLite, Oracle and MongoDB; every sql/<engine> file and
  share/db_schema.dba are generated from it.

## Unreleased

- Added: Oracle and MongoDB. `ticketsystemSchema` defines the two tables once; the installer creates them there
  with the kernel's schema handler (`sql/oracle/schema.sql` and `sql/mongodb/schema.json` are generated from it,
  `bin/php/install.php --write-schema-files`). On MongoDB ticket numbers come from an atomic `$inc` on the counter;
  on Oracle the column `number` is quoted (`settings/ezoracle.ini.append.php`, needs ezoracle with
  `[QuotedIdentifierSettings] TypeColumnNames`).
- Updated: The installer finds the existing tables in each database's own catalog.

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
