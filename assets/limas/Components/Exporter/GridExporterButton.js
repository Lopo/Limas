Ext.define('Limas.Exporter.GridExporterButton', {
	extend: 'Ext.button.Button',

	genericExporter: true,

	initComponent: function () {
		this.menu = [
			{
				text: i18n('Export Grid'),
				menu: [
					{
						text: i18n('CSV'),
						handler: 'onCSVExport',
						scope: this
					}, {
						text: i18n('Excel 2007 and later'),
						handler: 'onExcelExport',
						scope: this
					}
				]
			}
		];

		if (this.genericExporter) {
			this.menu.push({
				text: i18n('Custom Export…'),
				handler: 'onCustomExport',
				scope: this
			});
		}
		this.callParent(arguments);
	},
	onCSVExport: function () {
		Ext.create('Limas.Exporter.GridExporter', this.up('gridpanel'), 'text/comma-separated-values', 'csv')
			.exportGrid();
	},
	onExcelExport: function () {
		Ext.create('Limas.Exporter.GridExporter', this.up('gridpanel'), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx')
			.exportGrid();
	},
	onCustomExport: function () {
		Ext.create('Ext.window.Window', {
			items: Ext.create('Limas.Exporter.Exporter', {
				model: this.up('gridpanel').getStore().getModel()
			}),
			title: i18n('Export'),
			width: '80%',
			height: '80%',
			layout: 'fit',
			maximizable: true,
			closeAction: 'destroy'
		})
			.show();
	}
});
