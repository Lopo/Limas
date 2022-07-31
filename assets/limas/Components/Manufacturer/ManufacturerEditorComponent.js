Ext.define('Limas.ManufacturerEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.ManufacturerEditorComponent',
	navigationClass: 'Limas.ManufacturerGrid',
	editorClass: 'Limas.ManufacturerEditor',
	newItemText: i18n('New Manufacturer'),
	model: 'Limas.Entity.Manufacturer',
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
		iconCls: 'fugue-icon building',
		title: i18n('Manufacturers'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
