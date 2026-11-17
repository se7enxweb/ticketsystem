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
- The scanner's exit code: 0 clean; 1 a virus was found ("The file did not pass the virus check."); anything else the
  scanner failed ("Files cannot be checked right now."). Every code but 0 refuses the file and is logged in
  `var/log/ticketsystem.log` (the code only).

## Virus scanning with ClamAV

On Enterprise Linux 9 (EPEL) or Debian/Ubuntu:

1. Install the scanner daemon and the signature updater: `dnf install clamav clamd clamav-update` (EPEL), or
   `apt install clamav-daemon clamav-freshclam`. clamd needs about 1 GB of memory; with less to spare use
   `clamscan` on demand instead (slower: it loads the signatures for every file).
2. Configure clamd's local socket (EL: `/etc/clamd.d/scan.conf`: remove `Example`, set
   `LocalSocket /run/clamd.scan/clamd.sock`, `LocalSocketGroup virusgroup`, `LocalSocketMode 660`, and
   `StreamMaxLength`, `MaxFileSize` above `MaxFileSize` of the help desk, e.g. 30M) and remove `Example` from
   `/etc/freshclam.conf`.
3. Let the web server's PHP user use the socket: add it to the socket's group (`usermod -a -G virusgroup <user>`) and
   reload PHP-FPM, so its workers get the group.
4. Fetch the signatures and start both services: `freshclam`, `systemctl enable --now clamav-freshclam clamd@scan`.
5. Set the command in `settings/override/ticketsystem.ini.append.php`:

   ```ini
   [AttachmentSettings]
   ScanCommand=clamdscan --no-summary --fdpass --config-file=/etc/clamd.d/scan.conf %file
   RequireScan=enabled
   ```

   `--fdpass` hands the open file to clamd, so clamd needs no read access to PHP's upload directory.
   `RequireScan=enabled` refuses every file while the command is missing.
6. Check it with the EICAR test string (a harmless file every scanner reports): a `.txt` file with
   `X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*` is refused, an ordinary text file is
   accepted.

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
