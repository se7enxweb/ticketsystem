# Canned replies and macros

A canned reply is a text support staff use often, with placeholders that are filled in for the ticket at hand. A
**macro** is a canned reply that also changes the ticket when the reply is sent: its status, its priority, or its
assignee (the sender).

## Personal and shared, per queue

- **Shared** canned replies (`owner_id` 0) are offered to every staff member.
- **Personal** ones only to the staff member who wrote them.
- Each one belongs to one queue, or to every queue. On a ticket, the reply form offers the ones of the ticket's queue
  and the ones of every queue.

## Policies

| Function | Limitation | Grants |
|---|---|---|
| `ticketsystem/manage` | none | using canned replies in the reply form (staff) |
| `ticketsystem/canned` | CannedScope: 1 Personal, 2 Shared | creating, changing and deleting them on `ticketsystem/canned` |

- **Personal**: one's own canned replies.
- **Shared**: the team's canned replies.
- Without a limitation: both. The role "Ticket Support" has `ticketsystem/canned` without a limitation.
- Turning a personal one into a shared one (or back) needs both.
- Nobody can change or delete another staff member's personal canned reply.

To let new staff use the team's canned replies but keep only their own, give their role `ticketsystem/canned` with
CannedScope Personal.

## Placeholders

| Placeholder | Filled in with |
|---|---|
| `{number}` | the ticket number |
| `{subject}` | the subject |
| `{status}`, `{queue}`, `{priority}` | the ticket's status, queue and priority |
| `{customer}` | the name of the customer who opened the ticket |
| `{customer_first_name}` | that customer's first name (the attribute `first_name`, else the first word of the name) |
| `{staff_name}`, `{staff_first_name}` | the name and first name of the staff member who inserts it |
| `{link}` | the address of the ticket |
| `{tickets_link}` | the address of the customer's ticket list |
| `{new_ticket_link}` | the address of a new ticket |
| `{site}` | the name of the site (`[MailSettings] Brand`) |

The names of 1.1 still work: `{first_name}` = `{customer_first_name}`, `{agent}` = `{staff_name}`, `{ticket_link}` =
`{link}`. A placeholder is filled in once: a value is never searched for placeholders again, so a customer named
`{number}` stays `{number}`. Unknown placeholders are left as written.

The links start with `[MailSettings] SiteURL` (else site.ini `SiteURL`), the same as the links in the mails.

## Macros

A canned reply can, when the reply is sent:

- set the **status** (one of the set's `StaffChoices`); it is chosen in the reply form's status list when the text is
  inserted, so the staff member sees it and can still change it;
- set the **priority**;
- **assign** the ticket to the sender.

The changes go through the same service methods as by hand (`changePriority()`, `assign()`), so they are in the
ticket's history. The use is counted. `[CannedSettings] Macros=disabled` makes every canned reply a plain text.

## In the reply form

- A plain select "Canned reply" and the button "Insert the text": without JavaScript the server puts the filled-in text
  under what was written and chooses the macro's status. Nothing is sent yet.
- With JavaScript (`design/standard/javascript/ticketsystem-canned.js`, loaded with `ezscript_require`) the button
  inserts the text at the cursor, or over the selected text, at once and chooses the macro's status. The text comes
  from the option's `data-ts-text`, filled in on the server.
- The choice stays in the select, so sending the reply applies the macro.

Markup for other designs: an element `[data-ts-canned]` with `data-ts-canned-target` (the id of the textarea) and
`data-ts-canned-status` (the id of the status select), holding the select whose options carry `data-ts-text` and
`data-ts-status`, and a button `[data-ts-canned-insert]`.

## The management page

`ticketsystem/canned`: the list (title, personal or shared, queue, what the macro does, how often it was used),
`(queue)/<category>` for one queue, `(edit)/new` and `(edit)/<id>` for the form. Posts with the form token:
`CannedSave`, `CannedDelete`, `CannedFilter`, then post/redirect/get.

## Service and fetch

- `ticketsystemService::cannedReplies( $queue )`, `cannedFor( $id )`, `cannedChoices( $ticket )`,
  `cannedText( $canned, $ticket )`, `saveCanned( $id, $values )`, `deleteCanned( $id )`, `cannedManageable()`;
  pure: `cannedValues()`, `expand()`, `firstName()`, `mayKeepCanned()`, `cannedVisible()`, `cannedInQueue()`.
- `fetch( 'ticketsystem', 'canned_replies', hash( 'queue', 'billing' ) )`: the ones the user may use, or `false`.

## Settings

```ini
[CannedSettings]
Macros=enabled
MaxLength=20000
```
