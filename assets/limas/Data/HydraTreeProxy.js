Ext.define('Limas.Data.HydraTreeProxy', {
	extend: 'Limas.Data.HydraProxy',
	alias: 'proxy.HydraTree',

	actionMethods: {
		create: 'POST',
		read: 'GET',
		update: 'PATCH',
		destroy: 'DELETE'
	}
});
