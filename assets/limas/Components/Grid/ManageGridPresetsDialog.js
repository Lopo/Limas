/**
 * Manage saved presets for a given grid — rename inline, mark default,
 * delete. Opened from the GridPresetButton menu's "Manage…" entry.
 */
Ext.define('Limas.Components.Grid.ManageGridPresetsDialog', {
	extend: 'Ext.window.Window',
	alias: 'widget.ManageGridPresetsDialog',

	title: i18n('Manage grid presets'),
	width: 560,
	height: 360,
	modal: true,
	layout: 'fit',

	grid: null,
	presetStore: null,

	initComponent: function () {
		this.managedStore = Ext.create('Ext.data.Store', {
			model: 'Limas.Entity.GridPreset',
			autoLoad: true,
			filters: [{property: 'grid', value: this.grid.$className}]
		});

		this.presetGrid = Ext.create('Ext.grid.Panel', {
			border: false,
			store: this.managedStore,
			plugins: [{ptype: 'cellediting', clicksToEdit: 2}],
			columns: [
				{
					header: i18n('Name'), dataIndex: 'name', flex: 1,
					editor: {xtype: 'textfield', allowBlank: false}
				},
				{
					header: i18n('Default'), dataIndex: 'gridDefault', width: 80, align: 'center',
					renderer: function (v) {
						return v ? '<span class="limas-text-success">✓</span>' : '';
					}
				},
				{
					xtype: 'actioncolumn', width: 30, align: 'center',
					tooltip: i18n('Mark as default'),
					iconCls: 'fugue-icon star',
					handler: Ext.bind(this.onMarkDefault, this)
				},
				{
					xtype: 'actioncolumn', width: 30, align: 'center',
					tooltip: i18n('Delete'),
					iconCls: 'web-icon delete',
					handler: Ext.bind(this.onDelete, this)
				}
			]
		});

		this.items = [this.presetGrid];

		this.dockedItems = [{
			xtype: 'toolbar',
			dock: 'bottom',
			ui: 'footer',
			items: [
				'->',
				{text: i18n('Close'), iconCls: 'web-icon cancel', handler: Ext.bind(this.onClose, this)}
			]
		}];

		this.callParent();
	},

	onMarkDefault: function (grid, rowIndex) {
		let rec = this.managedStore.getAt(rowIndex);
		if (!rec) return;
		// Server-side action clears the previous default + sets this one
		Ext.Ajax.request({
			url: Limas.getBasePath() + rec.get('@id') + '/markAsDefault',
			method: 'PUT',
			success: Ext.bind(function () {
				this.managedStore.load();
			}, this)
		});
	},

	onDelete: function (grid, rowIndex) {
		let rec = this.managedStore.getAt(rowIndex);
		if (!rec) return;
		Ext.Msg.confirm(
			i18n('Delete preset'),
			Ext.String.format(i18n('Delete preset "{0}"?'), Ext.htmlEncode(rec.get('name'))),
			function (btn) {
				if (btn !== 'yes') return;
				rec.erase({
					success: Ext.bind(function () {
						this.managedStore.remove(rec);
					}, this)
				});
			},
			this
		);
	},

	onClose: function () {
		// Reload the caller's preset menu so renames/deletes/default-changes are visible
		if (this.presetStore) {
			this.presetStore.load();
		}
		this.close();
	}
});
