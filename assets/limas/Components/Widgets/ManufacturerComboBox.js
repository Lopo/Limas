Ext.define('Limas.ManufacturerComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.ManufacturerComboBox',
	initComponent: function () {
		this.store = Ext.data.StoreManager.lookup('ManufacturerStore');
		this.callParent();
	}
});
