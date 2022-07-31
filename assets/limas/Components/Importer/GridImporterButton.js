Ext.define('Limas.Importer.GridImporterButton', {
	extend: 'Ext.button.Button',

	initComponent: function () {
		this.handler = this.onImport;
		this.callParent(arguments);
	},
	onImport: function () {
		Ext.create('Ext.window.Window', {
			items: Ext.create('Limas.Importer.Importer', {
				model: this.up('gridpanel').getStore().getModel()
			}),
			title: i18n('Import'),
			width: '80%',
			height: '80%',
			layout: 'fit',
			maximizable: true,
			closeAction: 'destroy'

		})
			.show();
	}
});
