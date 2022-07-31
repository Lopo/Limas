Ext.define('Limas.UnitComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.UnitComboBox',
	forceSelection: true,
	allowBlank: true,
	emptyText: i18n('Unit'),
	initComponent: function () {
		this.store = Limas.getApplication().getUnitStore();
		this.callParent();
	}
});
