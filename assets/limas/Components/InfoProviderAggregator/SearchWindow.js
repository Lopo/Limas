Ext.define('Limas.Components.InfoProviderAggregator.SearchWindow', {
	extend: 'Ext.window.Window',
	title: i18n('Info Provider Aggregator'),
	iconCls: 'fugue-icon globe-network',
	width: 1280,
	height: 650,
	layout: 'fit',
	modal: true,
	items: [
		{
			xtype: 'infoProviderAggregatorSearchPanel',
			itemId: 'panel'
		}
	],
	initComponent: function () {
		this.callParent(arguments);
		this.down('#panel').on('applied', function () {
			this.fireEvent('applied');
		}, this);
	},
	startSearch: function (query) {
		this.down('#panel').startSearch(query);
	},
	setPart: function (partRecord) {
		this.down('#panel').setPart(partRecord);
	}
});
