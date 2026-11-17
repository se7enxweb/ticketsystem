# Automatic tickets

Other extensions, cronjobs and scripts report problems to the help desk (decision 29): `ticketsystemAutoTicket`,
`[AutoTicketSettings]`. Katalyst uses it for failed provisioning, SSL, backup and caption jobs (Katalyst's
doc/tickets.md).

## Internal tickets: `raise( $key, $subject, $body, $options )`

- One ticket per **key** while it is being worked on: the key names the problem and where (e.g.
  `katalyst-job:ssl:3`, the job type group and the server). The key is stored in `external_id` (lower case,
  `[a-z0-9:._-]`, 64 characters), the source is `auto`.
- No open ticket of the key: a new one, opened by the help desk user (`OpenerUserID`, 14 = admin; it needs the staff
  policies) in the account `internal:auto`, which no customer has, so **only staff read it** (Scope All). Queue
  `Category`, priority `Priority` unless the caller names them; the subscription when given. Its opening post is mailed
  to its followers when `Notify` is enabled.
- An open ticket of the key (any status but resolved and closed, not merged): the report is **added as a post**, and
  the ticket goes back to "waiting on staff".
- After the ticket was resolved or closed, the next report opens a new ticket.
- Bodies are plain text; callers put ids and messages in them, never secrets or a customer's personal data.

## Customer tickets: `raiseForCustomer( $key, $userId, $accountKey, $subject, $body )`

- A ticket for a customer, **once per key** (source `autocustomer`): the customer is the opener, the help desk user
  writes the opening post as staff, and it is mailed to the customer. A second call with the same key changes nothing.
- The account must be one of the user's (`ticketsystemAccount::keysFor()`).
- Off with `CustomerTickets=disabled`. Which events open one is the caller's decision.

Both return `ok`, `message`, `ticket`, `created`, `appended` and never throw.

## Settings

| Setting | Default | |
|---|---|---|
| `[AutoTicketSettings] Enabled` | enabled | |
| `OpenerUserID` | 14 | the help desk user (staff policies) |
| `Category`, `Priority` | '', high | of a new internal ticket ('' = the defaults) |
| `Notify` | enabled | mail the opening post of an internal ticket to its followers |
| `CustomerTickets` | enabled | `raiseForCustomer()` |
