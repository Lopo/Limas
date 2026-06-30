/**
 * Exports a grid with all visible fields and rows
 */
Ext.define('Limas.Exporter.GridExporter', {
	constructor: function (gridPanel, format, extension) {
		this.gridPanel = gridPanel;
		this.format = format;
		this.extension = extension;
	},
	exportGrid: function () {
		let columns = this.gridPanel.getColumns(),
			store = this.gridPanel.getStore(),
			records = store.getData(),
			record, i, j, value, column, fieldValue,
			rows = [], rowValues = [];

		for (i = 0; i < columns.length; i++) {
			if (!columns[i].isHidden()) {
				rowValues.push(Ext.util.Format.stripTags(columns[i].text));
			}
		}

		rows.push(rowValues);

		for (i = 0; i < records.length; i++) {
			rowValues = [];
			record = records.getAt(i);

			for (j = 0; j < columns.length; j++) {
				column = columns[j];
				fieldValue = record.get(column.dataIndex);
				if (column.renderer && column.renderer.call) {
					value = column.renderer.call(
						column.usingDefaultRenderer ? column : column.scope || this.gridPanel,
						fieldValue,
						null,
						record,
						i,
						j,
						store,
						this.gridPanel.getView()
					);
				} else {
					value = fieldValue;
				}

				if (!column.isHidden()) {
					// Strip ASCII control chars (Excel chokes on them) but
					// PRESERVE unicode — distributor part descriptions often
					// carry Cyrillic / CJK / accented chars. Keep TAB (0x09),
					// LF (0x0A), CR (0x0D) since CSV/XLSX cell content
					// legitimately uses those (port + fix of PartKeepr commit
					// 2e1c8fa6; their `[^\x1F-\x7D]` stripped Unicode too).
					let cleaned = typeof value === 'string'
						? value.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '')
						: value;
					rowValues.push(Ext.util.Format.stripTags(cleaned));
				}
			}

			rows.push(rowValues);
		}

		let options = {
			headers: {}
		};

		options.headers['Accept'] = this.format;
		options.jsonData = rows;
		options.method = 'POST';
		//this.down("#formatSelector").getValue().get("mimetype");
		options.url = Limas.getBasePath() + '/api/export';
		options.callback = Ext.bind(this.onExportSuccessful, this);
		Ext.Ajax.request(options);
	},
	/**
	 * Callback for when the export is complete. Creates a client-side blob object and forces download of it.
	 */
	onExportSuccessful: function (options, success, response) {
		let blob = new Blob([response.responseText], {type: this.format});
		saveAs(blob, 'export.' + this.extension);
	}
});
