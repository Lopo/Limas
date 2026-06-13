InfoProvider Aggregator — vendor-neutral parts search
---

Octopart used to be the single source for cross-distributor MPN lookups in
PartKeepr. The free tier is gone, so Limas ships a built-in aggregator that
fans the same query out to whichever distributor APIs you have keys for and
merges the answers into one candidate list.

Open any part editor → **Aggregator…** → type an MPN. You get one row per
real-world part (`(canonical manufacturer, MPN)`), with per-field provenance,
conflict markers when sources disagree, an "already in your inventory" flag
on parts you've added before, and a one-click **Apply Data** that fills the
editor — same flow as the old Octopart dialog.


## Configured sources

Each source lights up automatically once its env vars are present in
`.env.local`. See `.env.example.distributors` for the full list with sign-up
links and per-vendor notes.

| Source | Auth | Notes |
|---|---|---|
| DigiKey | OAuth2 client_credentials | V4 Product Information API |
| Farnell / element14 EU | API key (query param) | One key, one storeId per region |
| Newark | shares `ELEMENT14_KEY` | US sibling catalog of Farnell |
| element14 APAC | shares `ELEMENT14_KEY` | Asia-Pacific (`au.element14.com`) |
| TME | OAuth2 token + signed HMAC | v2 endpoints |
| LCSC | no key — community + LCSC unauth | `jlcsearch.tscircuit.com` + `wmsc.lcsc.com` |
| OEMSecrets | API key (query param) | Meta-aggregator over ~33 distributors; filters out the ones we query directly |
| Octopart (Nexar) | OAuth2 | Separate flow — opens its own dialog when configured |

Toggle individual sources on/off via the chip strip above the search bar
(state per browser via `localStorage`). The Aggregator button itself stays
hidden until at least one source is configured.


## How a query gets answered

1. **Phase 1** — parallel keyword fan-out to every enabled source via Symfony
   HttpClient's curl_multi. Total wallclock ≈ max-per-provider, not sum.
2. **Phase 2** — group results by canonical `(manufacturer, MPN)` (driven by
   a seedable `ManufacturerAlias` table), batched detail fetch per source.
   TME chunks 50 SKUs per call to stay under its rate limit.
3. **Merge** — per-field provenance, majority-or-hierarchy strategy
   (configurable via `services.yaml`), soft-normalize for case/whitespace
   so `"Diotec Semiconductor"` and `"DIOTEC SEMICONDUCTOR"` don't register
   as a conflict.
4. **Completion pass** — for the top 10 candidates with fewer sources than
   configured, fire strict exact-MPN lookups at the missing sources. Click
   **Complete more** to lift the cap when needed.
5. **Parameter normalization** — Stage 1 maps each vendor `rawName` to a
   canonical via the Octopart-seeded `ParameterAlias` table (757 attribute
   names + per-vendor mappings). Stage 2 parses each `rawValue` into
   numeric value + unit + SI prefix + range + `(Max)/(Min)/(Typ)` qualifier
   so `Operating Temperature (Max)=70°C` and `Operating Temperature (Min)=
   -40°C` collapse to ONE `PartParameter` row with `minValue=-40,
   maxValue=70, unit=°C`.


## Caching

Everything goes through a Redis cache pool (`aggregator.cache`, 5-min TTL)
keyed by `(provider, mpn, limit)` for search and `(provider, sku)` for
detail, so re-opening the dialog or repeating a query is effectively free.
Pass `?nocache=1` to the REST endpoint to bypass.


## ParameterAlias admin

A manual mapping admin lives under `Limas.ParameterAliasEditorComponent` —
review auto-discovered vendor parameter rawNames, promote to a canonical,
mark verified, bulk-merge variants. Useful when a vendor invents a new
parameter name that doesn't match the Octopart seed.


## Initial setup

The Octopart-seeded `ParameterAlias` taxonomy needs to be loaded once.
Either run the fixture directly:

```
php bin/console doctrine:fixtures:load --group=parameter-taxonomy --append
```

…or, if you're migrating from PartKeepr, use the `--prepare-aggregator`
flag on the import command (see `documentation/Installation.md`), which
loads the taxonomy and also seeds Manufacturer aliases + backfills
parameter canonicals and numeric values from the imported data.
