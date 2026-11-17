# Tickets staff open for a customer

Decision 3: staff open a ticket on behalf of a customer, e.g. after a phone call, or to tell them about a problem.
`ticketsystemService::openFor()`, the form `ticketsystem/new` for staff (`ticketsystem/manage` and `create`).

- **Who**: a user given by user id or login (`TicketForUser`), or a Katalyst customer (`TicketForCustomer`; its linked
  site user). The view parameters `(user)/<id>` and `(customer)/<Katalyst customer id>` fill it in; Katalyst's
  customer page links to `ticketsystem/new/(customer)/<id>`. A disabled user, the anonymous user, a customer without a
  site user and an unknown login are refused with a message on the field.
- **The customer is the opener**: the ticket is stored below their user node, in their account (with Katalyst the
  customer's account, so every user of that account sees it), in their ticket list. The staff member writes the
  opening post, marked as staff's; source `staff`.
- **Mail**: "Mail the opening post to the customer" (`TicketNotify`, ticked by default). Either way the customer
  follows the ticket, so later replies reach them; without the tick the opening post is not mailed.
- SLA targets, round-robin assignment and the queue's settings apply as for any new ticket. The history records
  `onbehalf` (`notified` or `silent`).
- Customers never see the "For a customer" part of the form, and `openFor()` refuses anyone who is not staff.
