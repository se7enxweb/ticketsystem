# Search

`ticketsystem/search` finds tickets by words, by their number and by filters. It always stays within what the
reader may read (the Scope of `ticketsystem/read`: Own, Account or All, see [permissions.md](permissions.md)).

## Who may search

| Reader | Needs | Searches |
|---|---|---|
| Support staff | `ticketsystem/report` (and `manage` for the notes) | every ticket of their read scope: subjects, the text of every post, **the internal notes** |
| Customers | `ticketsystem/read` and `[SearchSettings] Customers=enabled` | their own scope only: subjects and the text of the posts they can read, **never a note** |

Customers cannot search by default (`Customers=disabled`): the owner's decisions do not ask for it, and the list of
their own tickets is short. When it is switched on, the assignee, customer and account filters are left out for them.

Internal notes are searched only when the reader is staff (`ticketsystem/manage`). This is decided in one place,
`ticketsystemReport::searchPlan()`, and tested twice: the unit test `testInternalNotesAreSearchedForStaffOnly`, and the
engine test, where the opener of a ticket searches a word that appears only in its internal note and finds nothing.

## What is searched

- **Words** (`q`): the subject, the text of every customer-visible post (the opening post and every reply, kept in
  the search index `ticketsystem_post`), and for staff the internal notes (`ticketsystem_note`). The words are found
  in the order typed, case does not matter ("invoice address" finds "The invoice shows the wrong address"). `%` and
  `_` are taken literally. A ticket found only through a note is marked "in an internal note".
- A whole **ticket number** typed as the words (`7X-2026-00042`) opens the search on that number only.

## Filters

| View parameter | Values |
|---|---|
| `(q)` | the words |
| `(number)` | a whole number, or a part of one (`00042`) |
| `(status)` | `active` (not solved), `done` (solved or closed), `all` (default), or one status of the set |
| `(priority)` | a priority identifier |
| `(queue)` | a queue (category) identifier |
| `(assignee)` | `me`, `none` or a user id (staff only) |
| `(customer)` | a part of the opener's name or e-mail address (staff only) |
| `(account)` | `user-<id>` or `katalyst-<customer id>` (staff only) |
| `(from)`, `(to)` | the opening date, `YYYY-MM-DD`, both inclusive |
| `(offset)` | paging |

The form posts to `ticketsystem/search` (`SearchRun`, with the form token) and is redirected to these view
parameters, so a search is a link that can be kept and shared with a colleague. The view never reads GET parameters.

## How it runs on every database

Only single-table `SELECT`s with `=`, `IN`, `LIKE` on `LOWER(column)` and `AND`/`OR`: the kernel's drivers for
MySQL/MariaDB, PostgreSQL, SQLite, Oracle and MongoDB all run them. No `GROUP BY`, no `JOIN`, no sub-query and no
full-text syntax of one engine.

1. The words are matched in `ticketsystem_post` (and, for staff, `ticketsystem_note`): at most
   `[SearchSettings] MaxTextMatches` ticket ids.
2. The tickets are read from `ticketsystem_ticket` with the reader's scope, the filters and
   `(LOWER(subject) LIKE ... OR id IN (...))`; the id lists come in chunks of 500 (Oracle takes at most 1000).

## Fetch function

```
{def $found = fetch( 'ticketsystem', 'search', hash( 'filter', hash( 'q', 'disk', 'status', 'active' ), 'limit', 10 ) )}
```

`result`: `total` and `tickets` (the rows of the staff list, with `in_note`), or `false` for a reader without the
policy. The same rules as the view.

## Settings

```ini
[SearchSettings]
Customers=disabled
MaxTextMatches=2000

[LimitSettings]
SearchLimit=50
```
