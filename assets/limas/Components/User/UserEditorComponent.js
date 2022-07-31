Ext.define('Limas.UserEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.UserEditorComponent',
	navigationClass: 'Limas.UserGrid',
	editorClass: 'Limas.UserEditor',
	newItemText: i18n('New User'),
	deleteMessage: i18n("Do you really wish to delete the user '%s'?"),
	deleteTitle: i18n('Delete User'),

	model: 'Limas.Entity.User',

	titleProperty: 'username',

	initComponent: function () {
		this.createStore({
			sorters: [
				{
					property: 'username',
					direction: 'ASC'
				}
			],
			autoLoad: false
		});

		this.callParent();
	},
	statics: {
		iconCls: 'fugue-icon user',
		title: i18n('Users'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
