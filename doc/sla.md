# SLA timers and escalation

Every ticket gets two targets when it is opened: a **first response** (the first reply of support staff) and a
**resolution** (the ticket resolved). Their due times are stored on the ticket (`response_due`, `resolve_due`), shown
on the staff list, the ticket page and the SLA board, and watched by the cronjob part `ticketsystem`, which escalates
the ticket in steps when time runs out.

Settings: `[SLASettings]` and one `[SLA_<level>]` block per SLA level in `ticketsystem.ini` ([settings.md](settings.md)).

## Targets

A target belongs to an SLA level, a priority and, optionally, a queue (category):

```ini
[SLA_standard]
Label=Standard
FirstResponse[]
FirstResponse[low]=960
FirstResponse[medium]=480
FirstResponse[high]=240
FirstResponse[urgent]=60
# a queue's own target: <category>:<priority>
FirstResponse[billing:high]=120
Resolution[]
Resolution[medium]=2400
```

The lookup: the level's queue value, the level's value, for a first response the Katalyst level's text (below), then
the same in `DefaultLevel`. A priority without a value has no target.

A value is either a number of minutes or a text:

| Value | Counts |
|---|---|
| `480` | minutes on `[SLASettings] Clock` (business by default); `CalendarPriorities[]` (urgent) always around the clock |
| `8 business hours`, `2 business days`, `1 working day` | business hours; a business day is the length of `BusinessHours` (8 h) |
| `30 minutes`, `4 hours`, `1 day`, `1 week` | around the clock |

The shipped level `standard` (business hours unless noted):

| Priority | First response | Resolution |
|---|---|---|
| Low | 2 business days | 10 business days |
| Medium | 1 business day | 5 business days |
| High | 4 business hours | 2 business days |
| Urgent | 1 hour, around the clock | 8 hours, around the clock |

### Katalyst SLA levels (per plan)

With Katalyst and `KatalystEntitlements=enabled`, the level of a new ticket is the customer's active SLA entitlement
(`expKatalystEntitlement`, kind `sla`, for the ticket's subscription or the whole account) with the shortest first
response for the ticket's priority, else `DefaultLevel`. Such a level (`sla1` ... `sla7` in `katalyst.ini`) needs no
block here: its first response comes from its `Response<Severity>` text (urgent = critical, high = major,
medium = minor, low = request), e.g. "4 business hours" or "30 minutes"; its resolution from `DefaultLevel`. A block
`[SLA_sla5]` (and `Levels[]=sla5`) overrides it.

## Business hours and holidays

```ini
[SLASettings]
Clock=business
BusinessDays[]=1      # ISO weekdays, 1 = Monday ... 7 = Sunday
BusinessHours=09:00-17:00
TimeZone=             # e.g. America/Los_Angeles; empty = the installation's
Holidays[]=12-25      # every year
Holidays[]=2026-11-26 # that day
CalendarPriorities[]=urgent
```

The clock (`ticketsystemBusinessClock`) walks the days in its time zone, so the opening hours follow the local clock
over daylight saving time. A start outside the hours counts from the next opening: a ticket opened on Friday at 16:30
with a 1 hour first response is due on Monday at 09:30. A clock without a working day, or with a closing before its
opening, counts around the clock.

### The defaults of se7enx.com

Confirmed by the owner 2026-11-17: Monday to Friday 09:00-17:00 America/Los_Angeles (the installation's time zone);
the holidays 01-01, 07-04, 12-25 and Thanksgiving 2026 (2026-11-26) and 2027 (2027-11-25); urgent tickets around
the clock; the targets of the level `standard` in the table above. Queue leads are not named yet: escalations go to
the support group (Technical Support Group).

## Pausing

While a ticket has one of `PausedStatuses[]` (default: Waiting on customer) or is resolved, the clock stands still.
When it goes on, both open due times move on by the **working** time spent paused: waiting on the customer from
Monday 16:00 to Tuesday 10:00 moves the due times by 2 business hours, a pause over a weekend by nothing of the
weekend. An answered first response does not move. A target that was missed before the pause stays missed.

## States

| State | Meaning |
|---|---|
| On track (`ok`) | less than `WarnPercent` (80 %) of the target's working time used |
| Due soon (`warn`) | from `WarnPercent` |
| Breached (`breached`) | the due time has passed (or the target was met late) |
| Paused | the ticket waits on the customer |
| Met | answered or resolved in time |

The staff list (`ticketsystem/list`) shows the state of the target that matters now (the first response until it is
met, then the resolution) with the working time left; the ticket page shows both targets, the escalation level and
the steps that ran. A customer sees "First answer expected by ..." until staff has answered (`CustomerHint`).

## Escalation

The cronjob part `ticketsystem` (`cronjobs/ticketsystem_sla.php`, every 5 minutes) runs the steps of
`EscalationSteps[]` on every active ticket with a target:

```ini
EscalationSteps[]=80:assignee   # at 80 % of a target: mail the assignee (nobody assigned: the leads)
EscalationSteps[]=100:leads     # missed: mail the queue's leads, else the support group
EscalationSteps[]=100:raise     # missed: the priority one step up (the targets stay those of the opening)
EscalationSteps[]=100:mark      # missed: mark the ticket escalated
```

Actions: `assignee`, `leads` (`QueueLeads[<category>]` = object ids of users or user groups; none: every member of
`[AssignmentSettings] StaffGroup`; plus `AlertRecipients[]`), `group` (the whole support group plus
`AlertRecipients[]`), `raise`, `mark`.

- Each step runs **once** per ticket and target (`raise` and `mark` once per ticket): it is written to the escalation
  log (`ticketsystem_escalation`, unique per ticket and step key) before it acts, and a lock file keeps two runs of the
  cronjob apart.
- Mails go to **staff only**: users with `ticketsystem/manage` who may read the ticket, and the configured staff
  addresses. They name the ticket, the target, the due time, the level, the priority, the queue and the assignee.
- Every step writes a history event (`escalate`) and its mail counts (recipients, sent, failed) to the log, and
  `ticketsystem.log` gets one line per mail run, without addresses.
- The ticket keeps the highest level: 1 (a step below 100 %) or 2 (a missed target), and the breach flags
  (1 first response, 2 resolution). The staff desk filters "escalated" (level 1 or more) and "breached".

Staff escalate by hand with **Escalate** on the ticket page: one level up, the leads alerted, the ticket marked.

## Pages and fetch functions

| | |
|---|---|
| `ticketsystem/sla` | the SLA board (policy `ticketsystem/manage`): counts per state, the active tickets most urgent first, the latest escalation steps; `(state)/breached`, `warn`, `ok`, `paused` |
| `fetch( 'ticketsystem', 'sla', hash( 'ticket_id', $id ) )` | staff: both targets, labels, level, the log; a customer: the hint |
| `fetch( 'ticketsystem', 'sla_board', hash( 'state', '', 'limit', 100 ) )` | staff: the board |
| `fetch( 'ticketsystem', 'escalations', hash( 'ticket_id', $id ) )` | staff: the escalation log of a ticket |

Code: `ticketsystemSla` (pure: targets, clocks, states, steps), `ticketsystemBusinessClock` (pure),
`ticketsystemEscalation` (the runs, the board), `ticketsystemSlaMailer` (the staff mails). Tests:
`tests/ticketsystemSlaTest.php`.

## Running it

```
php runcronjobs.php -s <siteaccess> ticketsystem
```

It also closes resolved tickets after `[StatusSettings] AutoCloseDays`. Tickets opened while SLA was off have no
targets and are never escalated.
