# Changelog

## Unreleased

Work in progress for 1.1 (on main, not released).

- Fixed: The TicketSystem pages of the administration draw on the admin4 model: names in tables are cells (a row
  header is upper case and unbroken, so subjects ran out of the card), every fact is a "Label: value" pair, and the
  list's path names its list ("TicketSystem > Needs a staff reply").
- Updated: The README has a section "Administration" for installations without Katalyst; doc/views.md and
  doc/permissions.md describe the tab TicketSystem, its left menu and its pages.

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
- Added: SLA timers on business hours: targets per SLA level, priority and queue ([SLA_<level>], minutes or texts
  such as "8 business hours"), working days, hours, holidays and time zone ([SLASettings]), the Katalyst SLA levels
  per plan, the clock paused while the ticket waits on the customer or is resolved (the due times move on by the
  working time paused), the state on track / due soon / breached / paused on the staff list, the ticket page and the
  SLA board ticketsystem/sla, and a hint for customers when the first answer is due (doc/sla.md).
- Added: Escalation in steps ([SLASettings] EscalationSteps[]): notify the assignee, then the queue leads
  (QueueLeads[]) or the support group, raise the priority, mark the ticket escalated; run by the cronjob part
  ticketsystem, each step once per ticket and target (the escalation log ticketsystem_escalation and a lock), mails to
  staff only, every step in the history and the log.
- Added: Satisfaction ratings: the mail "How did we do?" when a ticket is resolved or closed, with a signed link per
  score (1 to 5) that works once and without sign-in (ticketsystem/rate), never for the imported WHMCS history; the
  results for staff (ticketsystem/satisfaction) and the fetch functions csat_summary and csat_latest for reports
  (doc/csat.md).
- Added: Fetch functions sla, sla_board, escalations and rating (modules/ticketsystem/function_definition.php).
- Updated: SLA and ratings are on by default.
- Added: The search (ticketsystem/search) with filters for the number, status, priority, queue, assignee, customer,
  account and opening date; words are found in the subjects and in every customer-visible post, for staff also in the
  internal notes (never for a customer); within the read scope, on every database ([SearchSettings], doc/search.md).
- Added: Reports (ticketsystem/reports) for any period and queue as view parameters: volume per day, week or month,
  first response and resolution times, SLA attainment and satisfaction (from the SLA part's aggregate when there),
  the backlog by age, queue and priority, and the workload of every staff member; the CSV export carries no note and
  no post text and defuses formula cells ([ReportSettings] MaxDays, MaxRows; doc/reports.md).
- Added: Canned replies and macros, personal and shared, per queue, under the policy function canned (CannedScope
  Personal / Shared); placeholders for the ticket, the customer's and the staff member's names and the links; a
  plain select in the reply form, inserted at the cursor by ticketsystem-canned.js ([CannedSettings],
  doc/canned-replies.md).
- Added: The fetch functions ticketsystem/search, ticketsystem/report and ticketsystem/canned_replies.
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
