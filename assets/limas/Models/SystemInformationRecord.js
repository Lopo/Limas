Ext.define('Limas.SystemInformationRecord', {
	extend: 'Limas.Data.HydraModel',
	alias: 'schema.Limas.SystemInformationRecord',

	fields: [
		{name: 'name', type: 'string'},
		{name: 'category', type: 'string'},
		{name: 'value', type: 'string'},
	],

	proxy: {
		type: 'Hydra',
		url: '/api/system_information'
	}
});
