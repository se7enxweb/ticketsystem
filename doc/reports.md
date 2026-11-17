# Reports

`ticketsystem/reports` shows the figures of the help desk for a period. Staff only: the policy function
`ticketsystem/report`, which the role "Ticket Support" has. Every figure stays within the reader's read scope (a
staff member with Scope Account sees the figures of their account's tickets only).

## The period and the queue

View parameters, never GET parameters:

| View parameter | Meaning |
|---|---|
| `(from)/YYYY-MM-DD`, `(to)/YYYY-MM-DD` | the period, both days included; default: the last `[ReportSettings] DefaultDays` days up to today |
| `(queue)/<category>` | one queue only |
| `(period)/day\|week\|month` | how the volume is grouped; default by the length of the period (up to 31 days by day, up to 182 by week, else by month) |
| `(days)/<n>` | the last n days (the links of 1.1) |
| `(format)/csv` | the CSV export of the same period and queue |

The form posts (`ReportRun`, with the form token) and is redirected to the view parameters. A period longer than
`MaxDays` is cut to its last `MaxDays` days; a period whose end is before its start is turned around.

## The figures

- **Volume**: the tickets opened in the period and, of these, how many are solved or still open, unassigned or
  escalated; the tickets opened and solved per day, week or month; the tickets opened by status, queue and priority.
- **First response**: from the opening to the first staff reply (`responded`): average and median in minutes.
- **Resolution**: from the opening to solving (`resolved`, else `closed`) of the tickets solved: average and median
  in hours.
- **SLA attainment**: of the tickets with an SLA target, how many met the first response and the resolution target.
  Shown as "SLA timers are off" while `[SLASettings] Enabled=disabled`.
- **Satisfaction (CSAT)**: the average rating and the number of ratings per star. Shown as "Ratings are off" while
  `[CSATSettings] Enabled=disabled` and nothing was rated.
- **Backlog**: the tickets not solved now, by how long they have been open (less than a day, 1 to 3, 3 to 7, 7 to 30,
  more than 30 days), by queue and by priority; the age of the oldest one.
- **Workload**: per staff member, the tickets not solved assigned to them now, their replies in the period and the
  number of tickets those replies went to; "Nobody" holds the unassigned tickets.

SLA and satisfaction figures come from the SLA part's own aggregate,
`ticketsystemFunctionCollection::fetchCsatSummary( $from, $to, $queue )`, when it exists (it answers `result` with
`count`, `avg`, `dist`, `response_met`, `response_total`, `resolution_met`, `resolution_total`); until then the report
counts them from the index columns (`rating`, `response_due`, `resolve_due`, `responded`, `resolved`) and says where
they came from (`data-ts-source` on the satisfaction figure: `fetch`, `index` or `off`).

Every part counts at most `[ReportSettings] MaxRows` tickets; a period with more says so.

## The CSV export

`ticketsystem/reports/(from)/.../(to)/.../(format)/csv`: one line per ticket opened in the period (and queue), within
the reader's scope, with the same policy as the page.

Columns: number, subject, account, status, queue, priority, assignee, created, first_response_minutes, resolved,
resolution_hours, sla_level, escalation, breached, rating, posts, source.

- **Never** the text of a post, never an internal note, never a rating comment.
- **Formula injection** is guarded: a cell that starts with `=`, `+`, `-`, `@`, a tab or a carriage return (also
  after spaces, also the full-width forms) gets a leading apostrophe, so a spreadsheet shows it as text. Numbers stay
  numbers.
- UTF-8 with a byte order mark, comma separated, CRLF line ends, quotes doubled (RFC 4180); control characters are
  removed. Written by `ticketsystemReport::csvLine()`, not `fputcsv()`.
- Sent with `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`.

The export contains customer names (the account column): it is for staff, like the page.

## How it runs on every database

Every query is a single-table `SELECT` with plain comparisons, `IN` lists (in chunks of 500) and `OR`; the figures
are counted in PHP. MySQL/MariaDB, PostgreSQL, SQLite, Oracle and MongoDB answer them the same way.

## Fetch function

```
{def $r = fetch( 'ticketsystem', 'report', hash( 'from', '2026-11-01', 'to', '2026-11-30', 'queue', 'billing' ) )}
{$r.figures.opened} {$r.backlog.total}
```

`result`: `ticketsystemReport::report()` (figures, volume, backlog, workload, period, csat_source, sla_enabled,
csat_enabled, truncated) plus `range`, or `false` without the policy.

## Settings

```ini
[ReportSettings]
DefaultDays=30
MaxDays=1100
MaxRows=20000
```
