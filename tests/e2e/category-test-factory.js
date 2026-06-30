/**
 * Factory for generating category CRUD tests
 */
const {test, expect} = require('@playwright/test');
const {setupPage, waitForPartManager, waitForCategoryInTree, selectCategoryByName} = require('./helpers');

/**
 * Creates a test suite for category CRUD operations
 * @param {Object} config - Configuration for the category type
 * @param {string} config.name - Display name (e.g., 'Part', 'Footprint', 'StorageLocation')
 * @param {string} config.treeAlias - ExtJS alias for the tree component
 * @param {string} [config.editorComponent] - Component to open via openAppItem (null for Part which is default)
 */
function createCategoryTests(config) {
	const {name, treeAlias, editorComponent} = config;

	// Helper to wait for tree to be loaded
	async function waitForTree(page) {
		if (editorComponent) {
			// Wait for UserPreferenceStore first
			await page.waitForFunction(() => {
				const store = Ext.data.StoreManager.lookup('UserPreferenceStore');
				return store && store.isLoaded();
			}, {timeout: 10000});

			// Open the editor component
			await page.evaluate((comp) => {
				Limas.getApplication().openAppItem(comp);
			}, editorComponent);
		} else {
			// For Part categories, wait for Part Manager to be fully loaded
			await waitForPartManager(page);
		}

		// Wait for tree to be visible and loaded
		await page.waitForFunction((alias) => {
			const tree = Ext.ComponentQuery.query(alias)[0];
			if (!tree || !tree.isVisible()) return false;
			const store = tree.getStore();
			const rootNode = tree.getRootNode();
			// Just check that store is loaded and root node exists
			return store && store.isLoaded() && rootNode;
		}, treeAlias, {timeout: 15000});
	}

	// Helper to select root's first child
	async function selectRootChild(page) {
		await page.evaluate((alias) => {
			const tree = Ext.ComponentQuery.query(alias)[0];
			const rootNode = tree.getRootNode();
			if (rootNode && rootNode.firstChild) {
				tree.getSelectionModel().select(rootNode.firstChild);
			} else if (rootNode) {
				tree.getSelectionModel().select(rootNode);
			}
		}, treeAlias);
	}

	// Helper to wait for add button to be enabled
	async function waitForAddButton(page) {
		await page.waitForFunction((alias) => {
			const tree = Ext.ComponentQuery.query(alias)[0];
			return tree && tree.toolbarAddButton && !tree.toolbarAddButton.isDisabled();
		}, treeAlias, {timeout: 3000});
	}

	// Helper to click add button
	async function clickAddButton(page) {
		await page.evaluate((alias) => {
			const tree = Ext.ComponentQuery.query(alias)[0];
			tree.toolbarAddButton.fireHandler();
		}, treeAlias);
	}

	// Helper to wait for and fill add category window
	async function fillAndSaveCategory(page, categoryName) {
		await page.waitForFunction(() => {
			const windows = Ext.ComponentQuery.query('window[title="Add Category"]');
			return windows.length > 0 && windows[0].isVisible();
		}, {timeout: 5000});

		await page.evaluate((catName) => {
			const window = Ext.ComponentQuery.query('window[title="Add Category"]')[0];
			window.down('textfield[name=name]').setValue(catName);
			const saveButton = window.down('button[text="Save"]');
			if (saveButton) saveButton.fireHandler();
		}, categoryName);

		await page.waitForFunction(() => {
			const windows = Ext.ComponentQuery.query('window[title="Add Category"]');
			return windows.length === 0;
		}, {timeout: 10000});

		// PartCategoryEditorWindow keeps the window open after first save (title
		// flips to "Edit Category") so the user can add default parameters right
		// away. Tests don't need that — close any leftover Edit Category window
		// so subsequent steps start with a clean slate.
		await page.evaluate(() => {
			const editWindows = Ext.ComponentQuery.query('window[title="Edit Category"]');
			editWindows.forEach((w) => w.close());
		});
	}

	test.describe(`Limas ${name} Categories UI`, () => {

		test(`should show ${name} Categories tree`, async ({page}) => {
			await setupPage(page);
			await waitForTree(page);

			const treeInfo = await page.evaluate((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				const store = tree.getStore();
				return {
					visible: tree.isVisible(),
					rootLoaded: store.isLoaded(),
					nodeCount: store.getCount(),
					hasToolbar: !!tree.toolbar,
					hasAddButton: !!tree.toolbarAddButton,
					hasDeleteButton: !!tree.toolbarDeleteButton
				};
			}, treeAlias);

			expect(treeInfo.visible).toBe(true);
			expect(treeInfo.rootLoaded).toBe(true);
		});

		test(`should expand and collapse ${name} Categories tree`, async ({page}) => {
			await setupPage(page);
			await waitForTree(page);

			// Click expand button
			await page.evaluate((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				if (tree.toolbarExpandButton) {
					tree.toolbarExpandButton.fireHandler();
				}
			}, treeAlias);

			await page.waitForTimeout(500);

			const expandedInfo = await page.evaluate((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				const rootNode = tree.getRootNode();
				return {
					rootExpanded: rootNode?.isExpanded(),
					firstChildExpanded: rootNode?.firstChild?.isExpanded()
				};
			}, treeAlias);

			// Click collapse button
			await page.evaluate((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				if (tree.toolbarCollapseButton) {
					tree.toolbarCollapseButton.fireHandler();
				}
			}, treeAlias);

			await page.waitForTimeout(500);

			const collapsedInfo = await page.evaluate((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				const rootNode = tree.getRootNode();
				return {
					firstChildExpanded: rootNode?.firstChild?.isExpanded()
				};
			}, treeAlias);
		});

		test(`should create ${name} Category via UI`, async ({page}) => {
			await setupPage(page);
			await waitForTree(page);

			const categoryName = `E2E ${name} Category ` + Date.now();

			await selectRootChild(page);
			await waitForAddButton(page);
			await clickAddButton(page);
			await fillAndSaveCategory(page, categoryName);

			await waitForCategoryInTree(page, categoryName);
		});

		test(`should edit ${name} Category via UI`, async ({page}) => {
			await setupPage(page);
			await waitForTree(page);

			const originalName = `Edit ${name} Category ` + Date.now();

			// Create category first
			await selectRootChild(page);
			await waitForAddButton(page);
			await clickAddButton(page);
			await fillAndSaveCategory(page, originalName);
			await waitForCategoryInTree(page, originalName);

			// Select and edit
			await selectCategoryByName(page, originalName);

			await page.waitForFunction((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				return tree && tree.toolbarEditButton && !tree.toolbarEditButton.isDisabled();
			}, treeAlias, {timeout: 3000});

			await page.evaluate((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				tree.toolbarEditButton.fireHandler();
			}, treeAlias);

			await page.waitForFunction(() => {
				const windows = Ext.ComponentQuery.query('window[title="Edit Category"]');
				return windows.length > 0 && windows[0].isVisible();
			}, {timeout: 5000});

			const updatedName = originalName + ' UPDATED';
			await page.evaluate((catName) => {
				const window = Ext.ComponentQuery.query('window[title="Edit Category"]')[0];
				window.down('textfield[name=name]').setValue(catName);
				const saveButton = window.down('button[text="Save"]');
				if (saveButton) saveButton.fireHandler();
			}, updatedName);

			await page.waitForFunction(() => {
				const windows = Ext.ComponentQuery.query('window[title="Edit Category"]');
				return windows.length === 0;
			}, {timeout: 10000});

			await waitForCategoryInTree(page, updatedName);
		});

		test(`should delete ${name} Category via UI`, async ({page}) => {
			await setupPage(page);
			await waitForTree(page);

			const categoryName = `Delete ${name} Category ` + Date.now();

			// Create category first
			await selectRootChild(page);
			await waitForAddButton(page);
			await clickAddButton(page);
			await fillAndSaveCategory(page, categoryName);
			await waitForCategoryInTree(page, categoryName);

			// Select and delete
			await selectCategoryByName(page, categoryName);

			await page.waitForFunction((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				return tree && tree.toolbarDeleteButton && !tree.toolbarDeleteButton.isDisabled();
			}, treeAlias, {timeout: 3000});

			await page.evaluate((alias) => {
				const tree = Ext.ComponentQuery.query(alias)[0];
				tree.toolbarDeleteButton.fireHandler();
			}, treeAlias);

			await page.waitForFunction(() => Ext.Msg.isVisible(), {timeout: 3000});
			await page.click('span.x-btn-inner:text("Yes")');
			await page.waitForFunction(() => !Ext.Msg.isVisible(), {timeout: 5000});

			// Wait for category to disappear
			await page.waitForFunction((data) => {
				const tree = Ext.ComponentQuery.query(data.alias)[0];
				if (!tree) return false;
				const store = tree.getStore();
				return store.findNode('name', data.name) === null;
			}, {alias: treeAlias, name: categoryName}, {timeout: 10000});
		});
	});
}

module.exports = {createCategoryTests};
