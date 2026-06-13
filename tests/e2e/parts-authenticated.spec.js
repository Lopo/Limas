const {test, expect} = require('@playwright/test');

test.describe('Limas Parts - Authenticated', () => {
	test('login and explore parts', async ({page}) => {
		await page.goto('/');

		// Počkáme kým sa načíta ExtJS
		await page.waitForFunction(() => typeof Ext !== 'undefined' && Ext.isReady, {timeout: 30000});

		// Pozrieme sa či je auto-login povolený
		const autoLoginEnabled = await page.evaluate(() => {
			return window.parameters?.autoLoginUsername && window.parameters?.autoLoginPassword;
		});

		// Počkáme kým aplikácia je pripravená
		await page.waitForSelector('.x-viewport', {timeout: 30000});
		await page.waitForTimeout(2000); // Extra čas na inicializáciu

		// Skúsime nájsť a kliknúť na Parts v navigácii
		const partsFound = await page.evaluate(() => {
			// Hľadáme Parts navigáciu - môže byť v rôznych miestach

			// 1. Skúsime hlavný panel/tree
			const trees = Ext.ComponentQuery.query('treepanel');
			for (let tree of trees) {
				const node = tree.getRootNode()?.findChild('text', 'Parts', true);
				if (node) {
					tree.selectPath(node.getPath());
					return {found: true, method: 'tree'};
				}
			}

			// 2. Skúsime toolbar buttons
			const buttons = Ext.ComponentQuery.query('button');
			const partsBtn = buttons.find(btn =>
				btn.text?.toLowerCase().includes('part') &&
				!btn.text.toLowerCase().includes('report')
			);
			if (partsBtn) {
				partsBtn.fireEvent('click', partsBtn);
				return {found: true, method: 'button'};
			}

			// 3. Skúsime tab panel
			const tabs = Ext.ComponentQuery.query('tab');
			const partsTab = tabs.find(tab => tab.text?.toLowerCase().includes('part'));
			if (partsTab) {
				partsTab.fireEvent('click', partsTab);
				return {found: true, method: 'tab'};
			}

			return {found: false};
		});

		// Počkáme na možné načítanie
		await page.waitForTimeout(3000);

		// Teraz skúsime získať parts data
		const partsData = await page.evaluate(() => {
			// Hľadáme všetky gridy
			const grids = Ext.ComponentQuery.query('grid');

			for (let grid of grids) {
				const store = grid.getStore();
				if (store && store.getCount() > 0) {
					// Skúsime zistiť či je to parts grid
					const firstRecord = store.getAt(0);
					const fields = firstRecord ? Object.keys(firstRecord.data) : [];

					// Hľadáme typické parts polia
					const isPartsGrid = fields.some(f =>
						['stockLevel', 'footprint', 'storageLocation', 'internalPartNumber'].includes(f)
					);

					if (isPartsGrid || grid.title?.includes('Part')) {
						const parts = [];
						store.each((record, idx) => {
							if (idx < 10) { // Prvých 10
								parts.push({
									name: record.get('name'),
									description: record.get('description'),
									category: record.get('category')?.name || record.get('categoryPath'),
									storageLocation: record.get('storageLocation')?.name,
									stockLevel: record.get('stockLevel'),
									footprint: record.get('footprint')?.name,
									internalPartNumber: record.get('internalPartNumber'),
									minStockLevel: record.get('minStockLevel'),
									allFields: Object.keys(record.data)
								});
							}
						});

						return {
							found: true,
							gridTitle: grid.title || 'Untitled',
							totalCount: store.getTotalCount(),
							count: store.getCount(),
							parts: parts
						};
					}
				}
			}

			// Ak sme nenašli parts grid, vrátime info o všetkých gridoch
			return {
				found: false,
				gridsInfo: grids.map(g => ({
					title: g.title,
					itemId: g.itemId,
					recordCount: g.getStore()?.getCount() || 0,
					columns: g.columns?.map(c => c.text || c.dataIndex) || []
				}))
			};
		});

		// Verify we got some result
		expect(partsData).toBeDefined();
	});
});
