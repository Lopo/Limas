Upgrade notes
=============

Operator-attention changes per release, newest first — schema migrations
that drop data, configuration that moved, infra that has to be running,
new hard requirements. Read every version newer than your current install
before running `doctrine:migrations:migrate`.

## Every upgrade

`public/js/models.js` (the ExtJS entity models the prod frontend loads) is a
generated, un-versioned build artifact. It is now regenerated automatically by
`composer install` / `composer update` (via the `limas:extjs:models`
auto-script), so a normal deploy keeps it in sync. If you ever build the
frontend without a fresh `composer install` — or the models look stale in the
browser (`Uncaught Error: No such Entity "Limas.Entity.<X>"` when opening a
dialog) — regenerate it by hand:

```
php bin/console limas:extjs:models
```

**Restart the background worker after every deploy.** The
`messenger:consume scheduler_default async` worker is a long-running process
that loads the code, service container and schedule once at start and keeps
them in memory, so new code, config, cron changes and message handlers only
take effect after a restart — otherwise it silently keeps running the old
version (a stale schedule won't pick up newly added jobs; fixed handlers keep
running the buggy code). Signal a graceful stop and let your process manager
(systemd / supervisor) bring it back up:

```
php bin/console messenger:stop-workers
```

## v1.2.7 → v1.2.8

**Security release** (GHSA-8883-2whw-gwhm). No schema change; run
`cache:clear` (the API metadata changed) and `yarn build` as usual.

The stock history API (`/api/stock_entries`) now enforces on the server the
rules the UI always had: only an admin may change an entry's amount, user or
part, or delete an entry; the entry's author may still edit their own price
and comment. Creating an entry directly (`POST /api/stock_entries`) is
admin-only — stock movements go through the part's `addStock` /
`removeStock` / `setStock` actions, which record the acting user. When a
regular user creates a part with an initial stock, that stock is booked under
their own name. Users embedded in other resources (stock entries, projects,
import jobs) no longer include the `email` field; it is only returned by the
user endpoints themselves. If you have scripts that write stock entries
directly as a non-admin account, or read e-mails from embedded users, adapt
them.

The same release closes further authorization holes found in a follow-up
audit. What changes for API clients and regular (non-admin) users:

- **Admin-only now:** creating, editing and deleting user providers; creating
  system notices and acknowledging them (the *Acknowledge* button is disabled
  for regular users).
- **Users:** reference users by IRI only; nested user objects in other
  resources are rejected. When writing a user, send its `provider` as an
  IRI. `roles` are read-only over the API; use `limas:user:admin` to grant
  or revoke admin. The login response returns only the user's public fields.
- **Batch jobs** can only target inventory entities (parts, categories,
  storage locations, footprints, manufacturers, distributors, projects and
  their child records), and may only update fields that are writable through
  the API. Existing batch jobs with another base entity fail with a 400.
- **Filters** (`?filter=` / `?order=`) reject password, role, e-mail and
  last-seen fields of users; an invalid filter returns 400 instead of 500.
- **Parts:** creating a part accepts only new stock entries; existing entries
  in `stockLevels` are rejected (400).
- **System information** hides server paths, OS release, load and cache
  details from non-admins.
- Tip-of-the-day history and bulk-import jobs are visible only to their owner
  (and admins, for bulk imports).

Fixed along the way: editing an existing manufacturer (or any entity with a
unique name) failed with "name already used" since v1.2.5 — `PUT` now updates
the existing record again (`standard_put: false` is the API default). Editing a
user as an admin without typing a new password no longer wipes that user's
password.

## v1.2.6 → v1.2.7

No schema change, no new requirements. Run `yarn build` and `cache:clear`, and
restart the worker (see *Every upgrade*) — the backup fix runs inside it.

**Check your backups.** Older versions could not compress an archive larger
than PHP's `memory_limit`: the worker died during the nightly backup, so if
your `limas-*.tar.gz` files are about 20 bytes they are empty. The usable data
is in the matching `limas-*.tar` files. After the first good backup on this
version:

1. Verify it: `gzip -t <newest>.tar.gz`, and check it is a realistic size.
2. Delete the empty `.tar.gz` files, and the `.tar` files you no longer want —
   retention never removes those. Gzip the ones you keep.
3. Delete any leftover `limas-*.staging` directories from the system temp dir.
   They hold a plaintext database dump and a copy of `.env.local`, readable by
   other local users, so consider rotating those secrets on a shared host.

**Backup directory.** Set `LIMAS_BACKUP_DIR` in `.env.local` instead of editing
`config/limas.yaml`. If you changed `backup.directory` there, move the value
over; a copy such as `limas.yaml.local` is not loaded at all, so check that
your backups really land where you expect.

## v1.2.1 → v1.2.2

No schema change, no new requirements — a plain code upgrade. Two notes:

1. **Rebuild the frontend** (`yarn build`). This release changes JS (Mouser
   source, distributor brand display names, storage-location picker). The
   ExtJS `models.js` is regenerated on its own by `composer install` — see
   *Every upgrade* above.
2. **New optional source: Mouser.** Set `MOUSER_KEY` in `.env.local` to enable it.

## v1.1.x → v1.2.0

Back up first — this release changes schema, tightens secret handling, and
adds required PHP extensions: `php bin/console limas:backup:create` (see the
Bundled backup task section under v1.0.0).

