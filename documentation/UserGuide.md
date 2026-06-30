# Limas User Guide

This guide explains the day-to-day concepts in Limas: how parts, projects, stock, manufacturers, footprints, and meta-parts fit together, and how to use the features that PartKeepr never properly documented.

If you're looking for installation/upgrade instructions, see [Installation.md](Installation.md) and [Upgrade.md](Upgrade.md). For distributor data import, see [InfoProviderAggregator.md](InfoProviderAggregator.md).

---

## 1. Core concepts

Limas is an inventory database for electronic components. Five things form the spine:

| Concept | What it is |
| --- | --- |
| **Part** | A specific stocked item (typically one per MPN). Has stock, parameters, attachments, manufacturers, distributors. |
| **Category** | A node in the part category tree (e.g. *Passive → Resistor → SMD*). Every Part lives in exactly one category. |
| **Storage Location** | A physical place where parts live (a box, drawer, shelf). Also a tree. |
| **Manufacturer** | Who makes the part (e.g. *STMicroelectronics*). |
| **Distributor** | Who sells the part to you (e.g. *Mouser*, *TME*, *LCSC*). |

Around the spine sit several related concepts: **Footprint**, **Parameters**, **Meta-Part**, **Project**, **Project Report**, **Attachments**.

The rest of this guide walks through each.

---

## 2. Parts

A Part is the central record. It typically represents one manufacturer part number you have in stock or want to track.

### What lives on a Part

