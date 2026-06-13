/**
 * Common helper functions for E2E tests
 */

/**
 * Helper to wait for ExtJS and get authenticated session
 */
async function setupPage(page) {
	await page.goto('/');
	await page.waitForFunction(() => typeof Ext !== 'undefined' && Ext.isReady, {timeout: 15000});
	await page.waitForSelector('#loader-wrapper', {state: 'hidden', timeout: 15000});
	// Wait for status bar to show "Ready."
	await page.waitForSelector('.x-status-text:has-text("Ready.")', {timeout: 15000});
}

/**
 * Helper to wait for PartManager to be fully loaded
 */
async function waitForPartManager(page) {
	await page.waitForFunction(() => {
		const partManager = Ext.getCmp('limas-partmanager');
		if (!partManager || !partManager.isVisible()) return false;
		const grid = partManager.grid;
		const tree = partManager.tree;
		return grid && tree && tree.getStore().isLoaded();
	}, {timeout: 15000});
}

/**
 * Helper to wait for Part Category tree to be fully loaded
 */
async function waitForPartCategoryTree(page) {
	await page.waitForFunction(() => {
		const tree = Ext.ComponentQuery.query('PartCategoryTree')[0];
		if (!tree || !tree.isVisible()) return false;
		const store = tree.getStore();
		// Wait for store to be loaded AND have at least the root node
		return store && store.isLoaded() && tree.getRootNode() && tree.getRootNode().childNodes.length > 0;
	}, {timeout: 15000});
}

/**
 * Helper to wait for a category to appear in the tree
 */
async function waitForCategoryInTree(page, categoryName) {
	// Wait for category to appear in DOM (tree should auto-update after save)
	await page.waitForSelector(`.x-tree-node-text:has-text("${categoryName}")`, {timeout: 20000});
}

/**
 * Helper to select a category by name in the tree
 */
async function selectCategoryByName(page, categoryName) {
	await page.click(`text="${categoryName}"`);
	await page.waitForTimeout(300);
}

module.exports = {
	setupPage,
	waitForPartManager,
	waitForPartCategoryTree,
	waitForCategoryInTree,
	selectCategoryByName
};
