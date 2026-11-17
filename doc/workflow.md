# The workflow event

"Automatic ticket notification" (`event_ticketnotification`, content/publish after) is the event of ticketsystem
1.x, ported to Exponential 6. Add it to a workflow on the trigger content publish after when tickets are also made
or edited in the administration or by imports. When a ticket or a reply is published there:

- the publisher follows the ticket (as 1.x did);
- a ticket without an index row gets one and a number; the index follows the subject and the status (attribute
  `state`) of the published version; a reply counts as a post.

Publications made by the help desk views are left alone (they do all of this themselves). No mail is sent by the
event.
