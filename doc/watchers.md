# Followers, watchers and CC

Every post of a ticket is mailed to its followers ([mail.md](mail.md)). Version 1.2 lets people choose, and adds CC
addresses (decision 16: customer and staff watchers / CC). Card **Followers and CC** on the ticket page (`#ts-watch`),
`ticketsystemWatch`, `[WatchSettings]`.

## Following

- Following is an Exponential notification subscription on the ticket node. The opener and everyone who posts follow
  automatically, as before.
- Everyone who may read the ticket can **follow** or **stop following** it (`TicketFollow`, `TicketUnfollow`). A
  customer who stops following gets no further mail of that ticket until they follow again or post.
- **Staff watchers**: staff add another member of the support staff (`TicketWatchAdd`, `TicketWatcher` = a user id) and
  remove one (`TicketWatchRemove`, `TicketWatcherID`). Only staff who can read the ticket can be added. Staff never take
  a customer off: customers decide that themselves.
- Staff see the list of followers (opener, customer, staff), marked when one may no longer read the ticket (such a
  follower gets no mail: every reader is checked when a post is mailed). Customers see only whether they follow.

## CC addresses

- A CC address gets a **copy of every post** of the ticket: the full text, HTML and plain text, with the notice that
  it is a CC copy. Never an internal note (notes are never mailed).
- Customers who may reply and staff add and remove them while the ticket is not closed (`TicketCcAdd` with
  `TicketCcAddress`, `TicketCcRemove` with `TicketCcID`).
- **Never an identity**: the address is not matched to any user. It grants no access to the ticket, its mails carry no
  signed reply reference (no `Ticket reference`, no reference in the Message-ID or Reply-To), so an answer from it
  never becomes a post, and it gets no rating link.
- **Account rules**: at most `MaxCC` addresses per ticket (5; 0 switches CC off), optionally only addresses of
  `CCDomains[]` (and their subdomains); never the help desk's own sender, its `ReplyTo`, the domain of the mail-in
  `ReplyAddress`, or an address of `RefusedCC[]`. One valid address at a time (no lists, no header characters).
- The history records that an address was added or removed, never the address. The captured test mails
  (`[MailSettings] CaptureDomains`) apply to CC addresses too.

## Settings

| Setting | Default | |
|---|---|---|
| `[WatchSettings] MaxCC` | 5 | CC addresses per ticket; 0 = no CC |
| `CCDomains[]` | none | allowed domains; empty = any |
| `RefusedCC[]` | none | addresses or `@domain` never accepted |
