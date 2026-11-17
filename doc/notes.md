# Internal notes

Staff write internal notes on a ticket: what they tried, what the customer must not read, a hand-over. A note is
for staff only, always:

- **never shown to a customer**: notes live in their own table (`ticketsystem_note`), not as content objects below the
  ticket, so neither the ticket views nor the content tree, the kernel's search or a content export can reach them.
  `ticketsystemService::notes()` answers only for staff (`ticketsystem/manage`) who may read the ticket; for anybody
  else it is an empty list;
- **never mailed**: writing a note sends no mail at all (not to the customer, not to the followers);
- **never in a customer's search**: the staff search (`ticketsystem/search`) looks into notes only for staff; the post
  index of the search (`ticketsystem_post`) holds customer-visible posts only;
- **never in an export**: the CSV of the reports carries index data only, never a note or the text of a post;
- **files of a note** (`NoteFiles[]`) are served by `ticketsystem/file/<id>` to staff only; a customer gets "not
  found", even for a ticket of their own.

The ticket's `note_count` shows on the staff list; the history records that a note was written (its id, not its text).

Forms: `TicketNote` with `TicketNoteBody` (at most `[LimitSettings] MaxNoteLength`, default 20000 characters) and
optionally `NoteFiles[]`, on `ticketsystem/view/<number>`; Katalyst's ticket page has the same.

The functional test of the extension's maintainers checks every point of this list with throwaway users on a real
installation: the customer's rendered ticket page, the mails, the post index, the customer's search and the CSV.
