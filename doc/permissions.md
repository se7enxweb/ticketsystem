# Policies, roles and sharing

## Policy functions of the module ticketsystem

| Function | Limitation | Grants |
|---|---|---|
| `read` | Scope: 1 Own, 2 Account, 3 All | the list and the ticket view |
| `reply` | Scope: 1 Own, 2 Account, 3 All | posting, marking solved, reopening |
| `create` | none | opening a ticket |
| `manage` | none | staff: posts count as support posts, any status can be set |
| `report` | none | staff: reports, the CSV export and the search, within the read scope ([reports.md](reports.md), [search.md](search.md)) |
| `canned` | CannedScope: 1 Personal, 2 Shared | keeping canned replies: one's own (Personal), the team's (Shared); none: both ([canned-replies.md](canned-replies.md)) |
| `use`, `deligate` | none | the functions of ticketsystem 1.x, kept for existing roles (`use`: `ticketsystem/tickets`) |

A policy without a limitation means All. **Own** reaches the tickets the user opened; **Account** also every ticket
of the user's account; **All** every ticket.

## Accounts

`[AccountSettings] Resolver`:

- `owner`: a ticket belongs to its opener (`user:<id>`); Account is the same as Own.
- `katalyst`: a ticket belongs to the Katalyst customer the opener acts for (the customer console's account
  switcher), `katalyst:<customer id>`. Every user linked to that customer reads it with Scope Account.
  A user without a customer falls back to `user:<id>`.
- `auto` (default): `katalyst` when the extension sevenx_katalyst is active, else `owner`.

The account is taken from the signed-in user, never from the request.

## Roles made by the installer

| Role | Assigned to | Policies |
|---|---|---|
| Ticket Customer | the groups of `--customer-group` | `ticketsystem/read` (Scope Account), `ticketsystem/reply` (Scope Account), `ticketsystem/create` |
| Ticket Support | Technical Support Group | `ticketsystem/read` and `reply` (Scope All), `create`, `manage`, `report`, `canned`, `use`; `user/login` (the front and admin siteaccesses), `user/selfedit`, `user/password`, `content/read` (section 1), `notification/use` |

A role whose policies differ is completed (missing policies added); nothing else is changed.

## Isolation

- Ticket objects are below user nodes, in the Users section: content/read of visitors and customers does not
  reach them.
- The views check the scope for every ticket; a ticket out of reach answers 404.
- Mails go only to followers who may still read the ticket (and to the poster).
- Internal notes are for staff (`manage`) only: not in the customer's views, mails, search or exports
  ([notes.md](notes.md)); the file of a note is served to staff only.
- Files (`ticketsystem/file/<id>`) are served only to users who may read the ticket ([attachments.md](attachments.md)).
- Assigning, claiming, the priority and the queue need `manage` ([assignment.md](assignment.md)).

## Staff pages, notes and exports

- **Search** (`ticketsystem/search`, function `report`): within the read scope. The internal notes are searched only
  for staff (`manage`); customers search only when `[SearchSettings] Customers=enabled`, their own scope, never a
  note ([search.md](search.md)).
- **Reports and the CSV export** (`ticketsystem/reports`, function `report`): within the read scope; the CSV carries
  no note and no post text, and every cell is defused against formula injection ([reports.md](reports.md)).
- **Canned replies** (`ticketsystem/canned`, function `canned`): using them needs `manage`; keeping them needs
  `canned` with the scope of the canned reply's owner ([canned-replies.md](canned-replies.md)).
- **Fetch functions** (`search`, `report`, `canned_replies`) answer for the signed-in user with the same rules.
