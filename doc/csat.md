# Satisfaction ratings (CSAT)

When a ticket is resolved or closed, its opener is asked once how happy they are with the help: a score from 1 to 5
(5 = very good, 1 = very bad) and an optional comment. Settings: `[CSATSettings]` ([settings.md](settings.md)).

## The mail "How did we do?"

The first change of a ticket to a done status (Resolved or Closed, by staff, by the customer or with a staff reply)
mails the opener one message with a link per score:

```
https://example.com/ticketsystem/rate/<token>/<score>
```

- **No sign-in**: the token is signed with HMAC-SHA256 over the ticket's id, its number, its opening time and the
  expiry (`[CSATSettings] TokenSecret`, else a secret derived from the installation), so a token fits one ticket only
  and cannot be extended.
- **One use**: a ticket with a rating takes no token any more. A link works for `TokenDays` (30).
- **One click to choose, one to send**: the link opens a page with the score already chosen, a comment field and
  "Send the rating". Opening a link stores nothing, so a mail program that checks links rates nothing.
- The page names the ticket's number only, never its subject or anything of the account.
- The mail goes out once per ticket (history event `csat_ask`); reopening and solving again asks no more.
- **No mail** for tickets of `QuietSources[]` (`whmcs`: the history imported from WHMCS), for tickets that are not done,
  that have a rating, or whose opener is disabled. The WHMCS import itself never changes a status through the help
  desk, so it never triggers the mail.
- `AskInMail=disabled` keeps the mail and lets only the ticket page ask.

## On the ticket page

The opener and the users of the ticket's account can rate a resolved or closed ticket on its page ("How did we do?")
and change their rating while it stays done. Staff see the score and the comment on the ticket.

## Results

| | |
|---|---|
| `ticketsystem/satisfaction` | staff (policy `ticketsystem/report`, read scope All): the share of satisfied ratings (4 and 5), the average, the distribution, per queue and per assignee, how many were asked and how many answered, the latest ratings with comments; `(days)/7`, `30`, `90`, `365` |
| `fetch( 'ticketsystem', 'csat_summary', hash( 'from', $ts, 'to', $ts, 'category', '', 'assignee_id', 0 ) )` | the aggregate: count, avg, csat (%), dist (1-5), by_queue, by_assignee (each with count, avg, csat, dist, label), asked, response_rate |
| `fetch( 'ticketsystem', 'csat_latest', hash( 'from', $ts, 'to', $ts, 'offset', 0, 'limit', 50 ) )` | the latest ratings |
| `fetch( 'ticketsystem', 'rating', hash( 'ticket_id', $id ) )` | one ticket's rating, for its readers |

The period is the time of the rating (`rated`). The rating is stored on the ticket (`rating`, `rating_comment`,
`rated`) and in its history (`rate`, with the channel `web` or `mail`).

Code: `ticketsystemCsat` (tokens and figures are pure), `ticketsystemSlaMailer::rating()`, the view
`modules/ticketsystem/rate.php`. Tests: `tests/ticketsystemSlaTest.php`.

## Request shield

`ticketsystem/rate/...` takes a POST without a signed-in user. A site with exp_request_shield allows it in its main
rule file, next to the other forms of the help desk:

```
[SITE-TICKETS-RATE] allow POST **/ticketsystem/rate/**   # the rating link of the mail "How did we do?"
```
