/**
 * Flips the standard EditorComponent layout: instead of narrow list (west) +
 * wide editor (center), the grid is `center` (full window width) and the
 * form lives in a `east` collapsible panel that opens on edit. Same UX as
 * the main Parts list — most operations happen in the grid (inline edit,
 * bulk merge), the form is occasional.
 */
/**
 * Flips the standard EditorComponent layout: grid is `center` (full window
 * width), form lives in a `east` collapsible panel. Only one record edited
 * at a time — switching rows swaps the form in-place via card layout, no
 * tabs (the standard EditorComponent uses tabs to allow concurrent edits
 * but for this grid-heavy admin view it's just noise).
 */
Ext.define('Limas.ParameterAliasEditorComponent', {
	extend: 'Limas.EditorComponent',
	alias: 'widget.ParameterAliasEditorComponent',
	navigationClass: 'Limas.ParameterAliasGrid',
	editorClass: 'Limas.ParameterAliasEditor',
	newItemText: i18n('New Parameter Alias'),
	model: 'Limas.Entity.ParameterAlias',
	// Drive the east-panel header text + delete-confirm message off this
	// field. ParameterAlias has no `name` (the EditorComponent default).
	titleProperty: 'rawName',

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

		// Single-card editor host. NOT a tab panel — only one record is
		// ever being edited from this admin screen, so a tab UI just adds
		// noise. Adding a new editor removes the previous one.
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

	/**
	 * Card-swap behaviour. EditorComponent base uses tabPanel.add() expecting
	 * a TabPanel; we emulate the same API on a plain Panel by clearing the
	 * previous item before adding the new one. The base's findEditor still
	 * works because it iterates `editorTabPanel.items`.
	 */
	startEdit: function (id) {
		let existing = this.findEditor(id);
		if (existing !== null) {
			return;   // already showing this record
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
		title: i18n('Parameter Aliases'),
		closable: true,
		menuPath: [{text: i18n('Edit')}]
	}
});
