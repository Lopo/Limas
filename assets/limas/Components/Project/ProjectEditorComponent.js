Ext.define('Limas.ProjectEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.ProjectEditorComponent',
	navigationClass: 'Limas.ProjectGrid',
	editorClass: 'Limas.ProjectEditor',
	newItemText: i18n('New Project'),
	model: 'Limas.Entity.Project',
	initComponent: function () {
		this.createStore({
			sorters: [
				{
					property: 'name',
					direction: 'ASC'
				}
			]
		});

		this.callParent();
	},
	statics: {
		iconCls: 'fugue-icon drill',
		title: i18n('Projects'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
