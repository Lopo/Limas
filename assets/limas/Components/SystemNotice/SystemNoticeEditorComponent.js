Ext.define('Limas.SystemNoticeEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.SystemNoticeEditorComponent',
	navigationClass: 'Limas.SystemNoticeGrid',
	editorClass: 'Limas.SystemNoticeEditor',
	newItemText: i18n('New System Notice'),
	model: 'Limas.Entity.SystemNotice',
	titleProperty: 'title',
	initComponent: function () {
		this.createStore({
			filters: [
				{
					property: 'acknowledged',
					operator: '=',
					value: false
				}
			],
			sorters: [
				{
					property: 'date',
					direction: 'DESC'
				},
			]
		});

		this.callParent();
	},
	statics: {
		iconCls: 'fugue-icon service-bell',
		title: i18n('System Notices'),
		closable: true,
		menuPath: [{text: i18n('View')}]
	}
});
