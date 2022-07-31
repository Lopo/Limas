Ext.define('Limas.data.store.UserProviderStore', {
	extend: 'Ext.data.Store',

	storeId: 'UserProviderStore',
	autoLoad: true,
	model: 'Limas.Entity.UserProvider',

	pageSize: 99999999
});
