# Reply by e-mail

A customer or a staff member answers a ticket mail in their own mail program, and the answer becomes a post on the
ticket, as if they had written it on the site. Everybody who follows the ticket gets the usual copy (the sender too).

## How a mail finds its ticket

- **The signed reference**: every ticket mail carries `ts.<number>.<user id>.<signature>` in its `Message-ID` (one
  per reader). A reply names that id in `In-Reply-To` and `References`, so no mail setting is needed. With
  `[MailInSettings] ReplyAddress=support+%token@example.com` the reference is in the `Reply-To` address as well
  (the mail server must deliver `+` addresses to the mailbox; Postfix with `recipient_delimiter = +` does). The
  signature is an HMAC of the ticket number and the reader's user id: nobody can make one for another ticket or
  another person. Its secret is `TokenSecret`, else a random one kept in the database (`ezsite_data`
  `ticketsystem_reply_secret`).
- **The number in the subject** (`Re: [7X-2026-00042] ...`) when `RequireToken=disabled` (the default).

## Who may post

- The `From` address must be the e-mail of an enabled user of the site.
- With a reference, it must be the user the reference was made for.
- With only a number, the receiving server's `Authentication-Results` must not report a failed SPF, DKIM or DMARC
  check.
- The user must be allowed to reply to the ticket on the site: the ticket's customer and the users of its account,
  and staff. Followers can post once their policy lets them reply.

Anything else is **refused**: quarantined (`RefusedAction=quarantine`, a copy in `QuarantineDirectory`, 0600) or,
for the pipe, bounced by the mail server (`RefusedAction=bounce`: exit code 77 with the reason). A sender who is a
user of the site gets a short notice (`NoticeToSender`); an unknown sender never gets mail, so forged senders cause
no backscatter.

## What is posted

- The new text only: quoted lines (`>`), everything from an attribution line ("On ... wrote:", "Am ... schrieb ...:"),
  an "Original Message" or `From:`/`Sent:` block, a signature (`-- `) or "Sent from my ..." on is cut. A mail
  without new text is refused.
- HTML-only mails are turned into text.
- Files: attached to the post through the help desk's attachment storage ([attachments.md](attachments.md)), checked
  like uploads (types, size, number, the virus scanner); a refused file is left out and counted in the result. With
  `[AttachmentSettings] Enabled=disabled` they are kept for staff in `HoldDirectory` (`<number>/<post id>/`, 0600).
- Never twice: the `Message-ID` is kept in `ticketsystem_mailin`. Automatic mail (auto-replies, mailing lists,
  bounces, delivery reports, and the help desk's own ticket mails, which carry `X-Ticket-Number`) is ignored.
- **New tickets by mail** (`OpenNewTickets`, off by default): a mail that names no ticket opens a new ticket when its
  sender is a user who may open tickets.

Results: `posted`, `opened`, `duplicate`, `ignored`, `quarantined`, `bounced`, `disabled`. The log
(`var/log/ticketsystem.log`) names the result, the reason and the ticket number, never an address or a text; the
staff page `ticketsystem/mailin` shows the latest results.

## Receiving the mail

### A: poll a mailbox (cronjob)

The cronjob part `ticketsystem_mailin` fetches the mail of an IMAP or POP3 mailbox (plain PHP streams, no PHP
extension):

```ini
# settings/override/ticketsystem.ini.append.php
[MailInSettings]
Enabled=enabled
Protocol=imap
Host=mail.example.com
Encryption=ssl
User=support@example.com
Password=...
Folder=INBOX
DoneFolder=
TicketMailOnly=enabled
```

```cron
* * * * * cd /path/to/exponential && php runcronjobs.php -q -s <siteaccess> ticketsystem_mailin
```

With IMAP and `TicketMailOnly=enabled` (the default) only mail that looks like a ticket reply is fetched (a
reference in `In-Reply-To` or in a `+` address, or a ticket number in the subject); every other mail in a shared
support mailbox stays untouched and unread. A handled mail is marked seen and, with a `DoneFolder`, moved there.
POP3 takes and deletes every mail: use it only for a mailbox of its own. `php bin/php/mailin.php --poll` runs the
same once by hand.

### B: a pipe from the mail server

`bin/php/mailin.php` takes one mail on stdin:

```bash
php extension/ticketsystem/bin/php/mailin.php -s <siteaccess> < message.eml
```

Exit code 0 when handled, 77 when bounced, 75 when reply by e-mail is disabled (the mail server keeps the mail and
tries again later). For Postfix, an alias that pipes to a small wrapper running the script as the site's user:

```
# /etc/aliases (or a virtual alias to a local pipe address)
tickets: "|/usr/local/bin/ticketsystem-mailin"
```

```bash
#!/bin/sh
cd /path/to/exponential && exec sudo -u <site user> php extension/ticketsystem/bin/php/mailin.php -s <siteaccess>
```

`--file=<message.eml>` and `--maildir=<Maildir>` (every mail of `new/`, moved to `cur/` when handled) are there for
tests and for mail delivered into a Maildir.

## Settings

See `[MailInSettings]` in `settings/ticketsystem.ini`: `Enabled`, `ReplyAddress`, `TokenSecret`, `RequireToken`,
`OpenNewTickets`, `RefusedAction`, `QuarantineDirectory`, `NoticeToSender`, `HoldDirectory`, `MaxMessageSize`,
`IgnoreSenders[]`, and the mailbox: `Protocol`, `Host`, `Port`, `Encryption`, `VerifyPeer`, `User`, `Password`,
`Folder`, `TicketMailOnly`, `DoneFolder`. Keep the mailbox password in `settings/override`, never in the
extension.

## Switching it on for se7enx.com (support@se7enx.com)

The live mailbox, its forwarding and the Plesk mail settings stay as they are; nothing below changes them.

1. Deploy this version and run `bin/php/install.php` (creates `ticketsystem_mailin` where it is missing).
2. In `settings/override/ticketsystem.ini.append.php` of se7enx.com:
   ```ini
   [MailInSettings]
   Enabled=enabled
   ReplyAddress=support+%token@se7enx.com
   Protocol=imap
   Host=<the mail host of se7enx.com>
   Encryption=ssl
   User=support@se7enx.com
   Password=<the mailbox password>
   TicketMailOnly=enabled
   DoneFolder=
   ```
   `+` addresses reach the support@ mailbox already (Postfix `recipient_delimiter = +`).
3. Clear the INI cache, then add the cron line (as the site user):
   `* * * * * cd <site root> && php runcronjobs.php -q -s <siteaccess> ticketsystem_mailin`.
4. Test: reply to a ticket mail of a test ticket; within a minute the reply is on the ticket and
   `ticketsystem/mailin` shows `posted`.
