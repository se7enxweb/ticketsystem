# Policies, roles and sharing

## Policy functions of the module ticketsystem

| Function | Limitation | Grants |
|---|---|---|
| `read` | Scope: 1 Own, 2 Account, 3 All | the list and the ticket view |
| `reply` | Scope: 1 Own, 2 Account, 3 All | posting, marking solved, reopening |
| `create` | none | opening a ticket |
| `manage` | none | staff: posts count as support posts, any status can be set |
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
| Ticket Support | Technical Support Group | `ticketsystem/read` and `reply` (Scope All), `create`, `manage`, `use`; `user/login` (the front and admin siteaccesses), `user/selfedit`, `user/password`, `content/read` (section 1), `notification/use` |

A role whose policies differ is completed (missing policies added); nothing else is changed.

## Isolation

- Ticket objects are below user nodes, in the Users section: content/read of visitors and customers does not
  reach them.
- The views check the scope for every ticket; a ticket out of reach answers 404.
- Mails go only to followers who may still read the ticket (and to the poster).
