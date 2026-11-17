# Changelog

## 1.1.0 (2026-11-17)

The help desk for staff: assignment, notes, SLA, ratings, reports, canned replies, reply by e-mail, the WHMCS import,
the administration's tab TicketSystem, and every database of the kernel. `bin/php/install.php` upgrades a 1.0
installation (new tables, the new columns or fields, the options of the class's selections); run it after updating.

### Staff work on tickets

- Added: Assigning and claiming tickets: an assignee in the index, "Take it" and "Assign" for staff, a staff reply to
  an unassigned ticket assigns it to the replier (`[AssignmentSettings] ClaimOnReply`), optional round-robin per queue
  (`[AssignmentSettings] AutoAssign[]`); the staff list filters "assigned to me" and "unassigned".
- Added: Internal staff notes (`ticketsystem_note`): shown to staff only, never mailed, never in a customer's view or
  search; the files of a note are served to staff only.
- Added: Changing the priority and the queue of an open ticket (staff); the ticket object's attributes follow.
- Added: Attachments of new tickets, replies and notes (`[AttachmentSettings]`): size, type and content checks, an
  optional virus scanner, stored under random names outside the public storage, served only by `ticketsystem/file`
  to readers of the ticket.
- Added: A history of every change of a ticket (`ticketsystem_event`) for staff.
- Added: The staff desk (`ticketsystemDesk`): status figures, filters (search, status, priority, queue, last post,
  assigned to, SLA, account) and sorting as view parameters, for `ticketsystem/list` and Katalyst's ticket manager.
- Updated: The installer completes the options of the ticket class's status, queue and priority selections from the
  settings; existing options keep their ids, so tickets keep their values.

### SLA, escalation and satisfaction

- Added: SLA timers on business hours: targets per SLA level, priority and queue (`[SLA_<level>]`, minutes or texts
  such as "8 business hours"), working days, hours, holidays and time zone (`[SLASettings]`), the Katalyst SLA levels
  per plan, the clock paused while the ticket waits on the customer or is resolved, the state on track / due soon /
  breached / paused on the staff list, the ticket page and the SLA board `ticketsystem/sla`, and a hint for customers
  when the first answer is due (doc/sla.md).
- Added: Escalation in steps (`[SLASettings] EscalationSteps[]`): notify the assignee, then the queue leads
  (`QueueLeads[]`) or the support group, raise the priority, mark the ticket escalated; run by the cronjob part
  `ticketsystem`, each step once per ticket and target (the log `ticketsystem_escalation` and a lock), mails to staff
  only. The run can be limited to given tickets.
- Added: Satisfaction ratings (CSAT): the mail "How did we do?" when a ticket is resolved or closed, with a signed
  link per score (1 to 5) that works once and without sign-in (`ticketsystem/rate`), never for imported WHMCS
  history; the results for staff (`ticketsystem/satisfaction`) and the fetch functions `csat_summary` and
  `csat_latest` (doc/csat.md).
- Updated: SLA and ratings are on by default.

### Search, reports and canned replies

- Added: The search (`ticketsystem/search`) with filters for the number, status, priority, queue, assignee,
  customer, account and opening date; words are found in the subjects and in every customer-visible post, for staff
  also in the internal notes (never for a customer) (`[SearchSettings]`, doc/search.md).
- Added: Reports (`ticketsystem/reports`) for any period and queue: volume per day, week or month, first response
  and resolution times, SLA attainment and satisfaction, the backlog by age, queue and priority, and the workload of
  every staff member; a CSV export that carries no note and no post text and defuses formula cells
  (`[ReportSettings] MaxDays, MaxRows`; policy function `report`; doc/reports.md).
- Added: Canned replies and macros, personal and shared, per queue, under the policy function `canned`
  (`CannedScope` Personal / Shared); placeholders for the ticket, the customer's and the staff member's names and the
  links; inserted at the cursor by `ticketsystem-canned.js` (`[CannedSettings]`, doc/canned-replies.md).
- Added: The fetch functions `search`, `report`, `canned_replies`, `sla`, `sla_board`, `escalations` and `rating`.

### Reply by e-mail and the WHMCS import

- Added: Reply by e-mail (doc/mail-in.md): every ticket mail carries the signed reference of the ticket and its
  reader in its Message-ID too, so a plain reply finds its ticket without a "+" address; the number in the subject is
  enough when the sender may reply and passed SPF/DKIM/DMARC (`RequireToken=disabled`, the default); refused mail is
  quarantined or bounced (pipe, exit 77), a known sender gets a notice, an unknown one never gets mail; files go
  through the attachment checks. `bin/php/mailin.php` takes a piped mail; the cronjob part `ticketsystem_mailin` and
  `mailin.php --poll` fetch an IMAP or POP3 mailbox (with IMAP only ticket mail, so a shared mailbox keeps
  everything else unread). `OpenNewTickets` is off by default. The signatures' secret is a random one kept in the
  database unless `TokenSecret` is set.
- Added: The WHMCS import (doc/import-whmcs.md): `bin/php/whmcs_export.php` exports, `bin/php/import_whmcs.php` is a
  dry run unless `--execute`; staff replies as `StaffUserMap[<WHMCS admin id>]` or the import user, status by
  `StatusMap` (default Closed), queue by `DepartmentMap` or the department's name; the opening post names the WHMCS
  ticket; a later run adds new replies only (`[ImportSettings]`, the map `ticketsystem_import`).

### Administration

- Added: The tab TicketSystem with its left menu (settings/menu.ini) and the pages list, ticket, new ticket, search,
  reports, canned replies, SLA board, satisfaction, settings, import and mail-in. They live in design/admin, so
  admin4, admin4l and the classic design admin draw them; admin4 marks the list or page shown in the left menu.
  No inline CSS: the shared components and `stylesheets/modules/ticketsystem.css`.
- Updated: The README has a section "Administration" for installations without Katalyst; doc/views.md and
  doc/permissions.md describe the tab, its menu and its pages.

### Databases

- Added: MySQL/MariaDB, PostgreSQL, SQLite, Oracle and MongoDB. `ticketsystemSchema` defines every table once; the
  installer creates a missing table with the kernel's schema handler of the database and adds the columns (and their
  indexes) a 1.0 table lacks. On MongoDB it sets the new fields' defaults on the documents of 1.0 and adds the
  missing indexes. Every `sql/<engine>` file and `share/db_schema.dba` are generated from it
  (`bin/php/install.php --write-schema-files`). The installer finds existing tables in each database's own catalog.
