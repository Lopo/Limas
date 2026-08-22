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
| Mouser | API key (query param) | V2 plain `/search/keyword` + `/partnumber`. Contributes price/stock/datasheet/image only — **no technical parameters** (Mouser's Search API omits them; other sources fill them on merge) |
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

## Legal / distributor attribution

Some distributor API terms require you to credit them wherever their data
is shown. The aggregator renders a per-source attribution line under each
distributor's block in the results detail panel, driven by
`InfoProviderInterface::getAttribution()`:

- **TME** — `Data powered by TME.eu Data – no guarantee of data accuracy`
  (their §8.7, exact wording).
- **DigiKey** — `Data provided by DigiKey` (§3.1.4 requires a clear,
  conspicuous source credit; no fixed string mandated).
- **Mouser** — `Data provided by Mouser Electronics` (their API Terms of Use
  require you to "clearly and conspicuously attribute the source of all Mouser
  Electronics Data"; like DigiKey, no fixed string is mandated).
- **Farnell / Newark / element14** — `Data provided by … (Premier Farnell)`
  (§5).
- **LCSC** (community jlcsearch + public endpoints) and **OEMSecrets** impose
  no attribution obligation, so they render none.

TME and DigiKey also want product-photo watermarks left intact — Limas'
CAS stores the fetched bytes as-is and never re-encodes, so that holds.

**A note on the element14/DigiKey "no caching / no own-database" and Mouser
"no undistinguished aggregation" clauses.** Read literally, element14 §4
("you will not … store any portion of the Farnell Content"), DigiKey §5.1
("build/update your own database"), and Mouser's ban on aggregating its
content "with third party content (without distinction)" would make any
multi-distributor inventory tool impossible. The intent is clearly to stop
someone bulk-scraping the whole catalogue and re-selling it as a competing
data service — and, for Mouser, blending sources into an anonymous blob. The
aggregator's per-source provenance + attribution (below) is exactly what
Mouser's "without distinction" qualifier asks for: sources stay labelled, not
merged into an unattributed whole. Limas is a **self-hosted, bring-your-own-key** tool that stores only
the handful of parts a user chooses to keep as local records — not a 1:1 dump
or a data product. Each deployment's own API key means the distributor's terms
bind **the deployer**, who is responsible for their own compliance. Keeping the
per-source attribution + provenance visible (rather than anonymously blending
sources) is what the terms actually care about.
