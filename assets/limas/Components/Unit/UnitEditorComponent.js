Ext.define('Limas.UnitEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.UnitEditorComponent',
	navigationClass: 'Limas.UnitGrid',
	editorClass: 'Limas.UnitEditor',
	newItemText: i18n('New Unit'),
	deleteMessage: i18n("Do you really wish to delete the unit'%s'?"),
	deleteTitle: i18n('Delete Unit'),
	model: 'Limas.Entity.Unit',
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
		iconCls: 'partkeepr-icon unit',
		title: i18n('Units'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