- Added: On MongoDB, ticket numbers come from an atomic `$inc` on the counter (MongoDB has no row lock).
- Note: On Oracle the columns `number` and `level` (reserved words) must be quoted by the driver
  (`settings/ezoracle.ini.append.php`, `[QuotedIdentifierSettings] TypeColumnNames[]`). That setting of ezoracle is **not released yet**: the
  latest ezoracle release, 2.3.13, does not have it. Oracle needs ezoracle from its main branch until its next
  release.

### Fixes

- Fixed: No deprecation notice on PHP 8.4 and 8.5 for parameters made nullable by their default (the parameters are
  declared `?array`, which PHP 8.0 reads too); a test scans every PHP file of the extension for the pattern.
- Fixed: Two tickets opened at once on MySQL or MariaDB no longer deadlock on the first ticket number of a year (the
  counter row is made before the transaction that takes the number).
- Fixed: The attachments list of the original ticket template closes its `let` block, so every template passes the
  kernel's template check.
- Updated: The MongoDB ticket counter passes on every error but the duplicate key of a counter made at the same time.
- Fixed: On Oracle the escalation log is written: its column `level` is quoted like `number`.
- Fixed: On MongoDB the installer gives the tickets of 1.0 the new fields' defaults and the collections their new
  indexes.

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
