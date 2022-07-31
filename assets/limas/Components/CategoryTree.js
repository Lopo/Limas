Ext.define('Limas.CategoryTree', {
	alias: 'widget.CategoryTree',
	extend: 'Ext.tree.Panel',
	categoryModel: null,
	viewConfig: {
		animate: false
	},
	loaded: false,
	rootVisible: false
});
