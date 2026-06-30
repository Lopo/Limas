/**
 * Toolbar dropdown for picking / saving / managing GridPreset rows for the
 * current grid. Presets capture column layout and active store filters; the
 * Limas.Components.Grid.GridPresetState helper does the serialization.
 *
 * Builds its own menu manually instead of going through Ext.ux.menu.StoreMenu —
 * StoreMenu copied `record.data.id` straight onto the menu item config, and
 * Ext rejects "1" as a component id, blowing up the first time the user
 * actually saved a preset.
 */
Ext.define('Limas.Components.Grid.GridPresetButton', {
	extend: 'Ext.button.Button',

	iconCls: 'fugue-icon folder-open-table',
	tooltip: i18n('Choose preset…'),
	overflowText: i18n('Choose preset…'),

	grid: null,
	presetStore: null,

	initComponent: function () {
		this.presetStore = Ext.create('Ext.data.Store', {
			model: 'Limas.Entity.GridPreset',
			autoLoad: false
		});

		this.menu = Ext.create('Ext.menu.Menu');
		this.presetStore.on('load', this.rebuildMenu, this);
		// Auto-apply runs ONCE after first load (cleared with this.didAutoApply).
		// Subsequent loads (e.g. after Save / Manage dialogs close) shouldn't
		// reset the user's current preset selection.
		this.didAutoApply = false;
		this.presetStore.on('load', this.maybeAutoApplyDefault, this);

		this.callParent(arguments);

		if (this.grid !== null) {
			this.setGrid(this.grid);
		}
	},

	maybeAutoApplyDefault: function () {
		if (this.didAutoApply) {
			return;
		}
		this.didAutoApply = true;
		let defaultRec = this.presetStore.findRecord('gridDefault', true, 0, false, false, true);
		if (defaultRec) {
			Limas.Components.Grid.GridPresetState.apply(this.grid, defaultRec.get('configuration'));
		}
	},

	setGrid: function (grid) {
		this.grid = grid;
		this.presetStore.clearFilter();
		this.presetStore.addFilter({
			property: 'grid',
			operator: '=',
			value: grid.$className
		});
		this.presetStore.load();
	},

	rebuildMenu: function () {
		this.menu.removeAll();
		this.menu.add([
			{
				text: i18n('Save current as preset…'),
				iconCls: 'fugue-icon disk--plus',
				handler: Ext.bind(this.onSavePreset, this)
			},
			{
				text: i18n('Manage presets…'),
				iconCls: 'fugue-icon gear',
				handler: Ext.bind(this.onManagePresets, this)
			},
			{xtype: 'menuseparator'},
			{
				text: i18n('Default'),
				iconCls: 'fugue-icon inbox-table',
				handler: Ext.bind(this.onDefaultSelect, this)
			}
		]);

		let records = this.presetStore.getRange();
		if (records.length > 0) {
			this.menu.add({xtype: 'menuseparator'});
		}
		records.forEach(function (rec) {
			let label = rec.get('name') + (rec.get('gridDefault') ? ' ★' : '');
			this.menu.add({
				text: label,
				iconCls: 'fugue-icon table',
				presetRecord: rec,
				handler: Ext.bind(this.onPresetSelect, this, [rec])
			});
		}, this);
	},

	onDefaultSelect: function () {
		Limas.Components.Grid.GridPresetState.applyDefault(this.grid);
	},
	onPresetSelect: function (rec) {
		Limas.Components.Grid.GridPresetState.apply(this.grid, rec.get('configuration'));
	},

	onSavePreset: function () {
		Ext.create('Limas.Components.Grid.SaveGridPresetDialog', {
			grid: this.grid,
			presetStore: this.presetStore
		}).show();
	},
	onManagePresets: function () {
		Ext.create('Limas.Components.Grid.ManageGridPresetsDialog', {
			grid: this.grid,
			presetStore: this.presetStore
		}).show();
	}
});
