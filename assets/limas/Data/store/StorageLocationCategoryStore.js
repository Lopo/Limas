Ext.define('Limas.Data.store.StorageLocationCategoryStore', {
	extend: 'Ext.data.TreeStore',

	storeId: 'StorageLocationCategoryStore',
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
	/**
	 * Sort by name ascending by default
	 */
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

	model: 'Limas.Entity.StorageLocationCategory',

	proxy: {
		ignoreLoadId: '@local-tree-root',
		url: '/api/storage_location_categories/getExtJSRootNode',
		type: 'Hydra',
		appendId: false,
		reader: {
			type: 'tree'
		}
	}
});
