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
| LimitSettings | MaxSubjectLength, MaxBodyLength | 200, 50000 | characters |
| | MaxOpenPerHour | 10 | new tickets per user and hour |
| | ListLimit | 25 | tickets per page |
