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
  On Exponential 6.0.15 and later the kernel's shared rule for automatic mail (`expMailReceive`: `Auto-Submitted`,
  `Precedence` and the `X-Autoreply` headers) applies first; the help desk's own additions follow.
- **New tickets by mail** (`OpenNewTickets`, off by default): a mail that names no ticket opens a new ticket when its
  sender is a user who may open tickets.

Results: `posted`, `opened`, `duplicate`, `ignored`, `quarantined`, `bounced`, `disabled`, and `failed` (the mail could
not be handled, e.g. the database was away: it stays unread on the server, or in the Maildir's `new/`, and is read
again at the next run; the other mails of the run are handled). The log
(`var/log/ticketsystem.log`) names the result, the reason and the ticket number, never an address or a text; the
staff page `ticketsystem/mailin` shows the latest results.

## Receiving the mail

### A: poll a mailbox (cronjob)

The cronjob part `ticketsystem_mailin` fetches the mail of an IMAP or POP3 mailbox (plain PHP streams, no PHP
extension). The mailbox can be **on any mail server** the web host can reach: the site's own server, a separate
mail or Plesk server, or a hosted service. Nothing has to run on the mail server itself; see
[the mailbox](#the-mailbox) below for what it must offer.

```ini
# settings/override/ticketsystem.ini.append.php
[MailInSettings]
Enabled=enabled
ReplyAddress=support+%token@example.com
Protocol=imap
Host=mail.example.com
Port=0
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

## The mailbox

Any IMAP (or POP3) mailbox works. It does not have to be on the server of the site, and the site's sender address
does not have to change: ticket mails keep their `From` (`[MailSettings] Sender`), only the `Reply-To` names the help
desk mailbox, so SPF, DKIM and DMARC of the sender's domain are not affected. What the mail server must offer:

- **Mail for the address arrives there**: the domain of `ReplyAddress` has an MX record pointing to that server (a
  domain without MX is delivered to its A record, but an explicit MX is the reliable choice).
- **`+` addresses reach the mailbox** for the token in `ReplyAddress` (`help+ts.…@example.com`). Postfix does this
  with `recipient_delimiter = +` (Plesk sets it), Google Workspace always. Without it, leave `%token` out
  (`ReplyAddress=help@example.com`): replies are still matched by the reference in `In-Reply-To`.
- **IMAPS reachable from the web host** (port 993 by default, any other with `Port`), with a certificate valid for the
  name in `Host`: `VerifyPeer=enabled` checks it, and the name is sent as SNI, so a server with one certificate per
  name hands out the right one.
- **A mailbox of its own is best.** With IMAP and `TicketMailOnly=enabled` a shared mailbox works too: only ticket
  replies are fetched, every other mail stays unread.

### Example: a mailbox on a separate Plesk server

The site runs on one server, the mailbox is on another Plesk server (`mail.example.net`), under a domain or domain
alias there with the mail service on:

1. On the mail server: `plesk bin mail --create help@example.net -mailbox true -passwd ''` with the password in the
   environment variable `PSA_PASSWORD` (so it is not on a command line). Check `postconf recipient_delimiter` (`+`).
2. A certificate for the IMAP host name: Plesk uses a domain's certificate for mail when it is chosen for mail
   (`plesk bin domain_pref --update mail.example.net -mail_certificate "<certificate name>"`); otherwise Dovecot
   answers with the server's default certificate. Check from the web host:
   `openssl s_client -connect mail.example.net:993 -servername mail.example.net -verify_return_error`.
3. DNS: `example.net. MX 10 mail.example.net.` where the zone is served.
4. On the site:
   ```ini
   [MailInSettings]
   Enabled=enabled
   ReplyAddress=help+%token@example.net
   Protocol=imap
   Host=mail.example.net
   Port=993
   Encryption=ssl
   User=help@example.net
   Password=<the mailbox password>
   TicketMailOnly=enabled
   ```
   If Dovecot listens for IMAPS on another port, put that port in `Port`. When the address is on a domain alias, the
   login (`User`) is the address under the main domain of the subscription.

### Example: Google Workspace

The help desk address is a Google Workspace mailbox (or an alias of one, such as `support@example.com`):

1. Turn on 2-step verification for that account and create an **app password** for it; IMAP must be allowed for the
   user in the admin console.
2. On the site:
   ```ini
   [MailInSettings]
   Enabled=enabled
   ReplyAddress=support+%token@example.com
   Protocol=imap
   Host=imap.gmail.com
   Port=993
   Encryption=ssl
   User=support@example.com
   Password=<the app password>
   TicketMailOnly=enabled
   DoneFolder=
   ```
   Gmail delivers `+` addresses to the mailbox. A handled mail is marked read; with `DoneFolder` (a label) it moves
   there.

## Switching it on

1. Deploy this version and run `bin/php/install.php` (creates `ticketsystem_mailin` where it is missing).
2. Put the mailbox into `[MailInSettings]` of `settings/override/ticketsystem.ini.append.php` (as above). The file
   holds a password: readable only by the site's user and the web server (for example 0640).
3. Clear the INI cache, then add the cron line (as the site user):
   `* * * * * cd <site root> && php runcronjobs.php -q -s <siteaccess> ticketsystem_mailin`.
   `php extension/ticketsystem/bin/php/mailin.php -s <siteaccess> --poll` runs it once by hand and says how many
   mails it handled, or why it could not log in.
4. Test with a test user and a test ticket: the ticket mail carries `Reply-To: help+ts.<number>.<user>.<signature>@…`;
   a reply to it is on the ticket within a minute and `ticketsystem/mailin` shows `posted`; a reply from an address
   that is no user of the site shows `quarantined`.
