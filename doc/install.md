# Installation and update

1. Get the extension: `composer require se7enxweb/ticketsystem` (or clone it to `extension/ticketsystem`).
2. Activate it in `settings/override/site.ini.append.php`:
   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=ticketsystem
   ```
3. Regenerate the autoloads: `php bin/php/ezpgenerateautoloads.php -e`.
4. Run the installer, first as a dry run:
   ```bash
   php extension/ticketsystem/bin/php/install.php -s <admin siteaccess> --dry-run \
       --customer-group="Members" --front-siteaccess=<front> --admin-siteaccess=<admin>
   php extension/ticketsystem/bin/php/install.php -s <admin siteaccess> \
       --customer-group="Members" --front-siteaccess=<front> --admin-siteaccess=<admin>
   ```
   It creates what is missing, and only that:
   - the tables `ticketsystem_ticket` and `ticketsystem_counter` (`sql/mysql`, `sql/postgresql`, `sql/sqlite`;
     on Oracle and MongoDB the kernel's schema handler creates them from `ticketsystemSchema::definition()`,
     `sql/oracle/schema.sql` and `sql/mongodb/schema.json` show the result for creating them by hand and are
     rewritten with `bin/php/install.php --write-schema-files`). On Oracle the column `number` is a reserved
     word: it is created as `"NUMBER"`, and `settings/ezoracle.ini.append.php` makes the driver quote it in every
     query (ezoracle with `[QuotedIdentifierSettings] TypeColumnNames`);
   - the class group "Help desk" with the classes `ticketsystem`, `ticket`, `ticket_comment` and
     `ticket_attachment`; existing classes from ticketsystem 1.x are kept and get the attributes they lack
     (`ticket_number`, `subscription`);
   - the user group "Technical Support Group" below the Users root with the role "Ticket Support";
   - the role "Ticket Customer", assigned to every group of `--customer-group` (comma separated).
   Run it again after an update; it ends with PASS or FAIL. Coming from 1.0, it adds the columns 1.1 needs to
   `ticketsystem_ticket`, creates the new tables, completes the options of the ticket class's selections from the
   settings and indexes the posts for the staff search; back up the `ticketsystem_*` tables first
   ([databases.md](databases.md)).
5. Settings: copy what you change from `settings/ticketsystem.ini` to `settings/override/ticketsystem.ini.append.php`
   (at least `[NumberSettings] Prefix` and `[MailSettings] Sender`), see [settings.md](settings.md).
6. Clear the caches (`php bin/php/ezcache.php --clear-all`) and reload PHP-FPM.
7. If the site runs exp_request_shield, let the forms through (EXP-POST allows POST only where the kernel takes
   forms), in the site's main rule file:
   ```
   [SITE-TICKETS-POST] allow POST **/ticketsystem/new **/ticketsystem/view/**
   [SITE-TICKETS-RATE] allow POST **/ticketsystem/rate/**
   ```
8. The cronjob part `ticketsystem` runs the SLA escalation and closes resolved tickets
   (`php runcronjobs.php -s <front> ticketsystem`, every 5 minutes; [sla.md](sla.md)).
9. Link the help desk from the site, e.g. `ticketsystem/list` in the user menu.

Support staff are put into the group "Technical Support Group". They work in the same views as customers and see
every ticket.
