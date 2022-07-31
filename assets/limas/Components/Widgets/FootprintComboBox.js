Ext.define('Limas.FootprintComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.FootprintComboBox',
	initComponent: function () {
		this.store = Limas.getApplication().getFootprintStore();
		this.callParent();
	}
});
