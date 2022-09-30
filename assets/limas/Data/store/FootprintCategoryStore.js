Ext.define('Limas.Data.store.FootprintCategoryStore', {
	extend: 'Ext.data.TreeStore',

	storeId: 'FootprintCategoryStore',

	/**
	 * Don't sort remotely as this is a tree store
	 */
	remoteSort: false,
	/**
	 * Sort folders alphabetically
	 */
	folderSort: true,
	/**
	 * Show the root node by default
	 */
	rootVisible: false,
	autoLoad: true,
	sorters: [
		{
			property: 'name',
			direction: 'ASC'
		}
	],

	/**
	 * Virtual Root Node
	 */
	root: {
		'@id': '@local-tree-root',
		name: 'virtual root - should not be visible'
	},

	model: 'Limas.Entity.FootprintCategory',

	proxy: {
		ignoreLoadId: '@local-tree-root',
		url: '/api/footprint_categories/getExtJSRootNode',
		type: 'Hydra',
		appendId: false,
		reader: {
			type: 'tree'
		}
	}
});
