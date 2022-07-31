Ext.define('Limas.ManufacturerComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.ManufacturerComboBox',
	initComponent: function () {
		this.store = Limas.getApplication().getManufacturerStore();
		this.callParent();
	}
});
