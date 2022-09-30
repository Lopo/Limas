Ext.define('Limas.Entity.ProjectReportList', {
	extend: 'Limas.Data.HydraModel',
	alias: 'schema.Limas.Entity.ProjectReportList',

	idProperty: '@id',

	fields: [
		{name: '@id', type: 'string'},
		{name: 'name', type: 'string'},
		{name: 'quantity', type: 'integer'},
		{name: 'description', type: 'string'},
		{
			name: 'user',
			reference: 'Limas.Entity.User'
		}
	],

	hasMany: [
		{
			name: 'parts',
			associationKey: 'parts',
			model: 'Limas.Entity.ProjectPart'
		},
		{
			name: 'attachments',
			associationKey: 'attachments',
			model: 'Limas.Entity.ProjectAttachment'
		}
	],

	proxy: {
		type: 'Hydra',
		url: '/api/projects'
	}
});
