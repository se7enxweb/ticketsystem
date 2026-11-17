# Views

| Address | Policy | What it does |
|---|---|---|
| `ticketsystem/list` | `read` | The tickets the user may read, newest activity first. View parameters: `(status)/open` (default: not resolved or closed), `(status)/done`, `(status)/all`; `(offset)/<n>`. Filters are view parameters, never GET parameters. |
| `ticketsystem/new` | `create` | The form of a new ticket: subject, category, priority, the site it is about (Katalyst), the message. `(subscription)/<id>` preselects the site when it is one of the account's own (anything else is ignored), for "Get help with this service". |
| `ticketsystem/view/<number>` | `read` | One ticket with every post, oldest first, the reply form, the details and the status actions. A ticket the user may not read answers **404**, like a number that does not exist. `ticketsystem/view/<index id>` redirects to the number. |
| `ticketsystem/import` | `manage` | The WHMCS import ([import-whmcs.md](import-whmcs.md)): the export's counts, a dry run, what was imported; upload an export (`TicketImportUpload`, file `ImportFile`) and run the import (`TicketImportRun`). Counts only. |
| `ticketsystem/mailin` | `manage` | Reply by e-mail ([mail-in.md](mail-in.md)): the settings in use and the latest inbound mails (time, result, ticket). |
| `ticketsystem/time` | `report` | Time tracking (1.2, [time-tracking.md](time-tracking.md)): the entries of a period, the sums per account and staff member, `(format)/csv`. |
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
| `TicketCreate` with `TicketForUser` or `TicketForCustomer`, `TicketNotify` | new | `manage` | 1.2: a ticket for a customer ([staff-opened-tickets.md](staff-opened-tickets.md)) |
| `TicketFollow`, `TicketUnfollow` | view | `read` | 1.2: follow the ticket by e-mail, or stop ([watchers.md](watchers.md)) |
| `TicketCcAdd` (`TicketCcAddress`), `TicketCcRemove` (`TicketCcID`) | view | `reply` | 1.2: CC addresses |
| `TicketWatchAdd` (`TicketWatcher`), `TicketWatchRemove` (`TicketWatcherID`) | view | `manage` | 1.2: staff watchers |
| `TicketMerge` (`TicketMergeInto`), `TicketSplit` (`TicketSplitPosts[]`, `TicketSplitSubject`) | view | `manage` | 1.2: merge and split ([merge-split.md](merge-split.md)) |
| `TicketTimeSpent`, `TicketTimeBillable`, `TicketTimeRemark` with `TicketReply` or `TicketNote`; `TicketTimeRemove` (`TicketTimeID`) | view | `manage` | 1.2: time tracking ([time-tracking.md](time-tracking.md)) |

Limits: `[LimitSettings]` (subject length, message length, new tickets per user and hour).

The views check their policy themselves: a visitor who is not signed in is sent to the sign-in page and back to the
view afterwards; a signed-in user without the policy gets the kernel's "Access denied".


## The customer's support summary (1.1)

`fetch( 'ticketsystem', 'customer_summary', hash( 'limit', 5, 'subscription_id', 0 ) )` gives a customer's home (the
Katalyst account home, the "my" portal) what `ticketsystem/list` shows that customer, within the same read scope
(`ticketsystemAccess::listConditions()`): no other ticket is ever counted or listed.

| Key | What |
|---|---|
| `counts` | `open` (not resolved or closed), `waiting` (the status of the role `StaffReply`: waiting on the customer), `to_rate` (resolved or closed, opened by the user, no rating yet), `done`, `all` |
| `open` | up to `limit` rows of `ticketsystemView::row()` plus `waiting`, `subscription_id`, `rating`: the tickets waiting on the customer first, then the newest activity |
| `to_rate` | resolved tickets the customer may still rate (`ticketsystemService::mayRate()`), each rated on `ticketsystem/view/<number>#ts-rate` |
| `may_create` | the user has `ticketsystem/create` |
| `rating` | `[CSATSettings] Enabled` |

`subscription_id` limits every list and count to the tickets about one site. Without `ticketsystem/read` the result
is `false`.

## The administration: the tab TicketSystem

In an administration siteaccess (its `SiteDesign` or an additional design is `admin*`: admin4, admin4l ...) the views
render the templates of `design/admin/templates/ticketsystem/` (admin4 and admin4l find them through their design fallback to admin; the classic design admin draws them with its copy of `components.css`) inside the admin page frame, with or without Katalyst:

- **The tab** "TicketSystem" (`settings/menu.ini.append.php`, navigation part `ezticketsystemnavigationpart`) opens
  `ticketsystem/list`. It is shown to users with `ticketsystem/manage` ([permissions.md](permissions.md)). With
  Katalyst it stands next to Katalyst's own tab "Tickets", which reads the same tickets.
- **The left menu**: in admin4 and admin4l menu.ini `[Leftmenu_ticketsystem]` drawn by `parts/ini_menu.tpl` with the groups Tickets and Manage, the current page marked (`design/admin4/templates/parts/ticketsystem/menu.tpl`); in the classic design admin a plain list (`design/admin/templates/parts/ticketsystem/menu.tpl`). The lists (Not resolved, Needs a staff reply, Assigned
  to me, Unassigned, Urgent, Escalated, Resolved or closed, All tickets, New ticket), then the management pages
  (Reports, Search, Canned replies, SLA and escalation (the board `ticketsystem/sla`), Satisfaction, Queues and categories, Mail, files and ratings; Import only
  when `[ImportSettings] Enabled=enabled`). Each list is a link with view parameters.
- **The list**, top down: the page head, the status figures (each a link to the same filter with that status), the
  filter bar (it posts and is redirected to view parameters), the active filters as removable chips, the sortable
  table and the pages. The path names the list: "TicketSystem > Needs a staff reply".
- **A ticket**: the details as "Label: value" facts with the status change, the staff's work (take it, assign,
  priority, queue), the SLA card, the conversation, the internal notes, the reply with canned replies, the rating
  and the history.

The pages use only the shared components of admin4 (`components.css`: `.a4-page`, `.a4-card`, `.a4-table`,
`.a4-filterbar`, `.a4-figures`, `.a4-facts`, `.a4-btn` ...) and one rule set of their own,
`design/admin/stylesheets/modules/ticketsystem.css` (`.a4-ticketsystem-post`, tokens only). No `<style>`, no
`style=""`, no colour of their own; `tests/ticketsystemAdminStylingTest.php` checks it, and that a table has no row
headers (an `.a4-table` header is upper case and does not wrap) and that every fact of `.a4-facts` is a `<div>`
with its `<dt>` and `<dd>`.

## Staff pages: search, reports, canned replies (1.1)

| View | Policy | Page |
|---|---|---|
| `ticketsystem/search` | `report` (customers: `read` with `[SearchSettings] Customers=enabled`) | the search with its filters ([search.md](search.md)) |
| `ticketsystem/reports` | `report` | the figures of a period and queue, `(format)/csv` the export ([reports.md](reports.md)) |
| `ticketsystem/canned` | `canned` | the canned replies and macros: list, `(edit)/new`, `(edit)/<id>` ([canned-replies.md](canned-replies.md)) |

Each has a plain template in `design/standard/templates/ticketsystem/` and one for the administration in
`design/admin/templates/ticketsystem/`. Every filter form posts with the form token and is redirected to view
parameters; none of the views reads GET parameters.
