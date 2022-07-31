Ext.define('Limas.PartUnitComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.PartUnitComboBox',
	initComponent: function () {
		this.store = Limas.getApplication().getPartUnitStore();
		this.callParent();
	}
});
