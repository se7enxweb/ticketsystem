# Databases

ticketsystem runs on every database of the kernel: MySQL/MariaDB, PostgreSQL, SQLite, Oracle (with the extension
ezoracle) and MongoDB.

## One definition

`ticketsystemSchema::definition()` is the only definition of the tables, in the kernel's generic schema format. The
installer (`bin/php/install.php`) creates a missing table with the kernel's schema handler of the database, and adds
the columns (and their indexes) that an older release's table lacks, with the handler's own `ALTER TABLE` statements.
On MongoDB a collection has no columns to add: the installer sets each new field to its default on the documents of
an older release that lack it (so `assignee_id = 0` or `source = 'web'` finds a 1.0 ticket as on the other databases)
and creates the indexes the collection lacks; the dry run lists both.
`bin/php/install.php --write-schema-files` writes from it:

| File | For |
|---|---|
| `sql/mysql/schema.sql`, `sql/postgresql/schema.sql`, `sql/sqlite/schema.sql`, `sql/oracle/schema.sql` | creating the tables by hand |
| `sql/mongodb/schema.json` | the collections and indexes on MongoDB |
| `share/db_schema.dba` | the MongoDB driver reads the types of the columns from it |

The tests check that every file is current.

## Tables

| Table | Holds |
|---|---|
| `ticketsystem_ticket` | the index of tickets: number, account, status, queue, priority (and its rank), assignee, SLA times and escalation, rating, counts |
| `ticketsystem_counter` | the last ticket number per prefix and year |
| `ticketsystem_note` | internal notes ([notes.md](notes.md)) |
| `ticketsystem_event` | the history of every change |
| `ticketsystem_canned` | canned replies and macros |
| `ticketsystem_attachment` | files ([attachments.md](attachments.md)) |
| `ticketsystem_post` | the text of customer-visible posts for the staff search |
| `ticketsystem_mailin` | inbound mails handled (reply by e-mail) |
| `ticketsystem_import` | what imported records became |

## Portable queries

The queries stay inside what every driver runs, so there is no branch per database except the ticket number counter on
MongoDB (an atomic `$inc`, as there is no row lock): single-table `SELECT` with `WHERE` (`=`, `<>`, `<`, `>`,
`IN`, `IN (SELECT ...)`, `LIKE` on `LOWER(column)`), `ORDER BY` on columns only (the priority sorts by its stored rank,
not a `CASE`), `COUNT(*) AS n` and `MAX(...) AS m` alone, paging by the driver's offset and limit; no `JOIN`, no `GROUP
BY` (counts per status are one count each), no `UPDATE`/`DELETE` without a `WHERE`. Rows are written through
`eZPersistentObject` with the id left to the database. On Oracle the columns `number` (ticketsystem_ticket) and `level` (ticketsystem_escalation), reserved words, are quoted by the driver
(`settings/ezoracle.ini.append.php`, `[QuotedIdentifierSettings] TypeColumnNames[]=number` and `level`). That setting needs
ezoracle 2.3.14 or later (2.3.13 and earlier do not know it).

## Proof

The maintainers' engine test installs the tables on an empty database of each engine (and upgrades a 1.0 table),
then runs the index, the numbers (four processes at once), the staff list's filters and sorting, notes, history, canned
replies, files, the search through the post index (a note found only by staff) and the SLA run, and drops everything.
