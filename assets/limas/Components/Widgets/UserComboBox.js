Ext.define('Limas.UserComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.UserComboBox',
	displayField: 'username',

	initComponent: function () {
		this.store = Limas.getApplication().getUserStore();
		this.callParent();
	}
});
