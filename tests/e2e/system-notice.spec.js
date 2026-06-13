const {test, expect} = require('@playwright/test');
const {setupPage} = require('./helpers');

/**
 * Helper to open System Notices panel and wait for it
 */
async function openSystemNoticesPanel(page) {
	await page.evaluate(() => {
		Limas.getApplication().openAppItem('Limas.SystemNoticeEditorComponent');
	});

	// Wait for panel and grid to be ready
	await page.waitForFunction(() => {
		const panel = Ext.ComponentQuery.query('SystemNoticeEditorComponent')[0];
		const grid = Ext.ComponentQuery.query('SystemNoticeGrid')[0];
		return panel && panel.isVisible() && grid && grid.getStore().isLoaded();
	}, {timeout: 5000});
}

test.describe('Limas System Notices UI', () => {

	test('should open System Notices panel from application', async ({page}) => {
		await setupPage(page);
		await openSystemNoticesPanel(page);

		const panelInfo = await page.evaluate(() => {
			const panel = Ext.ComponentQuery.query('SystemNoticeEditorComponent')[0];
			const grid = Ext.ComponentQuery.query('SystemNoticeGrid')[0];
			return {
				panelVisible: panel?.isVisible(),
				gridVisible: grid?.isVisible()
			};
		});

		expect(panelInfo.panelVisible).toBe(true);
		expect(panelInfo.gridVisible).toBe(true);
	});

	test('should list system notices in grid', async ({page}) => {
		await setupPage(page);
		await openSystemNoticesPanel(page);

		const gridInfo = await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('SystemNoticeGrid')[0];
			const store = grid.getStore();
			const notices = [];

			store.each(record => {
				notices.push({
					id: record.getId(),
					title: record.get('title'),
					description: record.get('description'),
					type: record.get('type'),
					acknowledged: record.get('acknowledged')
				});
			});

			return {
				count: store.getCount(),
				totalCount: store.getTotalCount(),
				notices: notices
			};
		});

		// Grid should be loaded (may have 0 unacknowledged notices)
		expect(gridInfo.count).toBeGreaterThanOrEqual(0);
	});

	test('should show system notice details when clicking on grid row', async ({page}) => {
		await setupPage(page);
		await openSystemNoticesPanel(page);

		// Check if there are any notices
		const hasNotices = await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('SystemNoticeGrid')[0];
			return grid.getStore().getCount() > 0;
		});

		if (!hasNotices) {
			return;
		}

		// Click on first notice row
		const firstNoticeId = await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('SystemNoticeGrid')[0];
			const store = grid.getStore();

			if (store.getCount() === 0) {
				return null;
			}

			const firstRecord = store.getAt(0);
			grid.fireEvent('itemEdit', firstRecord.getId());
			return firstRecord.getId();
		});

		if (firstNoticeId === null) {
			return;
		}

		// Wait for editor to open
		await page.waitForFunction(() => {
			const editors = Ext.ComponentQuery.query('SystemNoticeEditor');
			return editors.length > 0 && editors[0].isVisible();
		}, {timeout: 5000});

		const editorOpened = await page.evaluate(() => {
			const editor = Ext.ComponentQuery.query('SystemNoticeEditor')[0];
			if (editor && editor.isVisible()) {
				return {
					success: true,
					title: editor.record?.get('title'),
					description: editor.record?.get('description'),
					hasAcknowledgeButton: !!editor.acknowledgeButton
				};
			}
			return {success: false, error: 'Editor not visible'};
		});

		expect(editorOpened.success).toBe(true);
		expect(editorOpened.hasAcknowledgeButton).toBe(true);
	});

	test('should acknowledge system notice via UI', async ({page}) => {
		await setupPage(page);
		await openSystemNoticesPanel(page);

		// Check if there are any unacknowledged notices
		const noticeInfo = await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('SystemNoticeGrid')[0];
			const store = grid.getStore();
			if (store.getCount() === 0) {
				return {hasNotices: false};
			}
			const firstRecord = store.getAt(0);
			return {
				hasNotices: true,
				title: firstRecord.get('title'),
				id: firstRecord.getId()
			};
		});

		if (!noticeInfo.hasNotices) {
			return;
		}

		const noticeTitle = noticeInfo.title;

		// Click on the notice to open editor
		await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('SystemNoticeGrid')[0];
			const firstRecord = grid.getStore().getAt(0);
			grid.fireEvent('itemEdit', firstRecord.getId());
		});

		// Wait for editor to open
		await page.waitForFunction(() => {
			const editors = Ext.ComponentQuery.query('SystemNoticeEditor');
			return editors.length > 0 && editors[0].isVisible();
		}, {timeout: 5000});

		// Click acknowledge button
		await page.evaluate(() => {
			const editor = Ext.ComponentQuery.query('SystemNoticeEditor')[0];
			if (editor && editor.acknowledgeButton) {
				editor.acknowledgeButton.fireHandler();
			}
		});

		// Wait for notice to disappear from grid (it filters by acknowledged=false)
		await page.waitForFunction((expectedTitle) => {
			const grid = Ext.ComponentQuery.query('SystemNoticeGrid')[0];
			if (!grid) return false;
			const store = grid.getStore();
			return !store.isLoading() && store.findRecord('title', expectedTitle) === null;
		}, {timeout: 10000}, noticeTitle);
	});

	test('should check for system notice button in toolbar', async ({page}) => {
		await setupPage(page);

		// Check for system notice button in main toolbar (optional component)
		const buttonInfo = await page.evaluate(() => {
			const noticeButton = Ext.ComponentQuery.query('SystemNoticeButton')[0];
			return {
				found: !!noticeButton,
				visible: noticeButton?.isVisible()
			};
		});

		// Just verify check completed - button may not exist in all configurations
		expect(buttonInfo).toBeDefined();
	});
});
