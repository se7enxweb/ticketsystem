# Settings

All in `settings/ticketsystem.ini`, each with a comment there. Override them in
`settings/override/ticketsystem.ini.append.php` or a siteaccess.

| Block | Setting | Default | Meaning |
|---|---|---|---|
| TicketSettings | TicketNodeID | 0 | the folder listed by `ticketsystem/tickets` (1.x); also the parent with `Placement=node` |
| | TicketClass, PostClass, AttachmentClass | ticket, ticket_comment, ticket_attachment | class identifiers |
| | Placement | user | `user`: below the opener's user node; `node`: below TicketNodeID |
| NumberSettings | Prefix | TS | letters and digits, at most 16 |
| | Digits | 5 | width of the counter |
| | Format | %prefix-%year-%seq | the number |
| StatusSettings | StatusSet | 7x | the `[StatusSet_<name>]` in use ([statuses.md](statuses.md)) |
| | AutoCloseDays | 7 | resolved tickets close after this many days (0: never) |
| CategorySettings | Categories[], Default | general ... other | identifier => label |
| PrioritySettings | Priorities[], Default | low, medium, high, urgent; medium | identifier => label |
| AccountSettings | Resolver | auto | `auto`, `katalyst`, `owner` ([permissions.md](permissions.md)) |
| MailSettings | Sender, SenderName, ReplyTo | site.ini | the sender of the mails ([mail.md](mail.md)) |
| | SiteURL | site.ini SiteURL | the start of the links in mails |
| | Brand | site.ini SiteName | the name in mails |
| | CaptureDomains[], CaptureDirectory | invalid; var/log/ticketsystem-mail | test addresses are written to files, never sent |
| | StaffRecipients[] | none | further addresses for every post |
| ChromeSettings | Chrome | auto | `auto`, `katalyst`, `standard` ([design.md](design.md)) |
| SLASettings | Enabled | enabled | SLA timers and escalation ([sla.md](sla.md)) |
| | DefaultLevel, Levels[] | standard | the level of tickets without a Katalyst SLA entitlement; the levels with a `[SLA_<level>]` block |
| | PausedStatuses[] | waiting_customer | the clock stands still in these statuses (and while resolved) |
| | Clock | business | what a number of minutes in `[SLA_<level>]` counts: `business` or `calendar` |
| | BusinessDays[], BusinessHours | 1-5, 09:00-17:00 | the working days (ISO, 1 = Monday) and hours |
| | TimeZone | the installation's | the time zone of the business hours |
| | Holidays[] | 01-01, 07-04, 12-25, Thanksgiving 2026 and 2027 | `YYYY-MM-DD` or `MM-DD` (every year) |
| | CalendarPriorities[] | urgent | numeric targets of these priorities run around the clock |
| | WarnPercent | 80 | "due soon" from this share of a target |
| | KatalystEntitlements | enabled | the level from the customer's Katalyst SLA entitlement |
| | EscalationSteps[] | 80:assignee, 100:leads, 100:raise, 100:mark | `<percent>:<action>` (assignee, leads, group, raise, mark) |
| | QueueLeads[] | none | `<category>` => object ids of users or user groups that get the leads alerts |
| | AlertRecipients[] | none | further staff addresses of the leads and group alerts |
| | CustomerHint | enabled | customers see when the first answer is due |
| SLA_&lt;level&gt; | Label, FirstResponse[], Resolution[] | standard: 960/480/240/60, 4800/2400/960/480 | per priority (or `<category>:<priority>`): minutes or a text like "8 business hours" |
| CSATSettings | Enabled | enabled | satisfaction ratings ([csat.md](csat.md)) |
| | AskInMail | enabled | the mail "How did we do?" on resolve or close |
| | TokenDays | 30 | days a rating link works |
| | TokenSecret | derived | the secret of the rating links |
| | QuietSources[] | whmcs | tickets of these sources never get the mail |
| ReportSettings | DefaultDays | 30 | the default report period: the last n days ([reports.md](reports.md)) |
| | MaxDays | 1100 | the longest period of one report |
| | MaxRows | 20000 | tickets counted per part of a report and in the CSV export |
| SearchSettings | Customers | disabled | `enabled`: customers search their own scope, never notes ([search.md](search.md)) |
| | MaxTextMatches | 2000 | posts and notes matched per search text |
| CannedSettings | Macros | enabled | `disabled`: canned replies change nothing but the text ([canned-replies.md](canned-replies.md)) |
| | MaxLength | 20000 | characters of a canned reply |
| LimitSettings | MaxSubjectLength, MaxBodyLength | 200, 50000 | characters |
| | MaxOpenPerHour | 10 | new tickets per user and hour |
| | ListLimit | 25 | tickets per page |
| | SearchLimit | 50 | search results per page |
