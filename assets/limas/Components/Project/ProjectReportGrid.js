Ext.define('Limas.Components.Project.ProjectReportGrid', {
	extend: 'Limas.BaseGrid',
	alias: 'widget.ProjectReportGrid',

	reference: 'ProjectReportGrid',

	columns: [
		{header: i18n('Name'), dataIndex: 'name', flex: 2},
		{header: i18n('Created'), dataIndex: 'createDateTime', flex: 1, xtype: 'datecolumn'}
	],
	automaticPageSize: false,
	enableEditing: false,
	viewModel: {},
	store: {
		autoLoad: true,
		autoSync: false,
		remoteFilter: true,
		remoteSort: true,
		pageSize: 10,
		model: 'Limas.Entity.Report',
		filters: [{
			property: 'name',
			operator: '!=',
			value: ''
		}]
	},
	bbar: {
		xtype: 'pagingtoolbar',
		itemId: 'pager',
		items: ['-', {
			xtype: 'button',
			text: i18n('Load Report'),
			iconCls: 'fugue-icon notification-counter',
			bind: {
				disabled: '{!ProjectReportGrid.selection}'
			},
			itemId: 'loadReportButton'
		}, {
			xtype: 'button',
			text: i18n('Delete Report'),
			bind: {
				disabled: '{!ProjectReportGrid.selection}'
			},
			iconCls: 'fugue-icon minus-circle',
			itemId: 'deleteReportButton'
		}]
	}
});
