Ext.define('Limas.StorageLocationEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.StorageLocationEditorComponent',
	navigationClass: 'Limas.StorageLocationNavigation',
	editorClass: 'Limas.StorageLocationEditor',
	newItemText: i18n('New Storage Location'),
	model: 'Limas.Entity.StorageLocation',
	initComponent: function () {
		this.createStore({
			sorters: [
				{
					property: 'category.categoryPath',
					direction: 'ASC'
				}, {
					property: 'name',
					direction: 'ASC'
				}
			],
			groupField: 'categoryPath'
		});

		this.callParent();
	},
	statics: {
		iconCls: 'fugue-icon wooden-box',
		title: i18n('Storage Locations'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
