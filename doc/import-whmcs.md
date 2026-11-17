# Importing WHMCS tickets

The help desk can take over the support tickets of a WHMCS installation as history: every WHMCS ticket becomes a
help desk ticket of its customer, with its replies, internal notes, dates, status, priority and department.

## How it works

1. **Export** on the WHMCS host, read only: `bin/php/whmcs_export.php` uses WHMCS's own database layer (Capsule)
   and prints the tickets as JSON. It writes nothing to WHMCS.
2. **Import** into Exponential: `bin/php/import_whmcs.php` (or the staff page `ticketsystem/import`) reads that JSON.
   It is a **dry run unless `--execute`** is given.

The export carries ids, dates, subjects, messages, statuses, urgencies, the department, the WHMCS admin id of each
staff reply and the number of attached files. It never carries a client name, an e-mail address or an IP address.
It still holds customers' messages: keep it private (0600) and remove it after the import.

### Rules

- **Customers are found by the WHMCS client id only, never by an e-mail address.** With `[ImportSettings]
  ClientResolver=katalyst` (the default) the import looks up the Katalyst customer linked to the client
  (`katalyst_customer_ref`, source `whmcs`) and posts as that customer's website user; the ticket belongs to the
  customer's account (`katalyst:<id>`), so everyone of that account sees it. A client map
  (`--client-map=<csv of "WHMCS client id,user id">`) can be given instead or on top.
- **Skipped and counted**: guest tickets (no client), clients without a Katalyst customer here
  (`skipped_no_customer`), customers without a website user (`skipped_no_user`). A later run picks them up once the
  customer has a user.
- **Idempotent**: every ticket, reply and note is recorded in `ticketsystem_import` and never made twice. A second
  run adds only what is new (a reply written in WHMCS after the first run).
- **Silent**: nothing is mailed and nobody is notified. Posts are published without the kernel's notification and
  without the help desk's mailer, and nobody is subscribed to the imported tickets.
- **The ticket**: a `ticket` object below the opener's user node, owned by the opener, dated as in WHMCS; a number of
  the year it was opened (`7X-2024-00001`); the opening post ends with a line naming the WHMCS ticket, its
  department and its WHMCS status.
- **Status**: `[ImportSettings] StatusMap[<WHMCS status>]=<status>`; a status not listed becomes the status set's
  *Closed* (the default: the past tickets are closed history).
- **Priority**: the WHMCS urgency (Low, Medium, High) when the help desk has a priority of that name, else the
  default priority.
- **Category**: `[ImportSettings] DepartmentMap[<WHMCS department id>]=<category>`, else the category whose name
  is the department's name, else the default category.
- **Staff replies and notes**: as the user of `StaffUserMap[<WHMCS admin id>]`, else as `StaffUserID`, a named
  import user (create e.g. "Help desk import" in the Technical Support Group; 0 = the administrator, 14).
- **Internal notes**: imported as the help desk's internal notes ([notes.md](notes.md)), never shown to the customer;
  an installation without internal notes counts them as `notes_waiting`.
- **Files**: WHMCS keeps attachments in its own folder; they are counted (`files_not_copied`) and stay in WHMCS.

## Running it

On the WHMCS host, as the WHMCS site user with WHMCS's PHP:

```bash
php whmcs_export.php /path/to/whmcs --counts            # counts only, no content
php whmcs_export.php /path/to/whmcs > whmcs-tickets.json  # the export; chmod 600
```

Copy the file to the Exponential host, then from the installation's root:

```bash
php extension/ticketsystem/bin/php/import_whmcs.php -s <admin siteaccess> --file=whmcs-tickets.json            # dry run
php extension/ticketsystem/bin/php/import_whmcs.php -s <admin siteaccess> --file=whmcs-tickets.json --execute  # import
php extension/ticketsystem/bin/php/import_whmcs.php -s <admin siteaccess> --file=whmcs-tickets.json --execute  # again: 0 new
```

Both print counts only and end with PASS or FAIL:

| Count | Meaning |
|---|---|
| `tickets` | tickets in the export |
| `new_tickets` | tickets this run imports (a dry run: would import) |
| `already` | tickets imported before |
| `skipped_guest`, `skipped_no_customer`, `skipped_no_user` | not imported, see the rules |
| `new_replies`, `new_notes` | replies and notes this run adds |
| `notes_waiting` | notes waiting for the help desk's internal notes |
| `files_not_copied` | files that stay in WHMCS |
| `failed` | errors (the debug log has the WHMCS id) |

Without `--file` the file of `[ImportSettings] ExportFile` (default `var/ticketsystem/whmcs-tickets.json`) is read.

## The staff page

`ticketsystem/import` (policy `ticketsystem/manage`) shows the export file's counts, a dry run of what an import would
do now and what was imported so far. Staff can upload an export (stored as `ExportFile`, 0600) and run the import
with one button. The page shows counts only. It is a plain page of the standard design; the administration designs
restyle it.

## Settings

```ini
[ImportSettings]
ExportFile=var/ticketsystem/whmcs-tickets.json
ClientResolver=katalyst
Enabled=disabled
StaffUserID=0
StaffUserMap[]
StatusMap[]
DepartmentMap[]
```
