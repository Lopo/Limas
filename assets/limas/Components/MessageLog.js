Ext.define('Limas.MessageLog', {
	extend: 'Limas.BaseGrid',
	store: {
		model: 'Limas.Message'
	},
	columns: [
		{header: i18n('Message'), dataIndex: 'message', flex: 1},
		{header: i18n('Date'), dataIndex: 'date', width: 300},
		{header: i18n('Severity'), dataIndex: 'severity'}
	],
	proxy: {
		type: 'memory',
		reader: {
			type: 'json',
			root: 'items'
		}
	},
	sorters: [{
		property: 'date',
		direction: 'DESC'
	}]
});
