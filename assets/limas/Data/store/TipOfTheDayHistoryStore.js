Ext.define('Limas.Data.store.TipOfTheDayHistoryStore', {
	extend: 'Ext.data.Store',

	storeId: 'TipOfTheDayHistoryStore',
	autoLoad: true,
	model: 'Limas.Entity.TipOfTheDayHistory',

	pageSize: 99999999
});
