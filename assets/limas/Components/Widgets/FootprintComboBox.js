Ext.define('Limas.FootprintComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.FootprintComboBox',
	initComponent: function () {
		this.store = Ext.data.StoreManager.lookup('FootprintStore');
		this.callParent();
	}
});
