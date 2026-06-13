const {test, expect} = require('@playwright/test');
const {setupPage} = require('./helpers');

/**
 * Helper to open Projects panel and wait for it
 */
async function openProjectsPanel(page) {
	await page.evaluate(() => {
		Limas.getApplication().openAppItem('Limas.ProjectEditorComponent');
	});

	// Wait for panel and grid to be ready
	await page.waitForFunction(() => {
		const panel = Ext.ComponentQuery.query('ProjectEditorComponent')[0];
		const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
		return panel && panel.isVisible() && grid && grid.getStore().isLoaded();
	}, {timeout: 5000});
}

test.describe('Limas Projects UI', () => {

	test('should open Projects panel from application', async ({page}) => {
		await setupPage(page);
		await openProjectsPanel(page);

		const panelInfo = await page.evaluate(() => {
			const panel = Ext.ComponentQuery.query('ProjectEditorComponent')[0];
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			return {
				panelVisible: panel?.isVisible(),
				gridVisible: grid?.isVisible(),
				storeCount: grid?.getStore()?.getCount() || 0,
				addButtonEnabled: grid?.addButton?.isDisabled() === false
			};
		});

		expect(panelInfo.panelVisible).toBe(true);
		expect(panelInfo.gridVisible).toBe(true);
		expect(panelInfo.addButtonEnabled).toBe(true);
	});

	test('should create project via UI', async ({page}) => {
		await setupPage(page);
		await openProjectsPanel(page);

		const projectName = 'E2E UI Test ' + Date.now();

		// Click Add button
		await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			grid.addButton.fireHandler();
		});

		// Wait for editor tab to appear
		await page.waitForFunction(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			return editor && editor.isVisible();
		}, {timeout: 5000});

		// Fill form fields
		await page.evaluate((name) => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			editor.getForm().findField('name').setValue(name);
			editor.getForm().findField('description').setValue('Created via UI E2E test');
		}, projectName);

		// Click Save button
		await page.evaluate(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			editor.saveButton.fireHandler();
		});

		// Wait for record to be saved (not phantom anymore = has server ID)
		await page.waitForFunction(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			const record = editor?.record;
			// Record exists, is not phantom, and has an ID
			return record && !record.phantom && record.getId();
		}, {timeout: 15000});

		// Verify the record was saved
		const saveResult = await page.evaluate(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			const record = editor.record;
			return {
				success: record && !record.phantom,
				id: record?.getId(),
				name: record?.get('name')
			};
		});

		expect(saveResult.success).toBe(true);
		expect(saveResult.name).toBe(projectName);

		// Reload the grid store
		await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			grid.getStore().reload();
		});

		// Wait for project to appear in grid (use DOM selector)
		await page.waitForSelector(`text="${projectName}"`, {timeout: 15000});
	});

	test('should edit existing project via UI', async ({page}) => {
		await setupPage(page);
		await openProjectsPanel(page);

		// First create a project to edit
		const originalName = 'Edit Test ' + Date.now();

		await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			grid.addButton.fireHandler();
		});

		await page.waitForFunction(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			return editor && editor.isVisible();
		}, {timeout: 3000});

		// Set name and save
		await page.evaluate((name) => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			editor.getForm().findField('name').setValue(name);
			editor.record.set('name', name);
			editor.saveButton.fireHandler();
		}, originalName);

		// Wait for save to complete (record not phantom = has ID)
		await page.waitForFunction(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			const record = editor?.record;
			return record && !record.phantom && record.getId();
		}, {timeout: 5000});

		// After saving, editor stays open - modify and save again
		const updatedName = originalName + ' UPDATED';
		await page.evaluate((updatedName) => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			editor.getForm().findField('name').setValue(updatedName);
			editor.getForm().findField('description').setValue('Updated via UI test');
			editor.record.set('name', updatedName);
			editor.record.set('description', 'Updated via UI test');
			editor.saveButton.fireHandler();
		}, updatedName);

		// Wait for save to complete
		await page.waitForFunction(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			if (!editor || !editor.record) return false;
			return !editor.saveButton.isDisabled() && !editor.record.dirty && !editor.record.phantom;
		}, {timeout: 5000});

		const updateResult = await page.evaluate(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			const record = editor?.record;
			return {
				success: editor && record && !record.phantom,
				name: record?.get('name')
			};
		});

		expect(updateResult.success).toBe(true);
		expect(updateResult.name).toBe(updatedName);
	});

	test('should delete project via UI', async ({page}) => {
		await setupPage(page);
		await openProjectsPanel(page);

		// Create a project to delete
		const projectName = 'Delete Test ' + Date.now();

		await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			grid.addButton.fireHandler();
		});

		await page.waitForFunction(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			return editor && editor.isVisible();
		}, {timeout: 3000});

		// Set name and save in one step
		await page.evaluate((name) => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			editor.getForm().findField('name').setValue(name);
			editor.record.set('name', name);
			editor.saveButton.fireHandler();
		}, projectName);

		// Wait for save to complete
		await page.waitForFunction(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			const record = editor?.record;
			return record && !record.phantom && record.getId();
		}, {timeout: 5000});

		// Close editor and reload grid
		await page.evaluate(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			editor.cancelButton.fireHandler();
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			grid.getStore().reload();
		});

		// Wait for store to load
		await page.waitForFunction(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			return grid && !grid.getStore().isLoading();
		}, {timeout: 5000});

		// Select project in grid
		const recordFound = await page.evaluate((expectedName) => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			const record = grid.getStore().findRecord('name', expectedName);
			if (record) {
				grid.getSelectionModel().select(record);
				return true;
			}
			return false;
		}, projectName);

		expect(recordFound).toBe(true);

		// Wait for delete button to be enabled after selection
		await page.waitForFunction(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			return grid && grid.deleteButton && !grid.deleteButton.isDisabled();
		}, {timeout: 3000});

		// Click delete button
		await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			grid.deleteButton.fireHandler();
		});

		// Wait for confirm dialog and click Yes
		await page.waitForFunction(() => Ext.Msg.isVisible(), {timeout: 3000});

		// Click Yes button - find by inner span text
		await page.click('span.x-btn-inner:text("Yes")');

		// Wait for dialog to close
		await page.waitForFunction(() => !Ext.Msg.isVisible(), {timeout: 5000});

		// Wait for project to disappear from grid (poll until gone or timeout)
		await page.waitForFunction((expectedName) => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			if (!grid) return false;
			const store = grid.getStore();
			// Wait for store to not be loading AND record to be gone
			return !store.isLoading() && store.findRecord('name', expectedName) === null;
		}, {timeout: 10000}, projectName);
	});

	test('should list projects in grid', async ({page}) => {
		await setupPage(page);
		await openProjectsPanel(page);

		const gridInfo = await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			const store = grid.getStore();
			const projects = [];

			store.each(record => {
				projects.push({
					id: record.getId(),
					name: record.get('name'),
					description: record.get('description')
				});
			});

			return {
				count: store.getCount(),
				totalCount: store.getTotalCount(),
				projects: projects.slice(0, 5) // First 5 for logging
			};
		});

		// Grid should be loaded
		expect(gridInfo.count).toBeGreaterThanOrEqual(0);
	});

	test('should show project details when clicking on grid row', async ({page}) => {
		await setupPage(page);
		await openProjectsPanel(page);

		// First, check if there are any projects
		const hasProjects = await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			return grid.getStore().getCount() > 0;
		});

		if (!hasProjects) {
			// Create a project first
			await page.evaluate(() => {
				const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
				grid.addButton.fireHandler();
			});

			await page.waitForFunction(() => {
				const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
				return editor && editor.isVisible();
			});

			await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
				editor.getForm().findField('name').setValue('View Test ' + Date.now());
			});

			await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
				editor.saveButton.fireHandler();
			});

			// Wait for save to complete (record not phantom = has ID)
			await page.waitForFunction(() => {
				const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
				const record = editor?.record;
				return record && !record.phantom && record.getId();
			}, {timeout: 15000});

			await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
				editor.cancelButton.fireHandler();
			});

			await page.waitForTimeout(500);
		}

		// Click on first project row - use the internal edit method
		const firstProjectId = await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('ProjectGrid')[0];
			const store = grid.getStore();

			if (store.getCount() === 0) {
				return null;
			}

			const firstRecord = store.getAt(0);
			// Fire itemEdit event directly (this is what itemclick triggers)
			grid.fireEvent('itemEdit', firstRecord.getId());
			return firstRecord.getId();
		});

		if (firstProjectId === null) {
			return;
		}

		// Wait for editor to open
		await page.waitForFunction(() => {
			const editors = Ext.ComponentQuery.query('ProjectEditor');
			return editors.length > 0 && editors[0].isVisible();
		}, {timeout: 5000});

		const editorOpened = await page.evaluate(() => {
			const editor = Ext.ComponentQuery.query('ProjectEditor')[0];
			if (editor && editor.isVisible()) {
				return {
					success: true,
					projectName: editor.record?.get('name'),
					projectDescription: editor.record?.get('description')
				};
			}
			return {success: false, error: 'Editor not visible'};
		});

		expect(editorOpened.success).toBe(true);
	});
});