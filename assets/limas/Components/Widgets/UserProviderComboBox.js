Ext.define('Limas.UserProviderComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.UserProviderComboBox',
	forceSelection: true,
	displayField: 'type',
	initComponent: function () {
		this.store = Ext.data.StoreManager.lookup('UserProviderStore');
		this.callParent();
	}
});
