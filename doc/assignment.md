# Assignment, claiming, priority and queue

Staff (`ticketsystem/manage`) decide who works on a ticket and where it belongs. Every change is kept in the
ticket's history (`ticketsystem_event`, shown to staff on the ticket page) and sends no mail to the customer.

## Assign and claim

- **Take it** (claim): the ticket is assigned to the signed-in staff member. `ticketsystemService::assign( $t, 0 )`.
- **Assign** to another member of `[AssignmentSettings] StaffGroup` (default: the Technical Support Group), or to
  nobody. Only enabled users with `ticketsystem/manage` who may read the ticket can be assigned; anyone else is
  refused. The new assignee follows the ticket (a notification subscription) and gets a short mail "Assigned to you".
- **Claim on reply**: a staff reply to an unassigned ticket assigns it to the replier
  (`[AssignmentSettings] ClaimOnReply=enabled`).
- **Round-robin per queue**: `AutoAssign[<category>]=enabled` assigns a new ticket of that queue (and a ticket moved
  into it) to the staff member with the fewest active assigned tickets; on a tie, to the one whose last assignment is
  the oldest.

The index row keeps `assignee_id` and `assigned` (the time). The staff list filters by them:
`ticketsystem/list/(assignee)/me`, `(assignee)/none` or `(assignee)/<user id>`; the dashboard summary
(`ticketsystemDesk::summary()`) counts the unassigned and the reader's own tickets.

## Priority and queue

- `ticketsystemService::changePriority( $t, $priority )`: one of `[PrioritySettings] Priorities[]`. The index gets the
  priority and its rank (`priority_rank`, the position in the settings, used for sorting on every database), the
  ticket object's attribute `priority` follows (a new version), and an SLA target is recomputed for the new priority
  (with the time the clock was paused kept).
- `ticketsystemService::changeQueue( $t, $category )`: one of `[CategorySettings] Categories[]`. The attribute
  `category` follows; a queue with round-robin assigns the ticket when it has nobody.

The installer adds the labels of the settings that the class's selections lack (state, category, priority) as new
options, so the attributes can always follow; existing options keep their ids.

## Who may

| Action | Needs |
|---|---|
| take, assign, unassign | `ticketsystem/manage` and read access to the ticket |
| priority, queue | `ticketsystem/manage` and read access to the ticket |
| be assigned | an enabled member of StaffGroup with `ticketsystem/manage` who may read the ticket |

Customers can do none of these.
