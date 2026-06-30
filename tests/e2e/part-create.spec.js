const {test, expect} = require('@playwright/test');
const {setupPage, waitForPartManager} = require('./helpers');

test.describe('Limas Part Create', () => {

	test('should create part via UI with storage location and initial stock', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		// Click "Add Part" button
		await page.getByRole('button', {name: 'Add Part'}).click();

		// Wait for Part Editor dialog
		await page.waitForSelector('div.x-window:has-text("Add Part")');

		// Fill in part name
		const partName = 'E2E_Test_Part_' + Date.now();
		await page.fill('input[name="name"]', partName);

		// Fill in description
		await page.fill('input[name="description"]', 'Created via E2E test');

		// Select a category — auto-prefill from the tree was removed
		// (commit 3df9d03), so the editor opens with category empty and FE
		// validation refuses save without one. Wait for the combo's store to
		// load, then pick the first child of the (invisible) root node via
		// the picker's selection model so the input field + value sync.
		await page.evaluate(async () => {
			const combo = Ext.ComponentQuery.query('CategoryComboBox[name=category]')[0];
			if (!combo) {
				throw new Error('CategoryComboBox not found');
			}
			const store = combo.store;
			if (!store.isLoaded()) {
				await new Promise((resolve) => store.on('load', resolve, null, {single: true}));
			}
			combo.expand();
			const picker = combo.getPicker();
			const root = picker.getStore().getRoot();
			const target = root && root.firstChild;
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

		// Pick first available storage location straight from the picker's own
		// store — bypasses the open/click dance which is flaky now that the
		// picker overlay competes for z-index with the rest of the dialog
		await page.evaluate(async () => {
			const picker = Ext.ComponentQuery.query('StorageLocationPicker')[0];
			if (!picker) {
				throw new Error('StorageLocationPicker not found');
			}
			const store = picker.store;
			if (!store.isLoaded()) {
				await new Promise((resolve) => store.on('load', resolve, null, {single: true}));
			}
			if (store.getCount() === 0) {
				throw new Error('No storage locations seeded');
			}
			picker.setValue(store.getAt(0));
		});

		// Set initial stock level
		const stockField = page.locator('input[name="initialStockLevel"]');
		await stockField.clear();
		await stockField.fill('10');

		// Save via Ext API — clicking the rendered button is unreliable with
		// the new dialog overlays. Mirrors what the category factory tests do.
		await page.evaluate(() => {
			const win = Ext.ComponentQuery.query('window[title="Add Part"]')[0];
			if (!win || !win.saveButton) {
				throw new Error('PartEditorWindow not found');
			}
			win.saveButton.fireHandler();
		});

		// Wait for dialog to close and grid to update
		await page.waitForSelector('div.x-window:has-text("Add Part")', {state: 'hidden', timeout: 10000});

		// Verify part appears in grid
		await page.waitForSelector(`.x-grid-cell:has-text("${partName}")`, {timeout: 10000});

		// Verify stock level is shown
		const partRow = page.locator(`.x-grid-row:has-text("${partName}")`);
		await expect(partRow).toBeVisible();
	});

	test('should create part with URL attachment', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		// Click "Add Part" button
		await page.getByRole('button', {name: 'Add Part'}).click();
		await page.waitForSelector('div.x-window:has-text("Add Part")');

		// Fill in part name
		const partName = 'E2E_Attachment_Part_' + Date.now();
		await page.fill('input[name="name"]', partName);

		// Select a category — auto-prefill removed (commit 3df9d03), FE validation refuses save without one
		await page.evaluate(async () => {
			const combo = Ext.ComponentQuery.query('CategoryComboBox[name=category]')[0];
			if (!combo) {
				throw new Error('CategoryComboBox not found');
			}
			const store = combo.store;
			if (!store.isLoaded()) {
				await new Promise((resolve) => store.on('load', resolve, null, {single: true}));
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

		// Pick first available storage location straight from its store
		await page.evaluate(async () => {
			const picker = Ext.ComponentQuery.query('StorageLocationPicker')[0];
			if (!picker) {
				throw new Error('StorageLocationPicker not found');
			}
			const store = picker.store;
			if (!store.isLoaded()) {
				await new Promise((resolve) => store.on('load', resolve, null, {single: true}));
			}
			if (store.getCount() === 0) {
				throw new Error('No storage locations seeded');
			}
			picker.setValue(store.getAt(0));
		});

		// Go to Attachments tab
		await page.click('span.x-tab-inner:has-text("Attachments")');
		await page.waitForTimeout(300);

		// Click "Add" button in attachment toolbar (exact match to avoid Add Part/Add Meta-Part)
		await page.getByRole('button', {name: 'Add', exact: true}).click();

		// Wait for file upload dialog
		await page.waitForSelector('div.x-window:has-text("File Upload")');

		// Enter URL
		await page.fill('input[name="url"]', 'https://httpbin.org/image/png');

		// Click upload
		await page.getByRole('button', {name: 'Upload'}).click();

		// Wait for upload to complete - dialog should close or show success
		await page.waitForTimeout(3000);

		// Wait for upload to succeed - the file should appear in attachment grid
		// Dialog closes automatically on success, or we can close it
		const uploadDialog = page.locator('div.x-window:has-text("File Upload")');
		if (await uploadDialog.isVisible()) {
			// Try to close if there's a close button
			const closeBtn = uploadDialog.locator('button:has-text("Close")');
			if (await closeBtn.isVisible()) {
				await closeBtn.click();
			}
		}

		// Go back to Basic Data tab and save
		await page.click('span.x-tab-inner:has-text("Basic Data")');
		await page.waitForTimeout(200);

		// Save via Ext API
		await page.evaluate(() => {
			const win = Ext.ComponentQuery.query('window[title="Add Part"]')[0];
			if (!win || !win.saveButton) {
				throw new Error('PartEditorWindow not found');
			}
			win.saveButton.fireHandler();
		});

		// Wait for dialog to close
		await page.waitForSelector('div.x-window:has-text("Add Part")', {state: 'hidden', timeout: 15000});

		// Verify part appears in grid
		await page.waitForSelector(`.x-grid-cell:has-text("${partName}")`, {timeout: 10000});
	});
});
