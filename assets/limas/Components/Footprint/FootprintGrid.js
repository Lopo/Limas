Ext.define('Limas.FootprintGrid', {
	extend: 'Limas.EditorGrid',
	xtype: 'limas.FootprintGrid',
	columns: [
		{header: i18n('Footprint'), dataIndex: 'name', flex: 1}
	],
	addButtonText: i18n('Add Footprint'),
	addButtonIconCls: 'partkeepr-icon footprint_add',
	deleteButtonText: i18n('Delete Footprint'),
	deleteButtonIconCls: 'partkeepr-icon footprint_delete',
	features: [
		{
			ftype: 'grouping',
			groupHeaderTpl: '{name} ({children.length})',
			enableNoGroups: true
		}
	]
});
