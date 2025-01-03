Ext.define('Limas.Data.Store.CurrencyStore', {
	extend: 'Ext.data.Store',
	storeId: 'CurrencyStore',
	alias: 'store.CurrencyStore',
	autoLoad: true,
	fields: ['code', 'name', 'symbol'],

	constructor: function () {
		this.callParent(arguments);

		this.setProxy({
			url: Limas.getBasePath() + '/api/currencies',
			type: 'ajax',
			reader: {
				type: 'json',
				rootProperty: ''
			}
		});
	}
});
