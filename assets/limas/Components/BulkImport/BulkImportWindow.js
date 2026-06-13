/**
 * Bulk Import dialog — paste a CSV, map columns, watch the worker
 * progress against the aggregator
 *
 * Step 1 (local, no upload):
 *   - User drops a CSV file. FileReader reads it in-browser.
 *   - A small native JS parser produces a preview of the first 5 rows.
 *   - Auto-detect heuristics suggest header-yes/no and column → field
 *     mapping; user can override.
 *
 * Step 2 (POST):
 *   - User picks default Category + Storage (required) and confirms the
 *     mapping. The ORIGINAL file is uploaded multipart with a JSON
 *     `meta` body — backend re-parses with fgetcsv() for accuracy.
 *   - Returns jobId; FE polls /api/bulk-import-jobs/{id} every 2 s and
 *     renders per-row status (success / warning / skipped / ambiguous /
 *     failed) with links to created or existing Parts.
 *
 * Worker is NOT auto-started by the POST — operator runs
 *   php bin/console limas:bulk-import:run <jobId>
 * The dialog shows the command in a banner so they can copy-paste.
 */
Ext.define('Limas.Components.BulkImport.BulkImportWindow', {
	extend: 'Ext.window.Window',
	xtype: 'bulkImportWindow',
	requires: [
		'Ext.layout.container.Card',
		'Ext.form.field.ComboBox',
		'Ext.form.field.Checkbox',
		'Limas.CategoryComboBox',
		'Limas.Widgets.StorageLocationTreeComboBox'
	],

	width: 880,
	height: 640,
	resizable: true,
	layout: 'card',
	modal: true,
	title: i18n('Bulk Import (CSV)'),
	iconCls: 'fugue-icon documents-stack',

	// FE-local state populated in step 1, sent to backend in step 2.
	parsedRows: null, // string[][]  — full parse, sent verbatim back as the uploaded file
	parsedFile: null, // File object — the original; multipart-uploaded on submit
	previewRowCount: 5,
	autoMapping: null, // {mpn, manufacturer?, category?, storage?}  — auto-detected column indexes

	jobId: null,
	pollTimer: null,

	statics: {
		iconCls: 'fugue-icon documents-stack',
		title: i18n('Bulk Import'),
		closable: true,
		// Top-level menu entry: System ➤ Bulk Import. Keeps it visible
		// next to other operator-facing actions (User Preferences, Logout, …).
		menuPath: [{text: i18n('System')}]
	},

	initComponent: function () {
		this.items = [
			this.buildStepMapping(),
			this.buildStepProgress()
		];
		this.callParent(arguments);
	},

	// ──────────────────────────── STEP 1 ────────────────────────────

	buildStepMapping: function () {
		let me = this;

		this.dropArea = Ext.create('Ext.Component', {
			height: 80,
			html: '<div class="limas-dropzone">' +
				'<b>' + i18n('Drop CSV file here') + '</b><br>' +
				'<span style="font-size:11px;">' + i18n('or click to pick — handled locally, nothing uploaded yet') + '</span>' +
				'</div>',
			listeners: {
				render: function (cmp) {
					let el = cmp.getEl().dom;
					let zone = el.querySelector('.limas-dropzone') || el;
					el.addEventListener('click', () => me.fileInput.click());
					el.addEventListener('dragover', (e) => {
						e.preventDefault();
						zone.classList.add('limas-dropzone-hover');
					});
					el.addEventListener('dragleave', () => {
						zone.classList.remove('limas-dropzone-hover');
					});
					el.addEventListener('drop', function (e) {
						e.preventDefault();
						zone.classList.remove('limas-dropzone-hover');
						if (e.dataTransfer.files.length > 0) me.onFilePicked(e.dataTransfer.files[0]);
					});
				}
			}
		});
		// Hidden file input — clicking the drop area triggers it.
		// Use the raw DOM node directly (Ext.fly returns a Fly singleton
		// that doesn't accept addListener; reusable wrapper is the wrong
		// abstraction for a single component-scoped input).
		this.fileInput = document.createElement('input');
		this.fileInput.type = 'file';
		this.fileInput.accept = '.csv,text/csv';
		this.fileInput.style.display = 'none';
		document.body.appendChild(this.fileInput);
		this.fileInput.addEventListener('change', () => {
			if (this.fileInput.files.length > 0) this.onFilePicked(this.fileInput.files[0]);
		});

		// Preview is just a static 5×N text matrix — render with a
		// plain HTML table inside an Ext.panel to dodge the layout
		// drama that grid+store+reconfigure triggered. No interaction
		// needed: this is purely visual confirmation for the operator
		// before they commit the mapping.
		this.previewPanel = Ext.create('Ext.panel.Panel', {
			title: i18n('Preview (first 5 rows)'),
			height: 180,
			autoScroll: true,
			bodyPadding: 8,
			html: '<i class="limas-text-muted">' + i18n('Drop a CSV above to preview it here.') + '</i>'
		});

		this.hasHeaderCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('First row is a header'),
			checked: true,
			listeners: {change: () => me.refreshPreview()}
		});

		this.mpnColumn = this.buildColumnCombo('MPN', true);
		this.mfrColumn = this.buildColumnCombo('Manufacturer', false);
		this.catColumn = this.buildColumnCombo('Category', false);
		this.stoColumn = this.buildColumnCombo('Storage', false);
		this.qtyColumn = this.buildColumnCombo('Quantity', false);

		// Duplicates strategy — what to do when an inbound row's
		// (manufacturer, MPN) already exists in the local inventory.
		// Skip is the safe default. UpdateStock additionally requires
		// the Quantity column mapping above.
		this.duplicatesCombo = Ext.create('Ext.form.field.ComboBox', {
			fieldLabel: i18n('On duplicate'),
			labelWidth: 140,
			editable: false,
			value: 'skip',
			store: {
				fields: ['value', 'label'],
				data: [
					{value: 'skip', label: i18n('Skip — leave existing Part untouched (default)')},
					{value: 'create_anyway', label: i18n('Create anyway — new Part record (different supplier/batch)')},
					{value: 'update_stock', label: i18n('Update stock — add Quantity to existing, create+seed new')}
				]
			},
			displayField: 'label',
			valueField: 'value',
			queryMode: 'local'
		});

		// Both pickers are tree-style. Category uses the existing
		// CategoryComboBox (PartCategory is a real tree). Storage uses
		// the new StorageLocationTreeComboBox: StorageLocationCategory
		// nodes as folders, StorageLocation entries injected as leaves —
		// the (category + location) pair lives in a single field.
		// `select` (not `change`) because the TreePicker override at
		// ExtJS/Enhancements/Ext.ux.TreePicker-setValueWithObject.js
		// replaces setValue() without callParent — the parent's change-
		// firing chain never runs. The override does fire `select` from
		// selectItem(), which is what tree click-to-pick goes through.
		this.defaultCategoryCombo = Ext.create('Limas.CategoryComboBox', {
			fieldLabel: i18n('Default Category'),
			labelWidth: 140,
			allowBlank: false,
			displayField: 'name',
			emptyText: i18n('Pick category for unmapped rows'),
			listeners: {select: () => me.refreshSubmitButton()}
		});
		this.defaultStorageCombo = Ext.create('Limas.Widgets.StorageLocationTreeComboBox', {
			fieldLabel: i18n('Default Storage'),
			labelWidth: 140,
			allowBlank: false,
			emptyText: i18n('Pick storage for unmapped rows'),
			listeners: {select: () => me.refreshSubmitButton()}
		});

		this.submitButton = Ext.create('Ext.button.Button', {
			text: i18n('Start Import'),
			iconCls: 'fugue-icon arrow-circle-225',
			disabled: true,
			handler: () => me.onSubmit()
		});

		// Progressive disclosure: only the drop area is visible at
		// start. Once a valid CSV lands and parses cleanly, we reveal
		// the preview + mapping + defaults sections via
		// `postDropContainer.show()`. Keeps the empty state from
		// looking like a half-broken form.
		this.postDropContainer = Ext.create('Ext.container.Container', {
			layout: 'vbox',
			defaults: {anchor: '100%'},
			hidden: true,
			items: [
				{xtype: 'tbtext', html: '<b>' + i18n('Step 1 of 2 — local preview, no upload yet') + '</b>', margin: '8 0'},
				this.previewPanel,
				{
					// Side-by-side: column mapping (the long stack) on the
					// left, defaults + duplicates strategy on the right.
					// Keeps the dialog readable without scrolling on a
					// typical laptop screen.
					xtype: 'container',
					layout: 'hbox',
					items: [
						{
							xtype: 'fieldset',
							title: i18n('Column mapping'),
							layout: 'vbox',
							flex: 1,
							margin: '0 8 0 0',
							defaults: {anchor: '100%', labelWidth: 140},
							items: [
								this.hasHeaderCheckbox,
								this.mpnColumn, this.mfrColumn, this.catColumn, this.stoColumn, this.qtyColumn
							]
						},
						{
							xtype: 'container',
							layout: 'vbox',
							flex: 1,
							defaults: {anchor: '100%'},
							items: [
								{
									xtype: 'fieldset',
									title: i18n('Defaults for unmapped / unresolvable rows'),
									layout: 'vbox',
									defaults: {anchor: '100%'},
									items: [this.defaultCategoryCombo, this.defaultStorageCombo]
								},
								{
									// Job-level decision — not a default for
									// fallback rows but the strategy applied
									// to every existing-Part match.
									xtype: 'fieldset',
									title: i18n('Duplicates strategy'),
									layout: 'vbox',
									defaults: {anchor: '100%'},
									items: [this.duplicatesCombo]
								}
							]
						}
					]
				}
			]
		});

		return Ext.create('Ext.panel.Panel', {
			itemId: 'step-mapping',
			layout: 'vbox',
			defaults: {anchor: '100%'},
			bodyPadding: 12,
			// Window has a fixed initial height; the fieldsets stack
			// vertically and the bottom one ("Duplicates strategy" /
			// Start Import) would clip on smaller monitors without an
			// inner scroll. Bottom toolbar stays docked so the action
			// button is always reachable.
			autoScroll: true,
			items: [this.dropArea, this.postDropContainer],
			dockedItems: [{
				xtype: 'toolbar',
				dock: 'bottom',
				items: ['->', this.submitButton]
			}]
		});
	},

	buildColumnCombo: function (labelKey, required) {
		return Ext.create('Ext.form.field.ComboBox', {
			fieldLabel: i18n(labelKey),
			labelWidth: 140,
			allowBlank: !required,
			displayField: 'label',
			valueField: 'idx',
			store: {fields: ['idx', 'label'], data: []},
			queryMode: 'local',
			emptyText: required ? i18n('(required)') : i18n('(optional)'),
			listeners: {change: () => this.refreshSubmitButton()}
		});
	},

	onFilePicked: function (file) {
		// Sanity check before we read any bytes. Strict on extension —
		// many file types have empty MIME so a permissive check would
		// let .ini / .json / .yaml / etc. through and produce nonsense
		// rows that confuse the grid layout.
		let name = String(file.name || '').toLowerCase();
		if (!/\.(csv|tsv|txt)$/.test(name)) {
			Ext.toast({
				html: i18n('Not a CSV — expected .csv / .tsv / .txt, got ') + Ext.htmlEncode(file.name || '?'),
				align: 't',
				autoCloseDelay: 4000
			});
			return;
		}
		// Guard against absurdly large files — 50 MB CSV is already
		// ~500k rows, far beyond what this UI can preview or what the
		// worker should chew through in one job.
		if (file.size > 50 * 1024 * 1024) {
			Ext.toast({
				html: i18n('File too large (max 50 MB).'),
				align: 't',
				autoCloseDelay: 4000
			});
			return;
		}
		this.parsedFile = file;
		let reader = new FileReader();
		reader.onload = (ev) => {
			let text = ev.target.result;
			this.parsedRows = this.parseCsv(text);
			if (this.parsedRows.length === 0) {
				Ext.toast({
					html: i18n('CSV looked empty after parsing. Wrong delimiter?'),
					align: 't',
					autoCloseDelay: 4000
				});
				return;
			}
			this.populateColumnCombos();
			this.guessMapping();
			this.refreshPreview();
			this.refreshSubmitButton();
			// Reveal the post-drop sections (preview + mapping + defaults) now that we have parseable content to populate
			this.postDropContainer.show();
		};
		reader.onerror = () => {
			Ext.toast({html: i18n('Could not read the file (not text?).'), align: 't', autoCloseDelay: 4000});
		};
		reader.readAsText(file, 'UTF-8');
	},

	// Tiny JS CSV parser — handles comma/semicolon/tab + quoted fields
	// well enough for preview. Backend re-parses with PHP fgetcsv for
	// the real import.
	parseCsv: function (text) {
		// Strip BOM
		if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
		// Detect delimiter from first line
		let firstNL = text.indexOf('\n');
		let head = firstNL >= 0 ? text.slice(0, firstNL) : text;
		let delim = ',';
		let best = head.split(',').length;
		[';', '\t', '|'].forEach(d => {
			let c = head.split(d).length;
			if (c > best) {
				best = c;
				delim = d;
			}
		});
		let rows = [], row = [], cell = '', inQuote = false;
		for (let i = 0; i < text.length; i++) {
			let c = text.charAt(i);
			if (inQuote) {
				if (c === '"') {
					if (text.charAt(i + 1) === '"') {
						cell += '"';
						i++;
					} else inQuote = false;
				} else cell += c;
			} else {
				if (c === '"') inQuote = true;
				else if (c === delim) {
					row.push(cell.trim());
					cell = '';
				} else if (c === '\n') {
					row.push(cell.trim());
					rows.push(row);
					row = [];
					cell = '';
				} else if (c === '\r') { /* skip */
				} else cell += c;
			}
		}
		if (cell !== '' || row.length > 0) {
			row.push(cell.trim());
			rows.push(row);
		}
		return rows.filter(r => r.length > 0 && !(r.length === 1 && r[0] === ''));
	},

	populateColumnCombos: function () {
		if (!this.parsedRows || this.parsedRows.length === 0) return;
		let header = this.parsedRows[0];
		let combos = [this.mpnColumn, this.mfrColumn, this.catColumn, this.stoColumn];
		let data = header.map((h, i) => ({idx: i, label: 'Col ' + (i + 1) + (h ? ' (' + h + ')' : '')}));
		combos.forEach(c => c.getStore().loadData(data));
	},

	guessMapping: function () {
		if (!this.parsedRows || this.parsedRows.length === 0) return;
		let header = this.parsedRows[0].map(c => String(c).toLowerCase());
		let find = (...keys) => {
			for (let i = 0; i < header.length; i++) {
				for (let k of keys) {
					if (header[i].indexOf(k) >= 0) return i;
				}
			}
			return null;
		};
		let mpn = find('mpn', 'part number', 'part_number', 'partno', 'part no', 'product code');
		let mfr = find('manufacturer', 'mfr', 'brand', 'maker', 'vyrobca');
		let cat = find('category', 'kategoria', 'cat');
		let sto = find('storage', 'location', 'sklad', 'shelf');
		let qty = find('quantity', 'qty', 'mnozstvo', 'count', 'pcs', 'ks');
		if (mpn !== null) this.mpnColumn.setValue(mpn);
		if (mfr !== null) this.mfrColumn.setValue(mfr);
		if (cat !== null) this.catColumn.setValue(cat);
		if (sto !== null) this.stoColumn.setValue(sto);
		if (qty !== null) this.qtyColumn.setValue(qty);
	},

	refreshPreview: function () {
		if (!this.parsedRows || this.parsedRows.length === 0) {
			this.previewPanel.update('<i class="limas-text-muted">' + i18n('(no rows parsed)') + '</i>');
			return;
		}
		let hasHeader = this.hasHeaderCheckbox.getValue();
		let allRows = this.parsedRows.slice();
		let headerRow = hasHeader ? allRows.shift() : null;
		let cols = Math.min(8, this.parsedRows[0].length);
		let previewRows = allRows.slice(0, this.previewRowCount);

		let html = '<table class="limas-grid-table">';
		// header
		html += '<tr><th>#</th>';
		for (let i = 0; i < cols; i++) {
			let label = headerRow ? (headerRow[i] || '(col ' + (i + 1) + ')') : ('Col ' + (i + 1));
			html += '<th>' + Ext.htmlEncode(label) + '</th>';
		}
		html += '</tr>';
		// body
		previewRows.forEach((r, idx) => {
			html += '<tr><td class="limas-text-muted">' + (idx + 1) + '</td>';
			for (let j = 0; j < cols; j++) {
				html += '<td class="limas-grid-cell-truncate" title="' + Ext.htmlEncode(r[j] || '') + '">' + Ext.htmlEncode(r[j] || '') + '</td>';
			}
			html += '</tr>';
		});
		html += '</table>';
		this.previewPanel.update(html);
	},

	refreshSubmitButton: function () {
		// All four conditions must hold: file dropped, MPN column picked,
		// AND both defaults set. The defaults are required by the worker
		// (it falls back to them when a row doesn't resolve), so we'd
		// rather gate the submit than let the operator post a job that
		// gets rejected at the controller.
		let ok = this.parsedFile !== null
			&& this.mpnColumn.getValue() !== null
			&& this.extractIri(this.defaultCategoryCombo.getValue()) !== null
			&& this.extractIri(this.defaultStorageCombo.getValue()) !== null;
		this.submitButton.setDisabled(!ok);
	},

	// CategoryComboBox / StorageLocationPicker return the Ext model
	// record on getValue(); other combos may already return the IRI
	// string. Flatten both shapes to the IRI string (or null).
	extractIri: function (v) {
		if (v === null || v === undefined || v === '') return null;
		if (typeof v === 'string') return v;
		if (v && typeof v.get === 'function') return v.get('@id') || null;
		return null;
	},

	// ──────────────────────────── STEP 2 ────────────────────────────

	// Step 2 deliberately uses plain Ext.panel.Panel + setHtml for the
	// status banner / worker hint / progress table. The earlier grid
	// approach (Ext.grid.Panel + store.loadData) consistently triggered
	// "Layout run failed" — same gotcha that bit the step 1 preview.
	// Static panels with HTML bodies render cleanly inside the card
	// layout and we don't need interaction (sortable columns, etc) on
	// progress rows anyway.
	buildStepProgress: function () {
		// No worker-instructions hint here any more — the Bulk Import menu
		// entry is gated on `messenger.workers.async.alive` in Limas.js, so
		// reaching this step implies a worker is consuming the queue. The
		// status banner gets the first poll's "Status: running — X / Y"
		// within ~2 s of submit.
		this.statusPanel = Ext.create('Ext.panel.Panel', {
			border: false,
			bodyStyle: 'background: transparent;',
			margin: '0 0 8 0',
			html: '<i>' + i18n('Submitting job…') + '</i>'
		});
		this.progressBodyPanel = Ext.create('Ext.panel.Panel', {
			flex: 1,
			border: true,
			autoScroll: true,
			bodyPadding: 8,
			html: '<i class="limas-text-muted">' + i18n('Waiting for status…') + '</i>'
		});

		return Ext.create('Ext.panel.Panel', {
			itemId: 'step-progress',
			layout: 'vbox',
			defaults: {width: '100%'},
			bodyPadding: 12,
			items: [this.statusPanel, this.progressBodyPanel]
		});
	},

	renderProgressRows: function (items) {
		if (!items || items.length === 0) {
			return '<i class="limas-text-muted">' + i18n('No rows yet.') + '</i>';
		}
		let labels = {
			pending: i18n('Pending'),
			success: '✅ ' + i18n('OK'),
			warning: '⚠ ' + i18n('Warn'),
			skipped: '⊘ ' + i18n('Skipped'),
			ambiguous: '? ' + i18n('Ambiguous'),
			failed: '✗ ' + i18n('Failed')
		};
		let html = '<table class="limas-grid-table" style="width:100%;">';
		html += '<tr>'
			+ '<th style="width:40px;">#</th>'
			+ '<th>' + i18n('MPN') + '</th>'
			+ '<th>' + i18n('Manufacturer') + '</th>'
			+ '<th style="width:60px;">' + i18n('Qty') + '</th>'
			+ '<th style="width:110px;">' + i18n('Status') + '</th>'
			+ '<th>' + i18n('Result') + '</th>'
			+ '</tr>';
		items.forEach(function (r) {
			let status = labels[r.status] ? r.status : 'pending';
			let pill = '<span class="limas-status limas-status-' + status + '">' + labels[status] + '</span>';
			// Link to created/existing Part + the worker's free-text message
			// (which carries the "+N stock" detail or warning context). Both
			// kept side-by-side so the operator gets the link AND the why.
			let lines = [];
			if (r.partId) {
				lines.push(i18n('Part') + ' #' + r.partId + ' ' + Ext.htmlEncode(r.partName || ''));
			}
			if (r.existingPartId && r.existingPartId !== r.partId) {
				lines.push(i18n('Existing') + ' #' + r.existingPartId + ' ' + Ext.htmlEncode(r.existingPartName || ''));
			}
			if (r.message) {
				lines.push('<span class="limas-text-muted">' + Ext.htmlEncode(r.message) + '</span>');
			}
			let qtyCell = '';
			if (r.quantityApplied !== null && r.quantityApplied !== undefined) {
				qtyCell = '<span class="limas-text-success">+' + r.quantityApplied + '</span>';
			} else if (r.rawQuantity) {
				// Show the raw cell when no quantity was applied (eg. Skip mode keeps it in the row but doesn't touch stock)
				qtyCell = '<span class="limas-text-muted">' + Ext.htmlEncode(r.rawQuantity) + '</span>';
			}
			html += '<tr>'
				+ '<td class="limas-text-muted">' + (r.line || '') + '</td>'
				+ '<td>' + Ext.htmlEncode(r.mpn || '') + '</td>'
				+ '<td>' + Ext.htmlEncode(r.manufacturer || '') + '</td>'
				+ '<td style="text-align:right;">' + qtyCell + '</td>'
				+ '<td>' + pill + '</td>'
				+ '<td>' + (lines.join('<br>') || '—') + '</td>'
				+ '</tr>';
		});
		html += '</table>';
		return html;
	},

	onSubmit: function () {
		let catIri = this.extractIri(this.defaultCategoryCombo.getValue());
		let stoIri = this.extractIri(this.defaultStorageCombo.getValue());
		if (!catIri || !stoIri) {
			Ext.toast({html: i18n('Default Category and Storage are required.'), align: 't', closable: true});
			return;
		}
		let meta = {
			hasHeader: this.hasHeaderCheckbox.getValue(),
			mapping: {mpn: this.mpnColumn.getValue()},
			defaultCategoryId: this.iriId(catIri),
			defaultStorageLocationId: this.iriId(stoIri),
			duplicatesBehavior: this.duplicatesCombo.getValue() || 'skip'
		};
		if (this.mfrColumn.getValue() !== null) meta.mapping.manufacturer = this.mfrColumn.getValue();
		if (this.catColumn.getValue() !== null) meta.mapping.category = this.catColumn.getValue();
		if (this.stoColumn.getValue() !== null) meta.mapping.storage = this.stoColumn.getValue();
		if (this.qtyColumn.getValue() !== null) meta.mapping.quantity = this.qtyColumn.getValue();

		let fd = new FormData();
		fd.append('file', this.parsedFile);
		fd.append('meta', JSON.stringify(meta));

		Ext.Ajax.request({
			url: Limas.getBasePath() + '/api/bulk-import',
			method: 'POST',
			rawData: fd,
			headers: Object.assign(
				{},
				Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders(),
				// Let the browser set the multipart boundary itself
				{'Content-Type': null}
			),
			success: function (response) {
				let r = Ext.decode(response.responseText);
				this.jobId = r.jobId;
				this.getLayout().setActiveItem('step-progress');
				this.startPolling();
			},
			failure: function (response) {
				let detail = '';
				try {
					let err = Ext.decode(response.responseText);
					if (err && err.error) detail = ': ' + err.error;
				} catch (e) {
				}
				Ext.toast({html: i18n('Bulk import submit failed (HTTP ') + response.status + ')' + Ext.htmlEncode(detail), align: 't', closable: true});
			},
			scope: this
		});
	},

	iriId: function (iri) {
		if (typeof iri !== 'string') return iri;
		let parts = iri.split('/');
		return parseInt(parts[parts.length - 1], 10);
	},

	// ──────────────────────────── Polling ────────────────────────────

	startPolling: function () {
		this.pollJob();
		this.pollTimer = setInterval(() => this.pollJob(), 2000);
	},

	stopPolling: function () {
		if (this.pollTimer) {
			clearInterval(this.pollTimer);
			this.pollTimer = null;
		}
	},

	pollJob: function () {
		if (!this.jobId) return;
		Ext.Ajax.request({
			url: Limas.getBasePath() + '/api/bulk-import-jobs/' + this.jobId,
			method: 'GET',
			headers: Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders(),
			success: function (response) {
				let j = Ext.decode(response.responseText);
				this.statusPanel.update(
					'<b>' + i18n('Status') + ':</b> ' + Ext.htmlEncode(j.status) + ' &mdash; ' +
					i18n('processed') + ' ' + j.processedRows + ' / ' + j.totalRows
				);
				this.progressBodyPanel.update(this.renderProgressRows(j.items || []));
				if (j.status === 'completed' || j.status === 'partial' || j.status === 'failed') {
					this.stopPolling();
				}
			},
			scope: this
		});
	},

	listeners: {
		close: function () {
			this.stopPolling();
			if (this.fileInput && this.fileInput.parentNode) {
				this.fileInput.parentNode.removeChild(this.fileInput);
			}
		}
	}
});
