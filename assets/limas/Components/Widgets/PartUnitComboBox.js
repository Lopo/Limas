Ext.define('Limas.PartUnitComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.PartUnitComboBox',
	initComponent: function () {
		this.store = Ext.data.StoreManager.lookup('PartMeasurementUnitStore');
		this.callParent();
	}
});
