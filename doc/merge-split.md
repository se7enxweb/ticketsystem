# Merge and split

Staff (`ticketsystem/manage`) merge a duplicate into the ticket that stays, and split replies that are another matter
off into a new ticket. Both are on the ticket page in the administration (card **Merge or split**) and in
`ticketsystemMerge`. Neither sends a mail; both histories record it.

## Merge

`ticketsystemMerge::merge( $source, $target )`, the form `TicketMerge` with `TicketMergeInto` (the number of the
ticket that stays; any case).

- **Only tickets of the same account** (`account_key`): a post never reaches another customer. A ticket that was
  merged already, a ticket merged into itself and a closed target are refused (reopen it first, or merge the other way
  round).
- The **opening post** of the merged ticket becomes a post of the ticket that stays ("Merged from <number>: <subject>"),
  by its author and dated as it was, so the conversation stays in order.
- Its **replies** move below the ticket that stays (the content nodes move; authors, dates and versions are kept).
- Its **files**, **internal notes**, **time entries**, **search index rows** and **CC addresses** move with them (a CC
  address the ticket has already is dropped). A note stays a note: staff only.
- Its **followers** follow the ticket that stays.
- The merged ticket is **closed**, with `merged_into` set. Its number stays valid: `ticketsystem/view/<old number>`
  redirects to the ticket that stays (also after several merges), with the notice "Ticket ... was merged into this
  ticket." for a reader who may read it.
- The counts of both tickets (posts, notes, files, time) are recounted.

## Split

`ticketsystemMerge::split( $ticket, $replyObjectIds, $subject )`, the form `TicketSplit` with `TicketSplitPosts[]`
(the content object ids of replies) and `TicketSplitSubject`.

- Only replies: the opening post always stays. At least one reply; every one must be a reply of this ticket.
- A **new ticket** is opened for the same opener, in the same account, queue, priority and subscription (source
  `split`), with a staff post "This ticket was split from ticket <number>: <n> of its replies continue here." It gets a
  new number and its own SLA targets from now.
- The picked replies move below it with their **files**, **time entries** and **search index rows**.
- The followers of the old ticket follow the new one.
- After the split the page shows the new ticket.

## History

| Kind | On | Value |
|---|---|---|
| `merge` | the ticket that stays | the merged ticket's number |
| `merged` | the merged ticket | the number of the ticket that stays (old: the status before) |
| `split` | the old ticket | the new ticket's number |
| `splitfrom` | the new ticket | the old ticket's number |
