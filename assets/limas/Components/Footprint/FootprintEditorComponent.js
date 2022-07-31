Ext.define('Limas.FootprintEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.FootprintEditorComponent',
	navigationClass: 'Limas.FootprintNavigation',
	editorClass: 'Limas.FootprintEditor',
	newItemText: i18n('New Footprint'),
	model: 'Limas.Entity.Footprint',
	initComponent: function () {
		this.createStore({
			sorters: [{
				property: 'category.categoryPath',
				direction: 'ASC'
			}, {
				property: 'name',
				direction: 'ASC'
			}],
			groupField: 'categoryPath'
		});

		this.callParent();
	},
	statics: {
		iconCls: 'fugue-icon fingerprint',
		title: i18n('Footprints'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