**Operator actions:**

1. **New required PHP extensions.** `composer install` now declares and
   enforces `ext-gd`, `ext-mbstring`, `ext-openssl`, `ext-pdo` and
   `ext-pdo_mysql`. They were always used at runtime; a host missing any now
   fails the install early instead of at first request. Install them before
   deploying.

2. **Strong secrets are now enforced.** In production the kernel **refuses to
   boot** with an empty or too-short (`< 16` chars) `APP_SECRET`, or with
   `JWT_PASSPHRASE` empty or left at the shipped `passphrase` default. If your
   `.env.local` still carries weak/default values, set proper ones — e.g.
   `openssl rand -hex 32` for each. If you change `JWT_PASSPHRASE`, regenerate
   the keypair (`php bin/console lexik:jwt:generate-keypair --overwrite`); this
   invalidates existing tokens, so users log in once more.

3. **Run the schema migration** (`Version20260712074527`). Non-destructive:
   adds a nullable `User.passwordChangedAt` column plus performance indexes
   (`categoryPath` on the three category tables, `Part.internalPartNumber`, and
   `PartParameter (name, normalizedValue)` / `(name, stringValue)`).

4. **Grant ROLE_ADMIN to your admin account.** User management and System
   Preferences are now admin-only, and existing accounts do **not** get the
   role automatically: `php bin/console limas:user:admin <username>`. Skip this
   and that user loses access to those screens.

**Token-lifecycle note**: `passwordChangedAt` starts `NULL` for existing users,
so the upgrade does **not** invalidate anyone's session. A token is only
rejected once its owner changes their password after the upgrade — instant
access-token revocation from that point on. The refresh-token TTL also dropped
from 30 days to 7, with single-use rotation.

## v1.1.0

No operator action required — no schema drops, no configuration moves, no new
infra. Plain code upgrade + `doctrine:migrations:migrate`.

## v1.0.0 (first release / coming from the legacy cron pipeline)

### Recurring jobs → Symfony Scheduler

The legacy system-cron pipeline (`limas:cron:run` invoking individual
`limas:cron:*` commands) has been retired. Recurring work — version
check, statistics snapshot, tip-catalog sync — is now driven by Symfony
Scheduler through Messenger.

**Action required**:

1. Replace your crontab entry for `limas:cron:run` with a long-running
   worker. Under systemd, supervisord or a similar service manager:

   ```
   php bin/console messenger:consume scheduler_default async
   ```

   The same worker handles ad-hoc Bulk Import jobs too. If it isn't
   running the UI grays out features that depend on it (Bulk Import
   menu entry; Statistics chart shows an explanation banner).

2. Run the schema migration (`Version20260601160926`). It drops the
   `CronLogger` table.

**Data implications**: `CronLogger` only stored "when did this cronjob
last successfully run", one row per name. It was input for the legacy
orchestrator's "should I fire this command?" check. The data has no
landing place in the Scheduler world — Symfony Scheduler derives the
next-run time from the cron expression alone, and the `messenger_messages`
table has its own `delivered_at` for in-flight tracking. The drop is
intentional and there is no migration path; if you want a paper trail
before upgrading, dump the table first:

```
mysqldump <db> CronLogger > cronlogger-backup.sql
```

**Configuration**: cron expressions live in `config/limas.yaml` under
`limas.scheduler.*_cron`. Defaults run early-morning local time; edit
to match your maintenance window. After editing run
`bin/console cache:clear` and restart the worker.

### Bundled backup task

A scheduled backup task now ships out of the box. The archive shape
mirrors the project root so restore is just "untar into the project
directory":

- `db.sql` — `mysqldump` of the active database
- `data/` — CAS blob storage + parameter taxonomy + datasheet patterns
- `config/` — entire config tree (packages, services.yaml, limas.yaml,
  distributors.yaml, jwt/*.pem, bundles.php, routes/, …)
- `.env.local` — per-deployment env overrides

Written to `var/backups/limas-YYYYMMDD-HHMMSS.tar.gz`. The scheduler
runs it daily at 02:00 by default; the worker
(`messenger:consume scheduler_default async`) handles dispatch + retention
in the same process.

**Manual trigger** — right before an upgrade or migration, run:

```
php bin/console limas:backup:create
```

**Restore** — manual:

```
tar -xzf limas-YYYYMMDD-HHMMSS.tar.gz -C /path/to/limas/
mysql <database> < /path/to/limas/db.sql
```

There is no automated restore command on purpose; production restores
benefit from a human checking the tarball first.

**Config**: `config/limas.yaml → limas.backup`:

- `enabled` — set to `false` to disable the scheduled task (the CLI
  command still works).
- `directory` — where archives land. Default
  `%kernel.project_dir%/var/backups`. To point it at an SMB/NFS mount for
  off-host storage, set `LIMAS_BACKUP_DIR=/path/to/mount` in `.env.local`
  rather than editing `limas.yaml` (a copied `limas.yaml.local` is NOT
  loaded by Symfony — only `.env.local` is).
- `retention_count` — keep N newest, prune the rest. Default 7.
- `mysqldump_path` — absolute path to mysqldump if it's not on PATH.

Out-of-scope on purpose: remote upload (S3/SFTP). Use a mount instead.
