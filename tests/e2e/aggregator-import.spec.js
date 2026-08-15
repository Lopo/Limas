const {test, expect} = require('@playwright/test');
const {setupPage, waitForPartManager} = require('./helpers');

/**
 * Full aggregator import vertical: a synthetic candidate is applied onto a
 * real (new) Part editor record through the ACTUAL apply pipeline
 * (SearchPanel.runApply → ensureRequirements auto-creates Manufacturer +
 * Distributor server-side → doApply fills manufacturers / distributors /
 * parameters → applyAttachments uploads the datasheet), then the editor is
 * saved so everything persists, and the persisted Part is re-fetched from the
 * REST API to prove the relations survived the round-trip.
 *
 * Deterministic + offline: no live distributor search (we inject the candidate
 * straight into the panel store, exactly where the search transform would hand
 * it off) and the datasheet is an inline `data:` URI that the upload sink
 * decodes in-process — no external host, no creds.
 *
 * This is the "several async steps, careful waits" flow flagged in TODO: the
 * two server-side entity creations and the attachment upload are all async and
 * complete before the panel fires `applied`. It also caught a real bug —
 * doApply called `Limas.getDefaultPartUnit()` (undefined) instead of
 * `Limas.getApplication().getDefaultPartUnit()`, which crashed the whole apply
 * whenever the target Part had no partUnit yet.
 */

// Same known-good inline asset the attachment test uses — 1x1 PNG. Stands in
// for the datasheet so the upload path runs fully offline.
const DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWPgEpEDAABoAD0BFY5BAAAAAElFTkSuQmCC';

