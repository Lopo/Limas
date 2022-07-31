Ext.define('Limas.Components.Grid.GridPresetButton', {
	extend: 'Ext.button.Button',

	iconCls: 'fugue-icon folder-open-table',
	tooltip: i18n('Choose preset…'),
	overflowText: i18n('Choose preset…'),

	grid: null,

	initComponent: function () {
		this.menu = Ext.create('Ext.ux.menu.StoreMenu', {
			model: 'Limas.Entity.GridPreset',
			nameField: 'name',
			items: [{
				text: i18n('Default'),
				iconCls: 'fugue-icon inbox-table',
				default: true
			}],
			offset: 2,
			listeners: {
				click: this.onPresetSelect,
				scope: this
			}
		});
		this.callParent(arguments);

		if (this.grid !== null) {
			this.setGrid(this.grid);
		}
	},
	setGrid: function (grid) {
		this.menu.store.setFilters();
		this.menu.store.addFilter({
			property: 'grid',
			operator: '=',
			value: grid.$className
		});

		this.grid = grid;
	},
	onPresetSelect: function (menu, item) {
		if (item.default) {
			this.grid.reconfigure(this.grid.store, this.grid.getDefaultColumnConfiguration());
			return;
		}

		let matchedIndex = this.menu.store.findExact('name', item.text);
		if (matchedIndex !== -1) {
			this.grid.reconfigure(this.grid.store, Ext.decode(this.menu.store.getAt(matchedIndex).get('configuration')));
		}
	}
});
