Ext.define('Limas.Data.store.UserPreferenceStore', {
	extend: 'Ext.data.Store',

	storeId: 'UserPreferenceStore',
	autoLoad: false,
	model: 'Limas.Entity.UserPreference',

	pageSize: 99999999
});
