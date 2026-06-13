const {test, expect} = require('@playwright/test');
const {setupPage, waitForPartManager} = require('./helpers');

test.describe('Limas Parts UI', () => {

	test('should show Parts Manager as default view', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		// Part Manager should be visible by default
		const partsInfo = await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			const grid = partManager?.grid;
			const tree = partManager?.tree;
			return {
				partManagerVisible: partManager?.isVisible(),
				gridVisible: grid?.isVisible(),
				treeVisible: tree?.isVisible(),
				gridStoreLoaded: grid?.getStore()?.isLoaded(),
				partsCount: grid?.getStore()?.getCount() || 0
			};
		});

		expect(partsInfo.partManagerVisible).toBe(true);
		expect(partsInfo.gridVisible).toBe(true);
		expect(partsInfo.treeVisible).toBe(true);
	});

	test('should show Add Part button in grid toolbar', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		const buttonInfo = await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			const addButton = partManager?.grid?.addButton;
			return {
				found: !!addButton,
				text: addButton?.getText(),
				enabled: !addButton?.isDisabled()
			};
		});

		expect(buttonInfo.found).toBe(true);
		expect(buttonInfo.enabled).toBe(true);
	});

	test('should open Part Editor when clicking Add Part', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		// Click Add Part button
		await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			partManager.grid.addButton.fireHandler();
		});

		// Wait for Part Editor window to open (query by title since it has no alias)
		await page.waitForFunction(() => {
			const editors = Ext.ComponentQuery.query('window[title="Add Part"]');
			return editors.length > 0 && editors[0].isVisible();
		}, {timeout: 10000});

		const editorInfo = await page.evaluate(() => {
			const editor = Ext.ComponentQuery.query('window[title="Add Part"]')[0];
			return {
				windowVisible: editor?.isVisible(),
				hasNameField: !!editor?.down('textfield[name=name]'),
				hasDescriptionField: !!editor?.down('[name=description]'),
				hasCategoryField: !!editor?.down('[name=category]')
			};
		});

		expect(editorInfo.windowVisible).toBe(true);
		expect(editorInfo.hasNameField).toBe(true);
	});

	test('should filter parts by category selection', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		// Select root category
		await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			const tree = partManager.tree;
			const rootNode = tree.getRootNode();
			if (rootNode && rootNode.firstChild) {
				tree.getSelectionModel().select(rootNode.firstChild);
			}
		});

		// Wait for parts grid to filter
		await page.waitForTimeout(500);

		const filterInfo = await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			const tree = partManager.tree;
			const selectedCategory = tree?.getSelection()[0];
			return {
				categorySelected: !!selectedCategory,
				categoryName: selectedCategory?.get('name'),
				partsCount: partManager?.grid?.getStore()?.getCount() || 0
			};
		});

		expect(filterInfo.categorySelected).toBe(true);
	});

	test('should show part details panel', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		// Check for part details/display panel
		const detailsInfo = await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			return {
				hasDetailPanel: !!partManager?.detailPanel,
				hasDetail: !!partManager?.detail,
				hasStockLevel: !!partManager?.stockLevel,
				detailPanelTitle: partManager?.detailPanel?.title
			};
		});

		expect(detailsInfo.hasDetailPanel).toBe(true);
		expect(detailsInfo.hasDetail).toBe(true);
		expect(detailsInfo.hasStockLevel).toBe(true);
	});

	test('should show stock history grid in details panel', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		const stockHistoryInfo = await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			const stockLevel = partManager?.stockLevel;
			return {
				found: !!stockLevel,
				title: stockLevel?.title,
				hasStore: !!stockLevel?.getStore()
			};
		});

		expect(stockHistoryInfo.found).toBe(true);
		expect(stockHistoryInfo.title).toBe('Stock History');
	});
});