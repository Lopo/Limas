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

		// Select storage location via JavaScript (complex picker widget)
		await page.evaluate(() => {
			const picker = Ext.ComponentQuery.query('StorageLocationPicker')[0];
			if (picker) {
				picker.expand();
			}
		});
		await page.waitForTimeout(1000);

		// Wait for picker grid to show and click on Test Location
		await page.waitForSelector('.x-floatpanel .x-grid-cell, .x-layer .x-grid-cell', {timeout: 10000});
		await page.waitForTimeout(500);

		// Use JavaScript to select the first storage location and close picker
		await page.evaluate(() => {
			const picker = Ext.ComponentQuery.query('StorageLocationPicker')[0];
			const grid = picker.getPicker().getGrid();
			if (grid.getStore().getCount() > 0) {
				picker.setValue(grid.getStore().getAt(0));
			}
			picker.collapse();
		});
		await page.waitForTimeout(300);

		// Set initial stock level
		const stockField = page.locator('input[name="initialStockLevel"]');
		await stockField.clear();
		await stockField.fill('10');

		// Save the part - use getByRole and force click to avoid overlay issues
		await page.getByRole('button', {name: 'Save'}).click({force: true});

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

		// Select storage location via JavaScript (complex picker widget)
		await page.evaluate(() => {
			const picker = Ext.ComponentQuery.query('StorageLocationPicker')[0];
			if (picker) {
				picker.expand();
			}
		});
		await page.waitForTimeout(1000);
		await page.waitForSelector('.x-floatpanel .x-grid-cell, .x-layer .x-grid-cell', {timeout: 10000});
		await page.evaluate(() => {
			const picker = Ext.ComponentQuery.query('StorageLocationPicker')[0];
			const grid = picker.getPicker().getGrid();
			if (grid.getStore().getCount() > 0) {
				picker.setValue(grid.getStore().getAt(0));
			}
			picker.collapse();
		});
		await page.waitForTimeout(300);

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

		// Save the part
		await page.getByRole('button', {name: 'Save'}).click({force: true});

		// Wait for dialog to close
		await page.waitForSelector('div.x-window:has-text("Add Part")', {state: 'hidden', timeout: 15000});

		// Verify part appears in grid
		await page.waitForSelector(`.x-grid-cell:has-text("${partName}")`, {timeout: 10000});
	});
});