- **Identity**: name, description, internal part number, comment.
- **Classification**: category (one), footprint (zero or one), unit of measure (pieces / metres / grams…).
- **Location**: storage location, minimum stock level.
- **Sourcing**: manufacturers (the same MPN can have multiple production sources) and distributors (where you can buy it, with SKU and order URL).
- **Parameters**: a list of named values (resistance, voltage, tolerance…). They can be string-only, numeric, or numeric ranges (min/max).
- **Attachments**: arbitrary files (datasheets, photos, 3D models). See [§9 Attachments](#9-attachments).
- **Stock**: current quantity, plus a full history of every add/remove. See [§5 Stock](#5-stock).
- **Condition**: optional free-text field (e.g. "new", "salvage", "rejected — bent leads").
- **Needs review** flag: set when imported data conflicts with existing info, so you can spot it later.

### Creating a Part

The **Parts Manager** is the application's home view — it opens automatically on login and stays open (it cannot be closed). It's a three-pane layout: category tree on the left, parts grid in the middle, part detail panel on the right.

To create a Part:

1. In the category tree, select the category you want the Part in. If you need a new category first, use the **Add Category** button in the tree's toolbar. The Edit / Delete category buttons sit next to it.
2. In the parts grid toolbar (top of the middle pane), click **Add Part**. The Part editor opens.

Two common shortcuts speed step 2 up:

1. **Bulk CSV import** (System → Bulk Import) — bulk-create parts from a spreadsheet. Maps columns to fields; matches existing parts by MPN to avoid duplicates. See the bulk-import dialog's inline help for the column format.
2. **Aggregator** (Info Provider tab in the part editor) — type an MPN to search all enabled distributors at once, or paste a Mouser / Farnell / LCSC / TME / Newark product URL to resolve directly to that part. The merged result is applied to the Part via checkboxes (description, datasheet, picture, manufacturer, footprint, parameters, distributors, prices). See [InfoProviderAggregator.md](InfoProviderAggregator.md).

> Note on the UI: Limas does not bind any right-click context menus. Every action lives in a visible toolbar button or main-menu entry.

---

## 3. Categories, Footprints, Storage Locations

All three are **trees**, and they all behave the same way:

- Each tree has its own toolbar at the top: Expand / Collapse / Reload on the left, Add / Edit / Delete (of the selected node) on the right. There are no right-click menus.
- Drag-and-drop on a node moves it (and all its children) to a new parent.
- Deleting a non-empty node is blocked until its children are moved or deleted.

### Part categories

Where parts are filed. The tree drives the parts grid's left panel — selecting a node filters the grid to that subtree.

Each category can also carry a **Default Parameters** list — names + optional unit + value type. When you create a new Part in that category, the editor pre-populates empty parameter rows for each template so you only fill in the values. Templates are inherited down the tree (a *Resistor* category's defaults apply automatically to *Resistor → SMD* as well; child overrides parent if both define a template with the same name). Edit a category and switch to the *Default Parameters* tab to manage them.

### Footprints

Physical packaging (0603, SOIC-8, TO-220, LQFP48…). Lives in its own tree.

A footprint can carry an image (shown in the parts grid preview), attachments (datasheets, mechanical drawings), and aliases (see [§7 Aliases](#7-aliases)).

### Storage Locations

Where physical parts live. The tree usually mirrors your physical shelving (Lab → Cabinet 3 → Drawer B → Bin 4).

Each Part has one storage location. If you split the same MPN across two physical places, that's two Part records — Limas inherits this pattern from PartKeepr.

---

## 4. Manufacturers and Distributors

Two different things, often confused:

- **Manufacturer** makes the part. Texas Instruments. Vishay. STMicroelectronics.
- **Distributor** sells the part to you. Mouser, Digi-Key, Farnell, TME, LCSC.

A Part records both — manufacturer rows hold the manufacturer's part number; distributor rows hold the distributor's SKU, price, package quantity, and order URL.

### Manufacturer aliases

The same manufacturer is often returned under different names by different distributors:

- "ON Semiconductor" vs "ONSEMI" vs "ON Semi"
- "STMicroelectronics" vs "ST Microelectronics" vs "STM"

Manufacturer aliases map a vendor name to a canonical manufacturer. The aggregator uses these during import so that two candidates from different distributors get merged into one row (not split because their manufacturer strings differ).

> Manufacturer aliases currently have no admin UI — they are auto-created during import. A manager screen is on the v1.x backlog. Parameter and Footprint aliases do have UIs (Edit → Parameter Aliases / Footprint Aliases) — see [§7 Aliases](#7-aliases).

---

## 5. Stock

Stock is **journaled**, not just a counter. Every add or remove records a timestamp, the user, the amount, an optional price (for additions), and an optional comment. The current stock level is always the sum of the journal — you can audit every change.

### Adding / removing stock

Select a Part in the grid. In the **detail panel** on the right, the **Add Stock** and **Remove Stock** buttons open a dialog: amount + optional price + optional comment.

The Stock column in the grid is also **inline editable** — click the cell, type the new total, and a confirm window opens. (The confirm can be suppressed via User Preferences → "Skip online stock edit confirm".)

Minimum stock level is configured per Part — open the Part for editing → main tab.

### Stock history

**View → Stock History** shows the full journal across all parts — date, user, part, amount, price, comment — sorted with newest first.

### Average price

The Part editor's stock tab shows average unit price across positive stock entries — useful for project cost estimates.

---

## 6. Meta-Parts

A **Meta-Part** is a virtual part that matches other parts based on parameter criteria. It does not have stock of its own — it's a saved query.

Use case: in a BOM you don't always care *which* exact MPN gets used. *"Any 100 nF X7R 0603 capacitor with ≥10 V rating"* is a meta-part. At build time, the meta-part resolves to a list of concrete Parts that satisfy its criteria, and you pick one.

### Creating a Meta-Part

In the parts grid toolbar, click **Add Meta-Part** (instead of Add Part). The Meta-Part editor opens. Fill in name and category, then add **Parameter Criteria** rows: parameter name, operator (`=`, `!=`, `<`, `<=`, `>`, `>=`), value type (numeric or string), and the target value (with unit + SI prefix if numeric).

Save. A real Part matches the meta-part if **all** criteria are satisfied.

Meta-Parts show up in the regular parts grid with a brick icon. The match resolution UI lives in the Project Report flow (see [§8](#8-projects-and-project-reports)) — when a Project Report encounters a Meta-Part on the BOM, you navigate matches with the Next/Previous Meta-Part buttons and pick a concrete part to consume from.

### Limitations

- Meta-parts have no stock of their own. They can appear on a Project BOM, but a Project Report must resolve them to a concrete Part before consuming stock.
- A meta-part criterion compares against parameters that have *exactly* that canonical name. The aggregator's parameter alias pipeline ([§7](#7-aliases)) is what makes this practical — without it you'd need every part to use the exact same parameter name spelling.

---

## 7. Aliases

Three independent alias systems, all following the same pattern:

| Alias kind | Maps | Why it matters |
| --- | --- | --- |
| Manufacturer alias | vendor manufacturer name → canonical Manufacturer | Aggregator can merge candidates with differing manufacturer spellings into one row. |
| Footprint alias | vendor package name → canonical Footprint | Aggregator can map "LQFP-48" / "LQFP48" / "QFP48-7x7" onto your single canonical Footprint. |
| Parameter alias | vendor parameter name → canonical parameter name | Lets meta-parts and per-Part parameters match across distributors that use different names for the same thing. |

### The alias lifecycle

Every alias has three useful attributes:

- **Source** — `seed` (shipped with Limas), `user` (you created it), or `auto` (the aggregator created it when it first saw an unknown name).
- **Verified** — yes/no. `seed` and `user` aliases are verified by definition; `auto` aliases start unverified, waiting for a human to confirm.
- **Usage count** — incremented every time the alias resolves a lookup. Useful for spotting hot/cold aliases.

### Auto-create flow

When the aggregator sees a manufacturer, footprint, or parameter name it hasn't seen before, it doesn't fail — it creates an unverified alias record (with no target yet) and lets the import continue. The Part gets the *raw* value for now. Later, an admin opens the alias grid, assigns the canonical target, and ticks the verified box. Once verified, the same vendor name resolves automatically on every future import.

### Where to manage aliases

- Edit → Footprint Aliases
- Edit → Parameter Aliases

Each grid defaults to **unverified first**, sorted by usage count descending — so the highest-impact pending aliases bubble to the top.

Manufacturer aliases don't have a UI yet (v1.x backlog). For now they are auto-created during aggregator imports but can only be reviewed via the API.

---

## 8. Projects and Project Reports

A **Project** is a BOM (bill of materials). A **Project Report** is one build event — the workflow that consumes stock.

### Project

Edit → Projects → **Add**. Give it a name and description. On the **BOM** tab, add parts: each row is a Part (or a Meta-Part) plus quantity, optional remarks, optional lead time.

### Project Report — the run flow

Stock-consuming builds are created via **View → Project Reports**, not via the Project editor:

1. Left pane: **Choose Projects to create a report for**. Tick the project(s), set the **Qty** column (the multiplier — how many builds you're producing).
2. Click **Create Report**.
3. The result grid shows every part on the combined BOM with required quantity, available stock, distributor, item price, sum, and order amount.
4. For every meta-part on the BOM, use the **Next Meta-Part / Previous Meta-Part** buttons to navigate and resolve each one to a concrete Part.
5. **Auto-Fill Distributors** picks a distributor per part for ordering.
6. **Save Project Report** persists the report so you can reopen it later (right pane: **Previous Project Reports**).
7. **Remove parts from stock** commits the run — the consumed quantities are deducted from each Part's stock and recorded in stock history.

### Project Runs viewer

**View → Project Runs** shows past runs. The grid is viewer-only — you can drill into a run to see which Parts (and lot numbers) were consumed, but runs are created from the Project Report flow above, not here.

---

## 9. Attachments

Every Part, Footprint, and Project can carry attachments — datasheets, photos, mechanical drawings, anything.

Limas deduplicates attachments automatically: if you attach the same datasheet PDF to 50 parts, it lives once on disk. Likewise, re-attaching a file the aggregator already fetched is free. Deleting an attachment only frees the underlying file when no other Part still references it.

### Uploading

In the attachments tab, click **Add** to open the File Upload dialog. You have two options:

- **Select File…** — pick a local file to upload.
- **URL** field — paste a vendor URL (datasheet, image). Limas downloads it server-side. The downloader rejects internal/private network addresses, enforces a file-type allowlist, and sanitises SVGs to strip script content.

Other toolbar actions in the attachments tab: View (preview), Open URL (open the original source URL externally), Take Image (webcam capture), Delete.

---

## 10. Parameters

Each Part has a list of parameters. A parameter can be:

- **String-only** — e.g. *Status = Active*.
- **Numeric point** — e.g. *Resistance = 100 kΩ* (value + unit + optional SI prefix).
- **Numeric range** — e.g. *Operating Temperature = -40…85 °C* (min + max + unit).

Units and SI prefixes are managed under System Preferences (Edit → Units, Edit → SI Prefixes). Add what you need; the standard set is seeded on install.

### Parameter aliases

See [§7 Aliases](#7-aliases). Aliases let the aggregator merge "Resistance Value" (one distributor) and "Resistance" (another) into one row.

---

## 11. Users and login

Users are created from the command line during setup (the installation guide shows how).

There is currently no per-user permission system — anyone who can log in is a full editor. Per-user roles and permissions are tracked for a future release.

---

## 12. The aggregator (Info Provider) — at a glance

Detailed docs: [InfoProviderAggregator.md](InfoProviderAggregator.md). Quick mental model:

1. You search by MPN in the Info Provider tab of a Part editor.
2. Each enabled distributor (Digi-Key, Farnell, TME, LCSC, OEMSecrets, Newark, …) is queried in parallel.
3. The results are merged per-field by the configured strategy (e.g. "datasheet from the first source that has one", "price from all sources"). Unknown manufacturer / footprint / parameter names get auto-created as unverified aliases.
4. You pick the candidate row you want, tick which optional fields to apply (Parameters, Distributors, Best Datasheet, Image, Footprint), and hit **Apply Data**. Name, description, and manufacturer are always applied from the picked candidate.

The merge strategy and per-distributor priority are configurable per-search via the gear menu (persisted in your browser) and globally by your sysadmin.

---

## 13. Where to look next

- **Day-to-day**: the Parts Manager (always open) is where 90% of work happens.
- **Bulk operations**: System → Bulk Import for spreadsheet-driven creation; the aggregator for MPN-by-MPN enrichment.
- **Reports**: View → Project Reports, View → Statistics → Summary, View → Statistics → Chart, View → Stock History.
- **Maintenance**: Edit → Batch Jobs runs scheduled background operations (currently used for bulk re-import of distributor data).

If you want to dig into the internals (entities, services, the InfoProvider plugin interface), the source is the authoritative reference.
