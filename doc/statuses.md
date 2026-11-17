# Status sets

`[StatusSettings] StatusSet` chooses a `[StatusSet_<name>]` block of ticketsystem.ini.

| Set | Statuses |
|---|---|
| `7x` (default) | Open, Waiting on customer, Waiting on staff, On hold, Resolved, Closed |
| `original` | New, Open, Started, Reopened, Resolved, Verified, Confirmed, Closed, Invalid, Deferred (ticketsystem 1.x) |

A set names its statuses (`Statuses[<id>]=<label>`, in order), their colors (`Tone[<id>]`: ok, warn, bad, info,
muted) and the roles:

| Role | 7x | When |
|---|---|---|
| `Default` | open | a ticket is opened |
| `CustomerReply` | waiting_staff | the customer posts |
| `StaffReply` | waiting_customer | staff post (staff can choose another status with the reply) |
| `Resolved` | resolved | the customer marks it solved |
| `Reopen` | waiting_staff | a solved ticket gets a customer post or is reopened |
| `Closed` | closed | the cronjob closes a resolved ticket after `AutoCloseDays` |

`DoneStatuses[]` are not counted as open in the lists; `ClosedStatuses[]` take no posts (open a new ticket instead).
`StaffChoices[]` are the statuses staff can set.

The options of the class attribute `state` are the labels of the set in use when the class is created. A site that
changes the set later keeps working (the index holds the status); the installer reports labels the attribute lacks.
