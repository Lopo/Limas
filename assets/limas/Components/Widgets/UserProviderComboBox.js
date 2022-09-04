Ext.define('Limas.UserProviderComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.UserProviderComboBox',
	forceSelection: true,
	displayField: 'type',
	initComponent: function () {
		this.store = Limas.getApplication().getUserProviderStore();
		this.callParent();
	}
});
