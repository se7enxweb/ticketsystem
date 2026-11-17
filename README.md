# ticketsystem

Help desk tickets for [Exponential](https://github.com/se7enxweb/exponential): customers open tickets, follow them
and reply from the site; support staff answer, change the status and solve them. Every post, including the
customer's own, is mailed to everyone who follows the ticket, in HTML and plain text with the full message, so each
person keeps an external record of the whole conversation.

ticketsystem was written by xrow GbR (Hannover, Germany) in 2006 and 2007 for eZ Publish 3.9 and published on
projects.ez.no. 7x took it over in 2026 to keep it alive for its users: it now runs on Exponential 6.0.15 with
PHP 8.0 to 8.5, and it was cleaned up, re-documented and extended.

## Features

- **Tickets are content objects**, as in ticketsystem 1.x: a `ticket` object below the opener's user node (never in
  the public section), each reply a `ticket_comment` below the ticket.
- **Readable ticket numbers** like `7X-2026-00042`: a gapless counter per prefix and year.
- **Status sets**: the 7x set (Open, Waiting on customer, Waiting on staff, On hold, Resolved, Closed) by default, or
  the original ticketsystem set; resolved tickets close by themselves after a number of days.
- **Sharing by account**: the policy limitation *Scope* (Own, Account, All). With Katalyst the account is the
  Katalyst customer, so everybody linked to a customer sees its tickets; elsewhere a ticket belongs to its opener.
- **Mail copies of every post**, HTML and text, threaded per ticket, sent from one support address.
- **Exponential notification subscriptions**: the opener and everyone who posts follow the ticket node.
- **Technical Support Group**: a user group and the role *Ticket Support* for support staff.
- **Assignment** (1.1): staff take a ticket or assign it, optional round-robin per queue; the staff list filters
  "assigned to me" and "unassigned"; staff change the priority and the queue of an open ticket.
- **Internal notes** (1.1): for staff only; never shown to a customer, never mailed, never in a customer's search or
  an export.
- **Attachments** (1.1): files of tickets, replies and notes, checked (size, type, content, an optional virus
  scanner), stored outside the public storage, served only to readers of the ticket.
- **Every database of the kernel** (1.1): MySQL/MariaDB, PostgreSQL, SQLite, Oracle and MongoDB, from one schema
  definition; the installer also upgrades the tables of an older release.
- **Mobile-first views**, light and dark, with no inline CSS; inside the Katalyst customer console they use its
  frame and components.
- The original views of ticketsystem 1.x in the administration are kept (fixed for Exponential 6).

## Requirements

- Exponential 6.0.15 or later
- PHP 8.0 to 8.5
- MySQL/MariaDB, PostgreSQL, SQLite, Oracle (with the ezoracle extension) or MongoDB

## Installation

```bash
composer require se7enxweb/ticketsystem
```

Then activate the extension and run the installer; see [doc/install.md](doc/install.md).

## Documentation

| Topic | File |
|---|---|
| Installation and update | [doc/install.md](doc/install.md) |
| How it works: objects, index, numbers | [doc/architecture.md](doc/architecture.md) |
| Views and forms | [doc/views.md](doc/views.md) |
| Policies, roles and sharing | [doc/permissions.md](doc/permissions.md) |
| Status sets | [doc/statuses.md](doc/statuses.md) |
| Mail | [doc/mail.md](doc/mail.md) |
| Design and the Katalyst console | [doc/design.md](doc/design.md) |
| Every setting | [doc/settings.md](doc/settings.md) |
| Assignment, priority and queue | [doc/assignment.md](doc/assignment.md) |
| Internal notes | [doc/notes.md](doc/notes.md) |
| Attachments | [doc/attachments.md](doc/attachments.md) |
| Databases | [doc/databases.md](doc/databases.md) |
| Search | [doc/search.md](doc/search.md) |
| Reports and the CSV export | [doc/reports.md](doc/reports.md) |
| Canned replies and macros | [doc/canned-replies.md](doc/canned-replies.md) |
| The workflow event | [doc/workflow.md](doc/workflow.md) |
| Coming from ticketsystem 1.x | [doc/upgrade.md](doc/upgrade.md) |

## License

GNU General Public License v2.0 or (at your option) any later version; see [LICENSE](LICENSE) and
[COPYRIGHT.txt](COPYRIGHT.txt).

Copyright (C) 2006 - 2007 xrow GbR, Hannover (Germany). Original authors: Konstantin Pogrebnoj, Björn Dieding.
Copyright (C) 2026 7x (se7enx.com) for the take-over and all new work.
