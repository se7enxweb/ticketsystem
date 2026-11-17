# Views

| Address | Policy | What it does |
|---|---|---|
| `ticketsystem/list` | `read` | The tickets the user may read, newest activity first. View parameters: `(status)/open` (default: not resolved or closed), `(status)/done`, `(status)/all`; `(offset)/<n>`. Filters are view parameters, never GET parameters. |
| `ticketsystem/new` | `create` | The form of a new ticket: subject, category, priority, the site it is about (Katalyst), the message. |
| `ticketsystem/view/<number>` | `read` | One ticket with every post, oldest first, the reply form, the details and the status actions. A ticket the user may not read answers **404**, like a number that does not exist. `ticketsystem/view/<index id>` redirects to the number. |
| `ticketsystem/tickets` | `use` | The original list of ticketsystem 1.x (the folder `[TicketSettings] TicketNodeID`); without that folder it redirects to `ticketsystem/list`. |

## Forms

Every form carries the session's form token (`TicketsystemToken`) and works without JavaScript. After a POST the view
redirects (post/redirect/get) with a one-time message.

| Button | View | Who | Effect |
|---|---|---|---|
| `TicketCreate` | new | `create` | opens the ticket (subject, body, category, priority, subscription) |
| `TicketReply` | view | `reply` | adds a post; staff choose the status after it (`TicketStatus`) |
| `TicketResolve` | view | `reply` | the customer marks the ticket solved |
| `TicketReopen` | view | `reply` | the customer opens a solved ticket again |
| `TicketSetStatus` | view | `manage` | staff set any status of `StaffChoices` |

Limits: `[LimitSettings]` (subject length, message length, new tickets per user and hour).
