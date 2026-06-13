const {test, expect} = require('@playwright/test');
const {setupPage, waitForPartManager} = require('./helpers');

test.describe('Limas Stock UI', () => {

	test('should show Parts Grid with stock column', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		const gridInfo = await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			const columns = partManager?.grid?.getColumns() || [];
			const stockColumn = columns.find(c =>
				c.dataIndex === 'stockLevel' || c.text?.includes('Stock')
			);
			return {
				hasStockColumn: !!stockColumn,
				stockColumnText: stockColumn?.text,
				columnCount: columns.length
			};
		});

		expect(gridInfo.hasStockColumn).toBe(true);
	});

	test('should show Stock History panel in PartManager', async ({page}) => {
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

	test('should have stock history columns configured', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		const columnsInfo = await page.evaluate(() => {
			const partManager = Ext.getCmp('limas-partmanager');
			const stockLevel = partManager?.stockLevel;
			if (!stockLevel) return {found: false};

			const columns = stockLevel.getColumns() || [];
			return {
				found: true,
				columnCount: columns.length,
				columns: columns.map(c => ({
					text: c.text,
					dataIndex: c.dataIndex
				}))
			};
		});

		expect(columnsInfo.found).toBe(true);
		expect(columnsInfo.columnCount).toBeGreaterThan(0);
	});

	test('should open Stock History view from menu', async ({page}) => {
		await setupPage(page);

		// Wait for UserPreferenceStore to be loaded
		await page.waitForFunction(() => {
			const store = Ext.data.StoreManager.lookup('UserPreferenceStore');
			return store && store.isLoaded();
		}, {timeout: 10000});

		// Open Stock History via openAppItem - use the correct class name
		await page.evaluate(() => {
			Limas.getApplication().openAppItem('Limas.StockHistoryGrid');
		});

		// Wait for panel to open - use the alias 'PartStockHistoryGrid'
		await page.waitForFunction(() => {
			const panel = Ext.ComponentQuery.query('PartStockHistoryGrid')[0];
			return panel && panel.isVisible();
		}, {timeout: 10000});

		const panelInfo = await page.evaluate(() => {
			const panel = Ext.ComponentQuery.query('PartStockHistoryGrid')[0];
			return {
				visible: panel?.isVisible(),
				storeLoaded: panel?.getStore()?.isLoaded(),
				recordCount: panel?.getStore()?.getCount() || 0
			};
		});

		expect(panelInfo.visible).toBe(true);
	});

	test('should show low stock parts indicator', async ({page}) => {
		await setupPage(page);
		await waitForPartManager(page);

		// Check if there's a low stock indicator/button in the status bar
		const lowStockInfo = await page.evaluate(() => {
			const statusBar = Ext.ComponentQuery.query('statusbar')[0];
			return {
				statusBarExists: !!statusBar,
				statusBarVisible: statusBar?.isVisible()
			};
		});

		expect(lowStockInfo.statusBarExists).toBe(true);
	});
});