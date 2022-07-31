Ext.define('Limas.ProjectRunEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.ProjectRunEditorComponent',
	navigationClass: 'Limas.ProjectRunGrid',
	editorClass: 'Limas.ProjectRunEditor',
	titleProperty: 'project.name',
	model: 'Limas.Entity.ProjectRun',
	initComponent: function () {
		this.createStore({
			sorters: [
				{
					property: 'runDateTime',
					direction: 'DESC'
				}
			]
		});

		this.callParent();
	},
	statics: {
		iconCls: 'fugue-icon drill',
		title: i18n('Project Runs'),
		closable: true,
		menuPath: [{text: i18n('View')}]
	}
});
