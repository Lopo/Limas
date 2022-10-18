Ext.define('Limas.UserComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.UserComboBox',
	displayField: 'username',

	initComponent: function () {
		this.store = Ext.data.StoreManager.lookup('UserStore');
		this.callParent();
	}
});
