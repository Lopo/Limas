Upgrade notes
=============

Reverse-chronological list of changes that need operator attention on
upgrade — schema migrations that drop data, configuration that moved,
infra components that have to be running, etc. Always read entries
newer than your current install before running `doctrine:migrations:migrate`.

Recurring jobs → Symfony Scheduler
----------------------------------

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


Bundled backup task
-------------------

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
- `directory` — where archives land. Point at an SMB/NFS mount for
  off-host storage. Default `%kernel.project_dir%/var/backups`.
- `retention_count` — keep N newest, prune the rest. Default 7.
- `mysqldump_path` — absolute path to mysqldump if it's not on PATH.

Out-of-scope on purpose: remote upload (S3/SFTP). Use a mount instead.
