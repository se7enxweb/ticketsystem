# Attachments

Files of a new ticket, of a reply and of an internal note (`[AttachmentSettings]`).

## Limits and checks

- At most `MaxFiles` files per post (5) of at most `MaxFileSize` MB each (25).
- Only the extensions of `AllowedExtensions[]` (pdf, images, text and logs, csv, json, xml, zip, gz, Office and
  OpenDocument files, eml), and the content must match the extension (checked with PHP's fileinfo: a PHP script
  named `.pdf` is refused, as is anything that sniffs as HTML or an executable).
- A virus scanner: `ScanCommand` (e.g. `clamdscan --no-summary --fdpass %file`; exit code 0 = clean) runs on every
  file before it is stored; `RequireScan=enabled` refuses every file while no scanner is set. Without a scanner the
  files are stored unscanned (the settings page of the administration says so).
- The file name is cleaned (no path, no control characters, at most 200 characters).

## Storage

Files are stored below `StorageDirectory` (absolute, or relative to the installation; default
`var/ticketsystem/attachments`) under a random name in two levels of folders, mode 0640, never in the public storage
(`var/storage`). Put `StorageDirectory` outside the web root in production, e.g.
`/var/www/vhosts/<domain>/ticketsystem-files`. `ticketsystem_attachment` records the ticket, the post (the content
object of the reply, or the ticket object for the opening post) or the note, the uploader, the name, the type found,
the size and the SHA-256.

## Download

`ticketsystem/file/<id>` (policy `read`) serves a file only to a user who may read its ticket, and the file of an
internal note only to staff; anything else answers "not found". Always as a download: `Content-Disposition:
attachment`, the type found on upload, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store` and a
sandboxing Content-Security-Policy.

When the ticket's owner is removed (the test fixture's cleanup, or by hand), `ticketsystemFiles::remove()` deletes the
file with its row.