test.describe('Aggregator import flow', () => {

	test('applies a synthetic candidate onto a new Part and persists every relation', async ({page}) => {
		test.setTimeout(90000);
		await setupPage(page);
		await waitForPartManager(page);

		await page.getByRole('button', {name: 'Add Part'}).click();
		await page.waitForSelector('div.x-window:has-text("Add Part")');

		// Unique identifiers so the auto-created Manufacturer/Distributor don't
		// collide with anything seeded and the API assertions are unambiguous.
		const uniq = await page.evaluate(() => {
			const ts = Date.now();
			return {
				MPN: 'E2E-AGG-' + ts,
				MFR: 'E2E Agg Mfr ' + ts,
				DIST: 'E2E Agg Dist ' + ts,
				SKU: 'SKU-' + ts,
				PARAM: 'E2E Purpose ' + ts
			};
		});

		await page.fill('input[name="name"]', uniq.MPN);
		await page.fill('input[name="description"]', 'E2E aggregated part');

		// Category — same proven picker dance as part-create.spec.js (FE validation refuses save without one)
		await page.evaluate(async () => {
			const combo = Ext.ComponentQuery.query('CategoryComboBox[name=category]')[0];
			if (!combo) {
				throw new Error('CategoryComboBox not found');
			}
			if (!combo.store.isLoaded()) {
				await new Promise((resolve) => combo.store.on('load', resolve, null, {single: true}));
			}
			combo.expand();
			const picker = combo.getPicker();
			const target = picker.getStore().getRoot() && picker.getStore().getRoot().firstChild;
			if (!target) {
				throw new Error('No category available to pick');
			}
			picker.getSelectionModel().select(target);
			combo.applySelection();
		});
		await page.waitForFunction(() => {
			const combo = Ext.ComponentQuery.query('CategoryComboBox[name=category]')[0];
			return combo && combo.getValue() instanceof Limas.Entity.PartCategory;
		}, {timeout: 5000});

		// Storage location — pick the first seeded one from the picker store
		await page.evaluate(async () => {
			const picker = Ext.ComponentQuery.query('StorageLocationPicker')[0];
			if (!picker) {
				throw new Error('StorageLocationPicker not found');
			}
			if (!picker.store.isLoaded()) {
				await new Promise((resolve) => picker.store.on('load', resolve, null, {single: true}));
			}
			if (picker.store.getCount() === 0) {
				throw new Error('No storage locations seeded');
			}
			picker.setValue(picker.store.getAt(0));
		});

		// Drive the REAL apply pipeline against the editor's record. We build a
		// SearchWindow the same way PartEditorWindow does and point its panel at
		// the open record, but keep the window unshown — we only need the apply
		// methods, and rendering the grid with one synthetic row trips a column
		// renderer. Injecting straight into the store lands the candidate exactly
		// where the live-search transform would have. runApply then exercises
		// ensureRequirements (server-side Manufacturer + Distributor create),
		// doApply (manufacturers/distributors/parameters) and applyAttachments
		// (data: URI upload); the `applied` event fires only once all finish.
		const applied = await page.evaluate(async (data) => {
			const win = Ext.ComponentQuery.query('window[title="Add Part"]')[0];
			const record = win.editor.record;

			const aggWin = Ext.create('Limas.Components.InfoProviderAggregator.SearchWindow');
			const sp = aggWin.down('#panel');
			sp.setPart(record);
			sp.applyFlags = {parameters: true, distributors: true, bestDatasheet: true, images: false, footprint: false};

			const P = (name, val, extra) => Object.assign({
				canonicalName: name, rawName: name, rawValue: val,
				numericValue: null, numericMin: null, numericMax: null,
				unit: null, siPrefix: null, qualifier: null, valueText: null
			}, extra || {});

			const candidate = {
				manufacturerPartNumber: {chosenValue: data.MPN},
				manufacturerName: {chosenValue: data.MFR, sourcesValues: {}},
				description: {chosenValue: 'E2E aggregated part'},
				datasheetUrl: {chosenValue: data.DATA_URI, sourcesValues: {}},
				providerSpecific: {
					[data.DIST]: {sourceSku: data.SKU, currency: 'EUR', priceBreaks: [{quantity: 1, price: 1.23}], lifecycleStatus: 'active'}
				},
				parameters: {
					[data.DIST]: [
						P(data.PARAM, 'Purpose X'),
						P('E2E Count', '42', {numericValue: 42})
					]
				},
				contributingSources: [data.DIST]
			};

			const rowData = Limas.Components.InfoProviderAggregator.SearchPanel.prototype.candidateToRow.call(sp, candidate, '');
			const row = sp.store.add(rowData)[0];

			await new Promise((resolve, reject) => {
				const timer = setTimeout(() => reject(new Error('apply did not complete in time')), 30000);
				sp.on('applied', () => {
					clearTimeout(timer);
					resolve();
				}, null, {single: true});
				sp.runApply(row, {});
			});

			const firstMfr = record.manufacturers().getCount() ? record.manufacturers().getAt(0) : null;
			const firstDist = record.distributors().getCount() ? record.distributors().getAt(0) : null;
			const result = {
				manufacturers: record.manufacturers().getCount(),
				distributors: record.distributors().getCount(),
				parameters: record.parameters().getCount(),
				attachments: record.attachments().getCount(),
				mfrName: firstMfr && firstMfr.getManufacturer() ? firstMfr.getManufacturer().get('name') : null,
				distSku: firstDist ? firstDist.get('sku') : null
			};
			aggWin.destroy();
			return result;
		}, {...uniq, DATA_URI});

		// Every layer of the apply landed on the in-memory record
		expect(applied.manufacturers).toBe(1);
		expect(applied.distributors).toBe(1);
		expect(applied.parameters).toBe(2);
		expect(applied.attachments).toBe(1);
		expect(applied.mfrName).toBe(uniq.MFR);
		expect(applied.distSku).toBe(uniq.SKU);

		// Save the editor — the real persistence path POSTs the Part together
		// with the aggregator-applied manufacturers/distributors/parameters/
		// attachment
		await page.evaluate(() => {
			const win = Ext.ComponentQuery.query('window[title="Add Part"]')[0];
			if (!win || !win.saveButton) throw new Error('PartEditorWindow not found');
			// Stash the editor record so we can read its server-assigned @id
			// straight off the save response. Looking the Part up in the grid
			// store instead was flaky: the grid only holds the selected category's
			// current page, and its reload after save is async — the new Part
			// need not be there when we check.
			window.__e2ePartRecord = win.editor.record;
			win.saveButton.fireHandler();
		});
		await page.waitForSelector('div.x-window:has-text("Add Part")', {state: 'hidden', timeout: 15000});

		// The create POST writes the new @id back onto the record; poll for it
		const iri = await page.waitForFunction(() => {
			const rec = window.__e2ePartRecord;
			const id = rec && rec.get('@id');
			return id || null;
		}, null, {timeout: 15000}).then((handle) => handle.jsonValue());

		// Re-fetch the persisted Part from the REST API and prove the relations
		// survived (distributor SKU + parameter name are scalars that must
		// serialize in the detail group)
		const body = await page.evaluate(async (partIri) => {
			const headers = Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders();
			return await new Promise((resolve) => {
				Ext.Ajax.request({
					url: Limas.getBasePath() + partIri,
					method: 'GET',
					headers: headers,
					success: (r) => resolve(r.responseText),
					failure: () => resolve('')
				});
			});
		}, iri);

		expect(iri).toBeTruthy();
		expect(body).toContain(uniq.SKU);
		expect(body).toContain(uniq.PARAM);
	});
});
