/**
 * Aggregator settings dialog: per-source on/off + drag-or-button priority order
 * + merge strategy radio. Persists state to localStorage; SearchPanel reads
 * back on next render and on every query.
 *
 * Caller passes `sources` (full /sources payload), current `enabledSources`
 * map, current `sourceOrder` array, current `mergeStrategy`, and a
 * `defaults` object echoed from the server so "Reset to defaults" matches
 * services.yaml.
 *
 * Fires 'settingschanged' with {enabledSources, sourceOrder, mergeStrategy}
 * when the user clicks OK; cancel just closes the window.
 */
Ext.define('Limas.Components.InfoProviderAggregator.SettingsWindow', {
	extend: 'Ext.window.Window',
	title: i18n('Aggregator Settings'),
	iconCls: 'fugue-icon gear',
	width: 460,
	height: 460,
	layout: 'border',
	modal: true,
	resizable: false,

	sources: null,
	enabledSources: null,
	sourceOrder: null,
	mergeStrategy: 'majority',
	defaults: null,

	initComponent: function () {
		let me = this;

		// Build the per-source row store. Order follows sourceOrder; sources
		// not in sourceOrder (freshly added providers) get appended at the end.
		let rows = [];
		let seen = {};
		(me.sourceOrder || []).forEach(function (name) {
			let s = me.sources.find(s => s.name === name);
			if (s) {
				rows.push(me.rowFor(s));
				seen[name] = true;
			}
		});
		me.sources.forEach(function (s) {
			if (!seen[s.name]) rows.push(me.rowFor(s));
		});

		me.rowsStore = Ext.create('Ext.data.Store', {
			fields: ['name', 'enabled', 'iconCls', 'caps'],
			data: rows
		});

		me.sourceGrid = Ext.create('Ext.grid.Panel', {
			region: 'center',
			store: me.rowsStore,
			hideHeaders: true,
			columnLines: false,
			rowLines: true,
			selModel: {selType: 'rowmodel', mode: 'SINGLE'},
			columns: [
				{
					xtype: 'checkcolumn',
					dataIndex: 'enabled',
					width: 30,
					stopSelection: false
				},
				{
					dataIndex: 'iconCls',
					width: 36,
					align: 'center',
					renderer: function (val) {
						// Sprite is 16x16 with a hard-coded background-position
						// per source name; do NOT override width / height /
						// background-position inline — that would clobber the
						// sprite offset and make two halves of adjacent icons
						// bleed into the cell.
						return '<span class="distributor-icon ' + val + '"></span>';
					}
				},
				{
					dataIndex: 'name',
					flex: 1,
					renderer: function (val, meta, rec) {
						let caps = rec.get('caps') || '';
						return '<b>' + val + '</b>' + (caps ? ' <span class="limas-text-muted" style="font-size:10px">— ' + caps + '</span>' : '');
					}
				}
			],
			tbar: [
				(me.moveUpBtn = Ext.create('Ext.button.Button', {
					text: i18n('Move up'),
					iconCls: 'fugue-icon arrow-090',
					disabled: true,
					handler: me.onMoveUp,
					scope: me
				})),
				(me.moveDownBtn = Ext.create('Ext.button.Button', {
					text: i18n('Move down'),
					iconCls: 'fugue-icon arrow-270',
					disabled: true,
					handler: me.onMoveDown,
					scope: me
				})),
				'->',
				{
					text: i18n('Reset to defaults'),
					tooltip: i18n('Restore server-default priority order and merge strategy. Enabled set is left as-is.'),
					iconCls: 'fugue-icon arrow-circle-double',
					handler: me.onResetDefaults,
					scope: me
				}
			]
		});

		me.strategyGroup = Ext.create('Ext.form.RadioGroup', {
			region: 'south',
			fieldLabel: i18n('Merge strategy'),
			labelWidth: 110,
			height: 86,
			padding: '6 8',
			columns: 1,
			vertical: true,
			items: [
				{
					boxLabel: i18n('Majority — pick the most-voted value, hierarchy breaks ties'),
					name: 'mergeStrategy',
					inputValue: 'majority',
					checked: me.mergeStrategy !== 'hierarchy'
				},
				{
					boxLabel: i18n('Hierarchy — always pick the highest-priority source that reported a value'),
					name: 'mergeStrategy',
					inputValue: 'hierarchy',
					checked: me.mergeStrategy === 'hierarchy'
				}
			]
		});

		// Enable/disable Move up/down based on selection position. No selection
		// → both off; top row → only down; bottom row → only up; middle → both.
		me.sourceGrid.on('selectionchange', me.refreshMoveButtons, me);
		me.rowsStore.on('datachanged', me.refreshMoveButtons, me);

		me.items = [me.sourceGrid, me.strategyGroup];
		me.buttons = [
			{
				text: i18n('OK'),
				iconCls: 'fugue-icon tick',
				handler: me.onOk,
				scope: me
			},
			{
				text: i18n('Cancel'),
				iconCls: 'fugue-icon cross',
				handler: function () {
					me.close();
				}
			}
		];

		me.callParent(arguments);
	},

	rowFor: function (s) {
		return {
			name: s.name,
			enabled: this.enabledSources[s.name] !== false,
			iconCls: s.name,
			caps: (s.capabilities || []).join(', ').toLowerCase()
		};
	},

	refreshMoveButtons: function () {
		let sel = this.sourceGrid.getSelectionModel().getSelection()[0];
		if (!sel) {
			this.moveUpBtn.setDisabled(true);
			this.moveDownBtn.setDisabled(true);
			return;
		}
		let idx = this.rowsStore.indexOf(sel);
		this.moveUpBtn.setDisabled(idx <= 0);
		this.moveDownBtn.setDisabled(idx >= this.rowsStore.getCount() - 1);
	},

	onMoveUp: function () {
		let sel = this.sourceGrid.getSelectionModel().getSelection()[0];
		if (!sel) return;
		let idx = this.rowsStore.indexOf(sel);
		if (idx <= 0) return;
		this.rowsStore.remove(sel);
		this.rowsStore.insert(idx - 1, sel);
		this.sourceGrid.getSelectionModel().select(sel);
	},

	onMoveDown: function () {
		let sel = this.sourceGrid.getSelectionModel().getSelection()[0];
		if (!sel) return;
		let idx = this.rowsStore.indexOf(sel);
		if (idx < 0 || idx >= this.rowsStore.getCount() - 1) return;
		this.rowsStore.remove(sel);
		this.rowsStore.insert(idx + 1, sel);
		this.sourceGrid.getSelectionModel().select(sel);
	},

	onResetDefaults: function () {
		// Re-order rows per defaults.priority — sources not in defaults stay
		// after defaulted ones in their current relative order. Strategy
		// flips back to default. Enabled checks left untouched (user toggle
		// is independent of order/strategy).
		let priority = (this.defaults && this.defaults.priority) || [];
		let byName = {};
		this.rowsStore.each(function (r) {
			byName[r.get('name')] = r;
		});
		let ordered = [];
		let seen = {};
		priority.forEach(function (name) {
			if (byName[name]) {
				ordered.push(byName[name]);
				seen[name] = true;
			}
		});
		this.rowsStore.each(function (r) {
			if (!seen[r.get('name')]) ordered.push(r);
		});
		this.rowsStore.loadData(ordered.map(r => r.getData()));
		let strat = (this.defaults && this.defaults.mergeStrategy) || 'majority';
		this.strategyGroup.setValue({mergeStrategy: strat});
	},

	onOk: function () {
		// Snapshot current grid state, then fire change event with the new
		// triple. SearchPanel owns the actual persistence + chip re-render.
		let enabled = {};
		let order = [];
		this.rowsStore.each(function (r) {
			let n = r.get('name');
			enabled[n] = r.get('enabled') !== false;
			order.push(n);
		});
		let stratValue = this.strategyGroup.getValue();
		let strategy = (stratValue && stratValue.mergeStrategy) || 'majority';
		this.fireEvent('settingschanged', {
			enabledSources: enabled,
			sourceOrder: order,
			mergeStrategy: strategy
		});
		this.close();
	}
});
