Ext.define('Limas.BatchJobEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.BatchJobEditorComponent',
	navigationClass: 'Limas.BatchJobGrid',
	editorClass: 'Limas.BatchJobEditor',
	newItemText: i18n('New Batch Job'),
	model: 'Limas.Entity.BatchJob',
	initComponent: function () {
		this.createStore({
			sorters: [{
				property: 'name',
				direction: 'ASC'
			}]
		});

		this.callParent();
	},
	statics: {
		iconCls: 'fugue-icon task',
		title: i18n('Batch Jobs'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
