Ext.define('Limas.Components.UserPreferences.Preferences.StockConfiguration', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		this.confirmInlineStockLevelChangesCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Confirm in-line stock level changes from the parts grid')
		});

		this.confirmInlineStockLevelChangesCheckbox.setValue(Limas.getApplication().getUserPreference('limas.inline-stock-change.confirm', true) !== false);

		this.items = [this.confirmInlineStockLevelChangesCheckbox];

		this.callParent();
	},
	onSave: function () {
		Limas.getApplication().setUserPreference('limas.inline-stock-change.confirm', this.confirmInlineStockLevelChangesCheckbox.getValue());
	},
	statics: {
		iconCls: 'web-icon brick',
		title: i18n('Stock'),
		menuPath: [{iconCls: 'fugue-icon ui-scroll-pane-image', text: i18n('User Interface')}]
	}
});
