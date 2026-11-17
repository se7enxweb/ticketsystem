# Time tracking

Staff record the time they spent on a ticket with a reply or an internal note (decision 21: billable time outside an
SLA or warranty is billed). `ticketsystemTime`, table `ticketsystem_time`, `[TimeSettings]`. Customers never see time.

## Recording

- The staff reply form and the internal note form have **Time spent**, **Billable** and a **Remark** (staff only).
  The time is read as minutes (`45`, `45m`), hours and minutes (`1:30`, `1h 30m`, `1h30`) or hours (`1.5h`, `2 h`);
  empty records nothing. A value that cannot be read stops the reply with a message, so nothing is posted twice.
- Rounded up to `Increment` minutes (1 = exact, 15 = quarter hours); at most `MaxMinutes` at once (1440).
- The entry is linked to its reply (content object) or note, the staff member, the day, the account of the ticket and,
  when an SLA or warranty covered the account at that moment, its level (`covered`, e.g. `sla:gold`).
- **Billable by default** (`DefaultBillable`): `uncovered` (the default) ticks "Billable" unless an SLA or warranty
  covers the account; staff can still tick it for work outside what the SLA or warranty covers. `always`, `never`.
- **Coverage**: with Katalyst an active SLA or warranty entitlement of the ticket's customer (for the ticket's
  subscription, or for the whole account; `KatalystEntitlements`); without Katalyst the ticket's SLA level in
  `CoveredLevels[]`.
- The ticket keeps the sums: `time_minutes`, `billable_minutes`. The **Time** card of the ticket page lists the entries;
  an entry that was not billed yet can be removed (`TicketTimeRemove`, `TicketTimeID`). The history records `time` and
  `untime` (minutes only).

## The report: `ticketsystem/time`

Policy `ticketsystem/report`, within the reader's read scope; in the left menu under Manage, **Time**.

- View parameters, never GET: `(from)`, `(to)` (YYYY-MM-DD, inclusive; default this month), `(account)/<account key>`
  (e.g. `katalyst:12`), `(user)/<staff user id>`, `(billable)/yes|no`, `(billed)/yes|no`, `(format)/csv`. The filter
  form posts (`TimeRun`) and is redirected to them.
- Figures: in all, billable, covered by an SLA or warranty, billed; sums per account and per staff member; the entries.
- **CSV**: date, ticket, account, staff, minutes, hours, billable, covered_by, billed, billing_ref, remark. Never the
  text of a post or a note, never a customer's name or address. A remark starting with `= + - @` is prefixed with `'`.

## Billing

The help desk does not bill. An integration reads the billable entries not billed yet,
`ticketsystemTime::unbilled( $accountKey, $until )`, bills them its own way and marks them with
`ticketsystemTime::markBilled( $ids, $reference )` (a billed entry can no longer be removed). With Katalyst this is
`katalyst:tickets:bill-time` (Katalyst's doc/tickets.md): a dry run by default. Without an integration, the CSV is the
export.

## Settings

| Setting | Default | |
|---|---|---|
| `[TimeSettings] Enabled` | enabled | the fields, the card and the report |
| `Increment` | 1 | minutes are rounded up to it |
| `MaxMinutes` | 1440 | at most at once |
| `DefaultBillable` | uncovered | uncovered, always, never |
| `KatalystEntitlements` | enabled | Katalyst's SLA and warranty entitlements decide coverage |
| `CoveredLevels[]` | none | without Katalyst: covered SLA levels |
