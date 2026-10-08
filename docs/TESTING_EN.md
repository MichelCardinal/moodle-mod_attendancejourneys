# Running the automated tests

## RC12 renamed component validation — 8 October 2026

The renamed `mod_attendancejourneys` component passed 225 tests / 1,675 assertions on each of seven native configurations: Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 with PHP 8.3/MySQL, plus Moodle 5.3 with PHP 8.4/MySQL and PHP 8.3/PostgreSQL 17. Official phplint, savepoints, validate, phpcs and phpdoc checks passed; five AMD modules were rebuilt with native Grunt.

Separate full-site transition rehearsals preserved all records in 510 tables on a private 4.5 copy and 515 tables on a private 5.3 copy after normalization of the intended component references. Restoration to the predecessor was validated on both copies. A new prefix-only backup/restore rehearsal on the 4.5 copy also preserved all 510 tables without dropping a database. Target collisions and local translation preservation were checked separately. These are pre-publication laboratory procedures; the ZIP is not an ordinary upgrade from another component.

All 24 guide images were captured from the renamed activity using fictional participants, native Chrome and Boost, with eight images per language. Integrated English, French and Spanish guide rendering was reviewed. Sixteen plugin tables and fourteen grade tables were unchanged by the capture workflow. VoiceOver remains deferred. The following RC11 results describe historical predecessor validation, not a new certification of this component.

## RC11 administration validation — 8 October 2026

The RC11 functional suite passed **225 tests and 1,675 assertions** on all seven configurations in the table below. New cases cover administrative calculation policies, native settings saving, forced configuration, retained thresholds and delegated permissions. Private RC10-to-RC11 migration retained pedagogical data and cloned existing permissions, including local prohibitions. Eight real decision routes rejected requests lacking their specific permission. Administration pages were rendered in English, French and Spanish. These results describe local validation, not Marketplace certification. Native visual administration review remains part of the prepublication review; VoiceOver is deferred.

## RC9 validation baseline — 8 October 2026

The preserved RC9 suite passed **216 tests and 1,614 assertions** on each configuration below. RC10 changes documentation and journey display messages; it retains this baseline and uses separate targeted checks for those changes. These results do not certify every PHP/database version or Moodle Marketplace acceptance.

| Moodle | PHP | Database |
|---|---|---|
| 4.5.13 | 8.3.30 | MySQL 8.0.44 |
| 5.0.9, 5.1.6, 5.2.2, 5.3 | 8.3.30 | MySQL 8.4.11 |
| 5.3 | 8.4.17 | MySQL 8.4.11 |
| 5.3 | 8.3.30 | PostgreSQL 17.11 |

The suite covers attendance calculations, permissions, privacy, backup/restore, grades, completion, calendar integration, immutable history, retakes and uninstall cleanup. Preserve the evidence and record the exact archive and environment used for every later run.

## Isolated development environment

Use a development copy of the Moodle branch to test, with its Composer development dependencies installed. Reserve a separate database or table prefix and a separate data directory for PHPUnit. Never point PHPUnit at the site's ordinary data. Configure PHP according to that Moodle branch's requirements, including `max_input_vars=5000` or higher.

Example development configuration:

```php
$CFG->phpunit_dbname = 'attendancejourneys_phpunit';
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/separate/path/attendancejourneys-phpunitdata';
```

Put the intended PHP executable first in PATH. From the Moodle repository root, initialise with the command for that branch:

```bash
# Moodle 4.5 and 5.0.
php admin/tool/phpunit/cli/init.php

# Moodle 5.1 and later.
php public/admin/tool/phpunit/cli/init.php
```

Then run only this plugin's suite:

```bash
php -d max_input_vars=5000 vendor/bin/phpunit --testsuite mod_attendancejourneys_testsuite
```

Initialisation resets the reserved PHPUnit environment. Do not run PHPDoc Checker concurrently with PHPUnit against the same Moodle checkout: the checker's temporary plugin can interfere with component discovery.

## Checks beyond PHPUnit

Use Moodle Plugin CI for PHP lint, savepoints, plugin validation, Moodle coding style and PHPDoc; use Moodle Grunt for AMD builds, ESLint and CSS checks. The package has no Mustache templates to check. Keep French and Spanish source translations outside the installable plugin for AMOS, and verify key/placeholder parity with English.

Also verify native installation and upgrade, backup/restore, teacher/student browser workflows, downloads, keyboard use and responsive layout. RC9 evidence includes completed Excel/PDF downloads and representative Chrome workflows at 320 CSS pixels; it remains separate from RC10 documentation and label checks. The guide screenshots illustrate fictional data and do not certify every screen, theme or browser. VoiceOver remains deferred. Repeat the appropriate checks when behaviour or supported environments change. Nothing in these local checks grants Marketplace admission or publishes AMOS translations.
