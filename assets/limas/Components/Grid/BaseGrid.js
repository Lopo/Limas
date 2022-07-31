/**
 * Defines an abstract grid which includes the grid menu plugin
 */
Ext.define('Limas.BaseGrid', {
	extend: 'Ext.grid.Panel',
	alias: 'widget.BaseGrid',

	renderers: [],

	initComponent: function () {
		this.defaultColumnConfiguration = this.columns;
		this.callParent();
	},
	getDefaultColumnConfiguration: function () {
		return this.defaultColumnConfiguration;
	}
});
