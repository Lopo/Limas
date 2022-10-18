Ext.define('Limas.Data.store.TipOfTheDayStore', {
	extend: 'Ext.data.Store',
	storeId: 'TipOfTheDayStore',
	autoLoad: true,
	model: 'Limas.Entity.TipOfTheDay',

	pageSize: 99999999
});
