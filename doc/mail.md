# Mail

Every post is mailed: the opening post and every reply, staff's and the customer's own.

- **Who**: everyone subscribed to the ticket node (an Exponential notification subscription) who may still read the
  ticket, and the poster. The opener and everyone who posts are subscribed automatically; a user can unsubscribe in
  the kernel's notification settings. `[MailSettings] StaffRecipients[]` adds further addresses.
- **What**: one mail per reader (nobody sees another reader's address), `multipart/alternative`: a text part and an
  HTML part in the kernel's mail layout (`design:mail/layout.tpl`), both with the **full post**, the ticket's number,
  subject, status, category and priority, and a link to the ticket. The poster's copy says it is their own message.
- **Subject**: `[7X-2026-00042] <subject>`, replies `Re: [7X-2026-00042] <subject>`.
- **Threading**: `Message-ID` per post, `References` and `In-Reply-To` to the ticket, `X-Ticket-Number`.
- **Sender**: `[MailSettings] Sender` (else site.ini `EmailSender`, then `AdminEmail`), `SenderName`, `ReplyTo`.
- **Links**: `[MailSettings] SiteURL` (else site.ini `SiteURL`).

The kernel's own subtree mails are not made for ticket objects: the help desk publishes them without the
notification event, so a ticket is never mailed to someone subscribed to the Users tree who may not read it.

## Test addresses

Mails to `[MailSettings] CaptureDomains[]` (default `invalid`, i.e. `*.example.invalid`) are never sent; they are
written as `.eml` files to `CaptureDirectory` (default `var/log/ticketsystem-mail`). `var/log/ticketsystem.log`
counts the mails of each post (sent, captured, failed), never an address.
