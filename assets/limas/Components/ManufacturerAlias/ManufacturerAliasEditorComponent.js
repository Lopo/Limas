/**
 * Admin host for the ManufacturerAlias grid. Same layout as
 * FootprintAliasEditorComponent / ParameterAliasEditorComponent: grid in
 * center, narrow east form for the rare per-row deep edit. Most operations
 * happen inline in the grid.
 */
Ext.define('Limas.ManufacturerAliasEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.ManufacturerAliasEditorComponent',
	navigationClass: 'Limas.ManufacturerAliasGrid',
	editorClass: 'Limas.ManufacturerAliasEditor',
	newItemText: i18n('New Manufacturer Alias'),
	model: 'Limas.Entity.ManufacturerAlias',
	titleProperty: 'alias',

	initComponent: function () {
		this.createStore({
			autoLoad: true,
			pageSize: 50,
			sorters: [
				{property: 'verified', direction: 'ASC'},
				{property: 'usageCount', direction: 'DESC'}
			]
		});

		this.navigation = Ext.create(this.navigationClass, {
			region: 'center',
			split: true,
			store: this.store,
			titleProperty: this.titleProperty
		});
		this.navigation.on('itemAdd', this.newRecord, this);
		this.navigation.on('itemDelete', this.confirmDelete, this);
		this.navigation.on('itemEdit', this.startEdit, this);

		this.editorTabPanel = Ext.create('Ext.panel.Panel', {
			region: 'east',
			width: 420,
			collapsed: true,
			collapsible: true,
			titleCollapse: true,
			animCollapse: false,
			floatable: false,
			split: true,
			title: i18n('Edit Alias'),
			layout: 'fit'
		});

		this.items = [this.navigation, this.editorTabPanel];

		Ext.panel.Panel.prototype.initComponent.call(this);
	},

	startEdit: function (id) {
		let existing = this.findEditor(id);
		if (existing !== null) {
			return;
		}
		this.editorTabPanel.removeAll();
		this.callParent(arguments);
		if (this.editorTabPanel.collapsed) {
			this.editorTabPanel.expand();
		}
	},
	newRecord: function (defaults) {
		this.editorTabPanel.removeAll();
		this.callParent(arguments);
		if (this.editorTabPanel.collapsed) {
			this.editorTabPanel.expand();
		}
	},

	statics: {
		iconCls: 'fugue-icon table',
		title: i18n('Manufacturer Aliases'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
