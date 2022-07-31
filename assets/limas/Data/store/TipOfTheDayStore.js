Ext.define('Limas.data.store.TipOfTheDayStore', {
	extend: 'Ext.data.Store',

	storeId: 'TipOfTheDayStore',
	autoLoad: true,
	model: 'Limas.Entity.TipOfTheDay',

	pageSize: 99999999
});
