const {test, expect} = require('@playwright/test');
const {setupPage} = require('./helpers');

test.describe('Limas User Preferences', () => {
	test('should show user preferences panel', async ({page}) => {
		await setupPage(page);

		// Open User Preferences via openAppItem
		await page.evaluate(() => {
			Limas.getApplication().openAppItem('Limas.Components.UserPreferences.Panel');
		});

		// Wait for preferences panel to open
		await page.waitForFunction(() => {
			const panels = Ext.ComponentQuery.query('panel');
			return panels.some(p => {
				const title = typeof p.title === 'string' ? p.title : '';
				return (title.includes('Preferences') || title.includes('System')) && p.isVisible();
			});
		}, {timeout: 5000});

		const prefsInfo = await page.evaluate(() => {
			const panels = Ext.ComponentQuery.query('panel');
			const prefsPanel = panels.find(p => {
				const title = typeof p.title === 'string' ? p.title : '';
				return title.includes('Preferences') || title.includes('System');
			});
			return {
				found: !!prefsPanel,
				visible: prefsPanel?.isVisible(),
				title: prefsPanel?.title
			};
		});

		expect(prefsInfo.found).toBe(true);
		expect(prefsInfo.visible).toBe(true);
	});
});
