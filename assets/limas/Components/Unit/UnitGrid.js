Ext.define('Limas.UnitGrid', {
	extend: 'Limas.EditorGrid',
	alias: 'widget.UnitGrid',
	columns: [
		{header: i18n('Unit'), dataIndex: 'name', flex: 1},
		{header: i18n('Symbol'), dataIndex: 'symbol', width: 60}
	],
	addButtonText: i18n('Add Unit'),
	addButtonIconCls: 'limas-icon unit_add',
	deleteButtonText: i18n('Delete Unit'),
	deleteButtonIconCls: 'limas-icon unit_delete',
	automaticPageSize: true,
	initComponent: function () {
		this.callParent();
	}
});
