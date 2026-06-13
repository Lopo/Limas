const {test, expect} = require('@playwright/test');
const {setupPage} = require('./helpers');

test.describe('Explore Limas ExtJS', () => {
	test('explore ExtJS components', async ({page}) => {
		await setupPage(page);

		// Pozrime sa na title
		const title = await page.title();
		expect(title).toBeTruthy();

		// Získame info o ExtJS verzii
		const extVersion = await page.evaluate(() => {
			return Ext.getVersion().version;
		});
		expect(extVersion).toMatch(/^7\./);

		// Nájdeme všetky ExtJS komponenty
		const components = await page.evaluate(() => {
			const comps = [];
			Ext.ComponentQuery.query('*').forEach(cmp => {
				if (cmp.xtype && cmp.isVisible && cmp.isVisible()) {
					comps.push({
						xtype: cmp.xtype,
						id: cmp.id,
						text: cmp.text || cmp.title || ''
					});
				}
			});
			return comps.slice(0, 20);
		});
		expect(components.length).toBeGreaterThan(0);

		// Nájdeme menu položky
		const menuItems = await page.evaluate(() => {
			const items = [];
			Ext.ComponentQuery.query('menuitem').forEach(item => {
				if (item.text) {
					items.push(item.text);
				}
			});
			return items;
		});
		expect(menuItems.length).toBeGreaterThanOrEqual(0);
	});
});
