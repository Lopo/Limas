Ext.define('Limas.Components.Widgets.ColumnConfigurator.Panel', {
	extend: 'Ext.panel.Panel',

	layout: 'border',
	grid: null,

	originalColumnConfigurations: [],

	viewModel: {
		data: {
			column: null
		},
		formulas: {
			isFlex: function (get) {
				return get('column.widthMode') === 'flex';
			}
		}
	},

	initComponent: function () {
		this.columnListGrid = Ext.create('Limas.Components.Widgets.ColumnConfigurator.ColumnListGrid', {
			region: 'west',
			width: 400,
			split: true
		});

		this.columnProperties = Ext.create('Limas.Components.Widgets.ColumnConfigurator.ColumnProperties', {
				region: 'center',
				sourceModel: this.grid.getStore().getModel()
			}
		);

		this.items = [
			this.columnListGrid,
			this.columnProperties
		];

		this.callParent();

		this.columnListGrid.on('select', this.onColumnSelect, this);
		this.columnListGrid.getStore().on('add', this.onAdd, this);
		this.down('#restoreDefaults').on('click', this.restoreDefaults, this);
		this.columnListGrid.getStore().on('datachanged', this.preview, this);
		this.columnListGrid.getStore().on('update', this.preview, this);

		this.down('#gridPresetCombo').getStore().addFilter({
			property: 'grid',
			operator: '=',
			value: this.grid.$className
		});

		this.down('#gridPresetCombo').on('selectPreset', this.onPresetSelect, this);
		this.down('#gridPresetCombo').on('markAsDefault', this.onMarkAsDefault, this);
		this.down('#gridPresetCombo').setAdditionalFields([
			{
				fieldName: 'grid',
				value: this.grid.$className
			}
		]);

		this.down('#renderers').on('change', this.preview, this);

		this.autoPreviewTask = new Ext.util.DelayedTask(this.doPreview, this, null, true);
	},
	restoreDefaults: function () {
		if (this.grid instanceof Limas.BaseGrid) {
			this.grid.reconfigure(this.grid.store, this.grid.getDefaultColumnConfiguration());
			this.applyColumnConfigurationFromGrid();
		}
	},
	onMarkAsDefault: function (gridPreset) {
		gridPreset.callPutAction('markAsDefault', {}, Ext.bind(this.onMarkedAsDefault, this));
	},
	onMarkedAsDefault: function () {
		this.down('#gridPresetCombo').getStore().load();
	},
	onPresetSelect: function (configuration) {
		this.grid.reconfigure(this.grid.store, configuration);
		this.applyColumnConfigurationFromGrid();

		this.down('#gridPresetCombo').setConfiguration(configuration);
	},
	onColumnSelect: function (grid, record) {
		this.editData(record);
	},
	onAdd: function (store, records) {
		if (records.length === 1) {
			this.editData(records[0]);
		}
	},
	editData: function (record) {
		this.columnProperties.loadRecord(record);
	},
	preview: function () {
		this.down('#gridPresetCombo').setConfiguration(this.getColumnConfigurations());
		this.autoPreviewTask.delay(200);
	},
	doPreview: function () {
		// materializeColumn wires Param Renderer columns with the right
		// dataIndex + sortable flag so backend ORDER BY paramValues.<name>
		// works regardless of whether the user just edited columns in this
		// dialog or applied a saved preset (PK #1217 (b))
		let cols = this.getColumnConfigurations().map(
			Limas.Components.Grid.GridPresetState.materializeColumn,
			Limas.Components.Grid.GridPresetState
		);
		this.grid.reconfigure(this.grid.store, cols);
	},
	getColumnConfigurations: function () {
		let j, rtype,
			config = {}, columnConfigurations = [], fieldsToCopy = this.getFieldsToCopy(),
			data = this.columnListGrid.getStore().getData();

		for (let i = 0; i < data.getCount(); i++) {
			config = {};
			for (j = 0; j < fieldsToCopy.length; j++) {
				config[fieldsToCopy[j]] = data.getAt(i).get(fieldsToCopy[j]);
			}

			if (data.getAt(i).get('widthMode') === 'flex') {
				delete config.width;
			} else {
				delete config.flex;
			}

			config.renderers = [];

			for (j = 0; j < data.getAt(i).renderers().getCount(); j++) {
				rtype = data.getAt(i).renderers().getAt(j).get('rtype');

				if (typeof (Limas.Components.Grid.Renderers.RendererRegistry.lookupRenderer(rtype)) !== 'undefined') {
					// Most renderers don't carry a configurable payload; the
					// `config` field is then empty string and Ext.decode('')
					// throws. Default to null in that case.
					let rawCfg = data.getAt(i).renderers().getAt(j).get('config');
					config.renderers.push({
						rtype: rtype,
						rendererConfig: (rawCfg !== null && rawCfg !== '') ? Ext.decode(rawCfg) : null
					});
				}
			}
			columnConfigurations.push(config);
		}

		return columnConfigurations;
	},
	applyColumnConfigurationFromGrid: function () {
		let columns = this.grid.getColumns(),
			j,
			columnRecord;
		this.originalColumnConfigurations = [];
		let startColumn = 0,
			columnConfig,
			fieldsToCopy = this.getFieldsToCopy();

		this.columnListGrid.getStore().removeAll();

		// In case we have a row expander which adds an additional column, skip the first column
		if (this.grid.findPlugin('metapartrowexpander')) {
			startColumn++;
		}

		for (let i = startColumn; i < columns.length; i++) {
			columnRecord = Ext.create('Limas.Models.ColumnConfiguration');
			columnConfig = {};

			for (j = 0; j < fieldsToCopy.length; j++) {
				columnRecord.set(fieldsToCopy[j], columns[i][fieldsToCopy[j]]);
				columnConfig[fieldsToCopy[j]] = columns[i][fieldsToCopy[j]];
			}

			if (columnConfig["flex"] > 0) {
				columnRecord.set('widthMode', 'flex');
			} else {
				columnRecord.set('widthMode', 'width');
			}

			columnConfig['renderers'] = columns[i]['renderers'];
			this.originalColumnConfigurations.push(columnConfig);
			columnRecord.set('index', i);

			if (columns[i].renderers instanceof Array) {
				for (j = 0; j < columns[i].renderers.length; j++) {
					columnRecord.renderers().add(Ext.create('Limas.Models.ColumnRendererConfiguration', {
						rtype: columns[i].renderers[j].rtype,
						config: Ext.encode(columns[i].renderers[j].rendererConfig)
					}));
				}
			}

			columnRecord.renderers().on('datachanged', this.preview, this);
			columnRecord.renderers().on('update', this.preview, this);

			this.columnListGrid.getStore().add(columnRecord);
		}
	},
	getFieldsToCopy: function () {
		// align/minWidth/maxWidth round-trip so right-aligned numeric columns
		// don't silently regress to left after Save → Default → Load.
		// sortable/sortParam for PK #1217 — Param Renderer columns need
		// both to survive save→load.
		return ['dataIndex', 'text', 'hidden', 'flex', 'width', 'minWidth', 'maxWidth', 'align', 'tooltip', 'sortable', 'sortParam'];
	}
});
