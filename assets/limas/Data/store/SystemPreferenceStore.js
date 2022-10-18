Ext.define('Limas.Data.store.SystemPreferenceStore', {
	extend: 'Ext.data.Store',
	storeId: 'SystemPreferenceStore',
	autoLoad: true,
	model: 'Limas.Entity.SystemPreference',

	pageSize: 99999999
});
