Ext.define('Limas.DistributorEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.DistributorEditorComponent',
	navigationClass: 'Limas.DistributorGrid',
	editorClass: 'Limas.DistributorEditor',
	newItemText: i18n('New Distributor'),
	model: 'Limas.Entity.Distributor',
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
		iconCls: 'web-icon lorry',
		title: i18n('Distributors'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
