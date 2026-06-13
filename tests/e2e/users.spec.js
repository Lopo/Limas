const {test, expect} = require('@playwright/test');
const {setupPage} = require('./helpers');

/**
 * Helper to open Users panel and wait for it
 */
async function openUsersPanel(page) {
	await page.evaluate(() => {
		Limas.getApplication().openAppItem('Limas.UserEditorComponent');
	});

	// Wait for panel and grid to be ready
	await page.waitForFunction(() => {
		const panel = Ext.ComponentQuery.query('UserEditorComponent')[0];
		const grid = Ext.ComponentQuery.query('UserGrid')[0];
		return panel && panel.isVisible() && grid && grid.getStore().isLoaded();
	}, {timeout: 5000});
}

test.describe('Limas Users Administration', () => {
	test('should open Users panel from application', async ({page}) => {
		await setupPage(page);
		await openUsersPanel(page);

		const panelInfo = await page.evaluate(() => {
			const panel = Ext.ComponentQuery.query('UserEditorComponent')[0];
			const grid = Ext.ComponentQuery.query('UserGrid')[0];
			return {
				panelVisible: panel?.isVisible(),
				gridVisible: grid?.isVisible(),
				addButtonEnabled: grid?.addButton?.isDisabled() === false
			};
		});

		expect(panelInfo.panelVisible).toBe(true);
		expect(panelInfo.gridVisible).toBe(true);
		expect(panelInfo.addButtonEnabled).toBe(true);
	});

	// These tests must run in order - create → duplicate → edit → delete
	test.describe.serial('User CRUD operations', () => {
		const testUsername = 'e2e_user_' + Date.now();
		const testEmail = testUsername + '@test.com';

		test('should create user', async ({page}) => {
			await setupPage(page);
			await openUsersPanel(page);

			// Get initial usernames list and verify admin exists
			const initialUsernames = await page.evaluate(() => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				const store = grid.getStore();
				const usernames = [];
				store.each(record => usernames.push(record.get('username')));
				return usernames;
			});

			expect(initialUsernames).toContain('admin');

			// Click Add button
			await page.evaluate(() => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				grid.addButton.fireHandler();
			});

			// Wait for editor tab to appear
			await page.waitForFunction(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				return editor && editor.isVisible();
			}, {timeout: 5000});

			// Fill form fields
			await page.evaluate((data) => {
				const form = Ext.ComponentQuery.query('UserEditor')[0].getForm();
				form.findField('username').setValue(data.username);
				form.findField('email').setValue(data.email);
				form.findField('newPassword').setValue(data.password);
			}, {username: testUsername, email: testEmail, password: 'testpass123'});

			// Click Save button
			await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				editor.saveButton.fireHandler();
			});

			// Wait for new user to appear in grid (as gridcell, not in editor form)
			await page.waitForSelector(`.x-grid-cell-inner:has-text("${testUsername}")`, {timeout: 15000});

			// Verify user count increased
			const finalUsernames = await page.evaluate(() => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				const store = grid.getStore();
				const usernames = [];
				store.each(record => usernames.push(record.get('username')));
				return usernames;
			});

			expect(finalUsernames).toContain(testUsername);
			expect(finalUsernames.length).toBe(initialUsernames.length + 1);
		});

		test('should not allow duplicate username', async ({page}) => {
			await setupPage(page);
			await openUsersPanel(page);

			// Try to create user with same testUsername (created in previous test)
			await page.evaluate(() => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				grid.addButton.fireHandler();
			});

			await page.waitForFunction(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				return editor && editor.isVisible() && editor.record?.phantom;
			}, {timeout: 5000});

			// Fill form with duplicate username
			await page.evaluate((data) => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				const form = editor.getForm();
				form.findField('username').setValue(data.username);
				form.findField('email').setValue(data.email);
				form.findField('newPassword').setValue(data.password);
				editor.saveButton.fireHandler();
			}, {username: testUsername, email: 'other_' + testEmail, password: 'testpass123'});

			// Wait for error response - record should stay phantom
			await page.waitForTimeout(3000);

			// Verify record is still phantom (not saved due to duplicate)
			const result = await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				return {
					isPhantom: editor?.record?.phantom
				};
			});

			expect(result.isPhantom).toBe(true);

			// Close editor
			await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				editor.cancelButton.fireHandler();
			});
		});

		test('should edit user', async ({page}) => {
			await setupPage(page);
			await openUsersPanel(page);

			// Find and edit the testUsername user
			await page.evaluate((username) => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				const record = grid.getStore().findRecord('username', username);
				if (record) {
					grid.fireEvent('itemEdit', record.getId());
				}
			}, testUsername);

			// Wait for editor to open and provider to be loaded (onStartEdit has 200ms delay)
			await page.waitForFunction(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				if (!editor || !editor.isVisible() || editor.record?.phantom) return false;
				// Wait for provider to be set (onStartEdit completed)
				const providerField = editor.down('#provider');
				return providerField && providerField.getValue();
			}, {timeout: 5000});

			// Modify email
			const newEmail = 'updated_' + Date.now() + '@test.com';
			await page.evaluate((email) => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				editor.getForm().findField('email').setValue(email);
			}, newEmail);

			// Small delay to let form values sync to record (Firefox timing issue)
			await page.waitForTimeout(200);

			// Save
			await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				editor.saveButton.fireHandler();
			});

			// Wait for save to complete
			await page.waitForFunction(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				if (!editor || !editor.record) return false;
				return !editor.saveButton.isDisabled() && !editor.record.dirty;
			}, {timeout: 5000});

			// Close the editor
			await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				editor.fireEvent('editorClose', editor);
			});

			// Wait for editor to close
			await page.waitForFunction(() => {
				const editors = Ext.ComponentQuery.query('UserEditor');
				return editors.length === 0 || !editors[0].isVisible();
			}, {timeout: 3000});

			// Reopen the user to verify saved data
			await page.evaluate((username) => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				const record = grid.getStore().findRecord('username', username);
				if (record) {
					grid.fireEvent('itemEdit', record.getId());
				}
			}, testUsername);

			// Wait for editor to open and provider to be loaded (onStartEdit has 200ms delay)
			await page.waitForFunction(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				if (!editor || !editor.isVisible() || editor.record?.phantom) return false;
				// Wait for provider to be set (onStartEdit completed)
				const providerField = editor.down('#provider');
				return providerField && providerField.getValue();
			}, {timeout: 5000});

			// Verify email in the input field
			const updateResult = await page.evaluate(() => {
				const editor = Ext.ComponentQuery.query('UserEditor')[0];
				const emailField = editor.getForm().findField('email');
				return {
					email: emailField?.getValue()
				};
			});

			expect(updateResult.email).toBe(newEmail);
		});

		test('should delete user', async ({page}) => {
			await setupPage(page);
			await openUsersPanel(page);

			// Select testUsername user in grid
			const recordFound = await page.evaluate((expectedUsername) => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				const record = grid.getStore().findRecord('username', expectedUsername);
				if (record) {
					grid.getSelectionModel().select(record);
					return true;
				}
				return false;
			}, testUsername);

			expect(recordFound).toBe(true);

			// Wait for delete button to be enabled
			await page.waitForFunction(() => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				return grid && grid.deleteButton && !grid.deleteButton.isDisabled();
			}, {timeout: 3000});

			// Click delete button
			await page.evaluate(() => {
				const grid = Ext.ComponentQuery.query('UserGrid')[0];
				grid.deleteButton.fireHandler();
			});

			// Wait for confirm dialog and click Yes
			await page.waitForFunction(() => Ext.Msg.isVisible(), {timeout: 3000});
			await page.click('span.x-btn-inner:text("Yes")');

			// Wait for dialog to close and user to disappear from grid
			await page.waitForFunction(() => !Ext.Msg.isVisible(), {timeout: 5000});
			await page.waitForSelector(`.x-grid-cell-inner:has-text("${testUsername}")`, {state: 'detached', timeout: 10000});
		});
	});

	test('should not allow editing protected admin user', async ({page}) => {
		await setupPage(page);
		await openUsersPanel(page);

		// Find and click on admin user
		const adminFound = await page.evaluate(() => {
			const grid = Ext.ComponentQuery.query('UserGrid')[0];
			const record = grid.getStore().findRecord('username', 'admin');
			if (record) {
				grid.fireEvent('itemEdit', record.getId());
				return true;
			}
			return false;
		});

		expect(adminFound).toBe(true);

		// Wait for editor with admin record to open
		await page.waitForFunction(() => {
			const editors = Ext.ComponentQuery.query('UserEditor');
			return editors.some(e => e.isVisible() && e.record?.get('username') === 'admin');
		}, {timeout: 5000});

		// Wait for onStartEdit to complete (has 200ms delay in UserEditor)
		await page.waitForTimeout(500);

		// Check that save button is disabled for protected user
		const editorState = await page.evaluate(() => {
			const editors = Ext.ComponentQuery.query('UserEditor');
			const editor = editors.find(e => e.record?.get('username') === 'admin');
			return {
				isProtected: editor?.record?.get('protected'),
				saveDisabled: editor?.saveButton?.isDisabled(),
			};
		});

		expect(editorState.isProtected).toBe(true);
		expect(editorState.saveDisabled).toBe(true);
	});
});
