# How it works

## Objects

| Object | Class | Where |
|---|---|---|
| A ticket and its opening post | `ticket` (subject `name`, `ticket_number`, `category`, `priority`, `state`, `description`, `subscription`, and the 1.x attributes `type`, `project`, `assigned`, `deadline`) | below the main node of the opener's user object (`[TicketSettings] Placement=user`), or below `TicketNodeID` (`Placement=node`) |
| A reply | `ticket_comment` (`name`, `comment`) | below the ticket |
| A file | `ticket_attachment` (`name`, `file`) | below the ticket (views come in a later release) |
| A folder of tickets (1.x) | `ticketsystem` | anywhere |

User nodes are in the Users section, which visitors cannot read, so tickets are never public. The help desk views
read the objects for the user after their own checks ([permissions.md](permissions.md)).

Objects are published with `eZContentFunctions` as the person who posts, **without** the kernel's notification
event: the posts are mailed by the help desk itself ([mail.md](mail.md)).

## The index

`ticketsystem_ticket` holds one row per ticket: the content object and node, the readable number (`prefix`, `year`,
`seq`), the account key (`user:<id>` or `katalyst:<customer id>`), the opener, the subject, the status identifier,
the category and priority identifiers, the linked subscription, the number of posts, the time and author of the
last post (and whether it came from staff), and the times it was created, changed, resolved and closed. The lists
read only this table.

The status is kept in the index; the attribute `state` of the ticket follows it (a new version when it changes),
so the administration shows it too.

## Numbers

`ticketsystem_counter` keeps the last value per prefix and year. A new ticket takes the next value under a row lock
(`SELECT ... FOR UPDATE`; SQLite locks the database for the transaction), so numbers are gapless and never shared.
The format is `[NumberSettings] Format` with `%prefix`, `%year` and `%seq` (zero-padded to `Digits`).

## Classes of the extension

| Class | Job |
|---|---|
| `ticketsystemService` | open, reply, change the status, close resolved tickets, the posts for display |
| `ticketsystemAccess` | the policy scopes and the checks, the SQL condition of a user's list |
| `ticketsystemAccount` | the account of a user (owner or Katalyst customer) |
| `ticketsystemMailer` | the mails of a post |
| `ticketsystemStatusSet` | a status set and its rules (pure) |
| `ticketsystemNumber` | numbers (format pure, counter in the database) |
| `ticketsystemText` | plain text to ezxmltext and back (pure) |
| `ticketsystemChrome`, `ticketsystemView` | the frame and the rendering of the views |
| `ticketsystemInstaller` | tables, classes, group and roles |
| `ticketsystemTicket` | the index row |
