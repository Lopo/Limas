> **Upgrading an existing install?** See [Upgrade.md](Upgrade.md) for
> migration notes — schema changes that drop data, infra components
> that have to start running, etc.

Dependencies
---

* PHP 8.4 and higher
  * php-curl
  * php-ldap
  * php-bcmath
  * php-gd
  * php-dom
  * php-ctype
  * php-iconv
  * php-redis
* [Composer](https://getcomposer.org/download/)
* MySQL 8.4 server
* Nginx or Apache
* Redis

Fresh install
---

1. Copy or clone this repository into a folder on your server
2. Configure your webserver to serve from the `public/` folder. See [here](https://symfony.com/doc/6.1/setup/web_server_configuration.html) for additional information.
3. Copy the global config file `cp .env .env.local` and edit `.env.local`:
   * Change the line `APP_ENV=dev` to `APP_ENV=prod`
4. Install composer dependencies and generate autoload files: `SYMFONY_ENV=prod composer install --no-dev -o`
5. Install client side dependencies and build it: `yarn install` and `yarn build`
6. Create database: `php bin/console doctrine:migrations:migrate`
7. Init database: `php bin/console doctrine:fixtures:load --group=setup --append`
8. Create superadmin account: `php bin/console limas:user:create --role super_admin admin admin@example.com admin`
   * _Optional_ protect it: `php bin/console limas:user:protect admin`
9. Generate models.js asset: `php bin/console limas:extjs:models`
10. Compile assets: `php bin/console asset-map:compile`
11. _Optional_ generate JWT keypair: `php bin/console lexik:jwt:generate-keypair`
12. _Optional_ (speeds up first load): Warmup cache: `php bin/console cache:warmup`
13. Start the background worker (handles async bulk imports + recurring jobs:
    version check, statistics snapshot, tips refresh): `php bin/console messenger:consume scheduler_default async`.
    Run under systemd / supervisord in production so it survives restarts;
    the worker emits a heartbeat the UI uses to gate features that depend on it.


InfoProvider Aggregator setup
---

The [InfoProvider Aggregator](InfoProviderAggregator.md) is automatic — adapters with their env vars set in `.env.local` activate; the rest stay hidden. To enable it:

1. Copy distributor credential examples: `cat .env.example.distributors >> .env.local`, then edit `.env.local` and fill in the keys for whichever providers you signed up for (DigiKey / Farnell-element14 / TME / OEMSecrets …). LCSC needs no key — just set `LCSC_ENABLED=1` to opt in.
2. Load the Octopart-seeded `ParameterAlias` taxonomy (757 canonical attribute names + per-vendor mappings): `php bin/console doctrine:fixtures:load --group=parameter-taxonomy --append`
3. Reload Limas in the browser. The **Aggregator…** button appears in the Part editor toolbar when at least one source is configured.


Migrating from PartKeepr
---

If you have an existing PartKeepr installation, use the dedicated import
command after the fresh-install steps above (skip step 7 — the importer
populates everything).

```
php bin/console limas:import:partkeepr \
    --pkdsn=mysql://user:pass@localhost:3306/partkeepr \
    --pkroot=/path/to/partkeepr/install \
    --prepare-aggregator
```

* `--pkroot` is the PartKeepr install directory (the importer reads
  attachment files from `<pkroot>/data/`).
* `--lowercase` toggles between PartKeepr's PascalCase and lowercase
  table names — set when your PartKeepr DB was created on a
  case-insensitive collation.
* `--prepare-aggregator` (recommended) additionally seeds Manufacturer
  self-aliases, loads the Octopart `ParameterAlias` taxonomy, backfills
  canonical names on imported `PartParameter` rows, and lifts
  string-only parameter values into the `value` / `minValue` /
  `maxValue` numeric columns where possible. Skip the flag if you only
  want a plain data copy without aggregator preparation; you can still
  load the taxonomy later via the fixtures command from the section
  above.

Attachment SHA-256 hashes are computed inline during the import — no
follow-up `limas:attachments:hash --backfill` is needed.
