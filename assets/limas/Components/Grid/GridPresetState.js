/**
 * Capture / restore helpers for GridPreset configuration JSON
 *
 * New presets save as {columns: [...], filters: [...]}. Legacy presets
 * (plain columns array) are still loadable — apply() detects the shape.
 *
 * `columns` mirrors the field list Limas's ColumnConfigurator/Panel
 * already uses (dataIndex, text, hidden, width/flex, tooltip, renderers).
 * `filters` is a list of Limas.util.Filter.getState() snapshots — applied
 * via setFilters() to wipe + restore the store filter set.
 */
Ext.define('Limas.Components.Grid.GridPresetState', {
	singleton: true,

	capture: function (grid) {
		return {
			columns: this.captureColumns(grid),
			filters: this.captureFilters(grid)
		};
	},

	captureColumns: function (grid) {
		let cols = grid.getColumns();
		let out = [];
		// Include `align` so a right-aligned numeric/currency column doesn't
		// silently regress to left after a round-trip; minWidth/maxWidth keep
		// resize bounds; sortable for PK #1217 (kept flag for cols that have
		// one — the real "Param values aren't backend-sortable" half stays a
		// separate ticket). NOT capturing xtype on purpose — specialised
		// columns like actioncolumn need their items[] config to function and
		// that doesn't round-trip; treating restored columns as plain
		// gridcolumn matches what the existing ColumnConfigurator/Panel does.
		let fields = ['dataIndex', 'text', 'hidden', 'flex', 'width', 'minWidth', 'maxWidth', 'align', 'tooltip', 'sortable', 'sortParam'];
		// Mirror ColumnConfigurator/Panel.js: skip the metapart row-expander
		// "column" since it isn't a real column the user configures.
		let startIdx = grid.findPlugin && grid.findPlugin('metapartrowexpander') ? 1 : 0;
		for (let i = startIdx; i < cols.length; i++) {
			let col = cols[i];
			let entry = {};
			fields.forEach(function (f) {
				if (col[f] !== undefined) entry[f] = col[f];
			});
			// flex and width are mutually exclusive on round-trip — keep
			// whichever the column actually used.
			if (entry.flex > 0) delete entry.width; else delete entry.flex;
			if (Ext.isArray(col.renderers)) {
				entry.renderers = col.renderers.map(function (r) {
					return {
						rtype: r.rtype,
						rendererConfig: r.rendererConfig
					};
				});
			}
			out.push(entry);
		}
		return out;
	},

	captureFilters: function (grid) {
		if (!grid.store || !grid.store.getFilters) return [];
		let filters = grid.store.getFilters().items || [];
		let out = [];
		filters.forEach(function (f) {
			if (typeof f.getState === 'function') {
				out.push(f.getState());
			}
		});
		return out;
	},

	/**
	 * Apply a stored configuration to the grid. Accepts both new-shape
	 * objects and legacy column-only arrays.
	 */
	apply: function (grid, configuration) {
		let decoded = (typeof configuration === 'string') ? Ext.decode(configuration) : configuration;
		let columns = decoded;
		let filters = [];
		if (decoded && !Ext.isArray(decoded) && typeof decoded === 'object') {
			columns = decoded.columns || [];
			filters = decoded.filters || [];
		}
		columns = (columns || []).map(this.materializeColumn, this);
		grid.reconfigure(grid.store, columns);
		if (grid.store && grid.store.getFilters) {
			let liveFilters = filters.map(function (state) {
				return Ext.create('Limas.util.Filter', state);
			});
			grid.store.setFilters(liveFilters);
		}
	},

	/**
	 * Augment a stored column config with derived runtime properties.
	 * Param Renderer columns get dataIndex `paramValues` (a real model
	 * field — the renderer plucks the requested name off the map),
	 * sortParam `paramValues.<name>` (consumed by AdvancedSearchFilter to
	 * JOIN PartParameter), and `sortable: true`. PK #1217 (b).
	 *
	 * Earlier attempt set dataIndex to `paramValues.<name>` directly, but
	 * ExtJS treats dot-notation in dataIndex as "association.subfield" and
	 * silently skips data-cell rendering when the head isn't a registered
	 * association — the column's header showed up but every data cell went
	 * missing. Decoupling render-time dataIndex from sort-time sortParam
	 * keeps both halves working.
	 *
	 * Existing dataIndex / sortable / sortParam on the saved config win.
	 */
	materializeColumn: function (col) {
		let out = Ext.apply({}, col);
		if (Ext.isArray(out.renderers)) {
			let paramRenderer = out.renderers.find(function (r) {
				return r && r.rtype === 'partParameter' && r.rendererConfig && r.rendererConfig.parameterName;
			});
			if (paramRenderer) {
				let name = paramRenderer.rendererConfig.parameterName;
				// Overwrite a previously-saved bad dataIndex (older preset versions saved 'paramValues.<name>' which doesn't render)
				if (!out.dataIndex || out.dataIndex === '' || (typeof out.dataIndex === 'string' && out.dataIndex.indexOf('paramValues.') === 0)) {
					out.dataIndex = 'paramValues';
				}
				if (!out.sortParam) {
					out.sortParam = 'paramValues.' + name;
				}
				if (out.sortable === undefined) {
					out.sortable = true;
				}
				// ExtJS Column.getSortParam in this version just returns
				// `this.dataIndex` — the `sortParam` config it documents is
				// ignored. Override per-instance with a closure that returns
				// the path AdvancedSearchFilter expects.
				out.getSortParam = function () {
					return 'paramValues.' + name;
				};
			}
		}
		return out;
	},

	applyDefault: function (grid) {
		grid.reconfigure(grid.store, grid.getDefaultColumnConfiguration());
		if (grid.store && grid.store.getFilters) {
			grid.store.setFilters([]);
		}
	}
});
