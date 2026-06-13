/**
 * InfoProvider Aggregator search panel — searches for an MPN across all
 * configured info providers (TME, DigiKey, Farnell, Mouser, …), shows a merged
 * candidate list with cross-provider consensus + conflict markers, and applies
 * the picked candidate's data into the host PartEditorWindow's Part record
 * (Octopart-style flow). User then fills Category / StorageLocation / Stock
 * and saves through the editor as usual.
 */
Ext.define('Limas.Components.InfoProviderAggregator.SearchPanel', {
	extend: 'Ext.panel.Panel',
	xtype: 'infoProviderAggregatorSearchPanel',
	layout: 'border',

	grid: null,
	store: null,
	searchBar: null,
	applyButton: null,
	sourcesContainer: null,
	enabledSources: null, // populated by loadSources()
	sourceButtons: null,
	sourceOrder: null, // string[] — render + priority order, persisted
	mergeStrategy: 'majority', // 'majority' | 'hierarchy', persisted
	serverDefaults: null, // {priority, mergeStrategy} echoed from /sources
	configuredSourceData: null, // raw configured sources array from /sources
	lastQuery: '',

	/**
	 * What slices of data to copy into the host editor's Part record. Mirrors
	 * the Octopart "Apply Data" dialog so the aggregator can be a drop-in replacement.
	 *
	 *  - parameters    — PartParameter rows from the merged candidate
	 *  - distributors  — PartDistributor rows with sku/currency/price ladder
	 *  - bestDatasheet — first datasheet URL → server-side download → attachment
	 *  - images        — primary image URL → server-side download → attachment
	 *
	 * Not yet wired (no source data through current adapters):
	 *  - cadModels         — Octopart-only; no TME/DigiKey/Farnell equivalent
	 *  - referenceDesigns  — Octopart-only
	 *  - footprint         — Farnell exposes footprint inside attributes,
	 *                        DigiKey/TME don't; needs taxonomy mapping first
	 */
	applyFlags: null,

	initComponent: function () {
		this.applyFlags = {
			parameters: true,
			distributors: true,
			bestDatasheet: true,
			images: true
		};

		this.store = Ext.create('Ext.data.Store', {
			fields: [
				{name: 'manufacturer', type: 'string'},
				{name: 'mpn', type: 'string'},
				{name: 'isExactMatch', type: 'boolean'},
				{name: 'description', type: 'string'},
				{name: 'sources'},
				{name: 'conflicts'},
				{name: 'package', type: 'string'},
				{name: 'datasheetUrl', type: 'string'},
				{name: 'datasheetSources'}, // {sourceName: url, ...} for multi-URL fallback
				{name: 'imageUrl', type: 'string'},
				{name: 'imageSources'}, // ditto for images
				{name: 'paramCount', type: 'int'},
				// Two-phase state: false on rows loaded from light search,
				// flipped true after the on-demand POST /deepen call has
				// merged Phase-2 detail back in. Apply Data + Parameter
				// columns wait on this. 'pending' while a deepen is in
				// flight so we don't fire a second call for the same row.
				{name: 'deepened'}, // false / 'pending' / true
				// Worst lifecycle status across all contributing sources —
				// "any source flagged Discontinued" trumps "another source
				// said Active" so the grid surfaces the strongest warning.
				{name: 'worstLifecycle', type: 'string'},
				{name: 'providerSpecific'},
				{name: 'paramsFlat'}, // flat de-duped param list for applyData
				{name: 'conflictsDetail'}, // {fieldName: {chosen, sources: {sourceName: value}}}
				// Existing-part info: flat scalar fields driven by the
				// unambiguous `inDb` boolean. ExtJS's int/string coercion was
				// turning null→0/"" so a nested-object or use-null configs
				// kept rendering everything as matched — boolean is the only
				// safe gate for "row should show inventory tint".
				{name: 'inDb', type: 'boolean'},
				{name: 'existingPartId', type: 'int'},
				{name: 'existingPartName', type: 'string'},
				{name: 'existingStorageLocation', type: 'string'},
				{name: 'existingStock', type: 'int'}
			],
			proxy: {
				type: 'ajax',
				url: '',
				pageParam: '',
				startParam: '',
				limitParam: '',
				reader: {
					type: 'json',
					// Backend returns a bare JSON array. Transform flattens each
					// AggregatedPartCandidate into the simple field shape above.
					// `deepened` is set per record from the current search phase:
					// `light` keeps it false so the selection handler triggers a
					// deepen; `full` (legacy heavy path) starts already deepened.
					transform: (data) => {
						if (!Ext.isArray(data)) return data;
						let needle = (this.lastQuery || '').trim().toLowerCase();
						let initiallyDeepened = this.searchPhase !== 'light';
						return {
							candidates: data.map((c) => Object.assign(
								this.candidateToRow(c, needle),
								{deepened: initiallyDeepened}
							))
						};
					},
					rootProperty: 'candidates'
				}
			},
			autoLoad: false
		});

		// Per-column filter UI — built-in Ext grid feature; gives the user
		// a manufacturer / mpn / package text filter without us building a
		// faceted filter rail. Important once a "FG-06"-style ambiguous
		// designation pulls 50+ candidates from N unrelated manufacturers.
		this.grid = Ext.create({
			xtype: 'grid',
			region: 'center',
			plugins: ['gridfilters'],
			columns: [
				{text: i18n('Manufacturer'), dataIndex: 'manufacturer', flex: 1, filter: {type: 'string'}},
				{
					text: i18n('MPN'), dataIndex: 'mpn', flex: 1, filter: {type: 'string'},
					renderer: function (v, meta, record) {
						let txt = Ext.htmlEncode(v || '');
						if (record.get('isExactMatch')) {
							return '<span class="limas-text-success" data-qtip="' + i18n('Exact MPN match') + '">✓ </span>' +
								'<b>' + txt + '</b>';
						}
						return '<span class="limas-text-muted" data-qtip="' + i18n('Fuzzy match — distributor returned this on keyword search but MPN differs from your query') + '">' + txt + '</span>';
					}
				},
				{text: i18n('Description'), dataIndex: 'description', flex: 3, filter: {type: 'string'}},
				{
					text: i18n('Sources'), dataIndex: 'sources', flex: 1,
					renderer: function (v) {
						if (!Ext.isArray(v)) return '';
						// `title=` covers native browser tooltip too — Farnell
						// and Newark share the element14 glyph so hover is the only way to tell them apart
						return v.map(s =>
							'<i class="distributor-icon ' + Ext.String.htmlEncode(s) +
							'" title="' + Ext.htmlEncode(s) +
							'" data-qtip="' + Ext.htmlEncode(s) + '" style="margin:0 2px;"></i>'
						).join('');
					}
				},
				{
					text: i18n('Conflicts'), dataIndex: 'conflicts', flex: 1,
					renderer: function (v) {
						if (!Ext.isArray(v) || v.length === 0) return '';
						return '<span class="limas-text-warning">⚠ ' + v.join(', ') + '</span>';
					}
				},
				{text: i18n('Package'), dataIndex: 'package', flex: 1, filter: {type: 'string'}},
				{
					// Render '?' before the deepen call returns — light search
					// has no parameters yet, so a literal 0 is indistinguishable
					// from a part that genuinely has zero. Only once the row's
					// `deepened` flag flips to true does the real count get
					// rendered (including 0 when that's the truth).
					text: i18n('Params'), dataIndex: 'paramCount', width: 70, filter: {type: 'number'},
					align: 'right',
					renderer: function (v, meta, record) {
						if (record.get('deepened') === true) return v;
						// Centre the '?' placeholder; right-align (the column default) only reads well for the numeric value
						meta.style = 'text-align:center;';
						return '<span class="limas-text-muted" data-qtip="' + i18n('Loading detail…') + '">?</span>';
					}
				},
				{
					text: i18n('Lifecycle'), dataIndex: 'worstLifecycle', width: 110,
					renderer: (v) => this.formatLifecycle(v).trim()
				},
				{
					text: i18n('In DB'), dataIndex: 'inDb', width: 130,
					renderer: function (inDb, meta, record) {
						if (!inDb) return '';
						let partId = record.get('existingPartId');
						let name = record.get('existingPartName');
						let stock = record.get('existingStock');
						let storage = record.get('existingStorageLocation');
						let label = name || ('#' + partId);
						let stockNum = Ext.util.Format.number(stock, '0,000');
						let loc = storage ? ' @ ' + Ext.htmlEncode(storage) : '';
						meta.tdAttr = 'data-qtip="' + Ext.htmlEncode(
							i18n('Part') + ' #' + partId + ' ' + (name || '') + loc + ' · ' + stockNum + ' ' + i18n('pcs')
						) + '"';
						return '<span class="limas-text-success">✓ ' +
							Ext.htmlEncode(label) +
							'</span> <span class="limas-text-muted">· ' + stockNum + ' ' + i18n('pcs') + '</span>';
					}
				}
			],
			viewConfig: {
				// Subtle green tint on rows that match a local Part — keeps
				// the existing inventory rows obvious in a long candidate list
				// without changing the textual content.
				getRowClass: function (record) {
					return record.get('inDb') ? 'limas-aggregator-row-existing' : '';
				}
			},
			store: this.store
		});

		// Per-source toggle chip strip. Buttons are added directly to the
		// top toolbar by `loadSources()` once /sources resolves — a nested
		// container inside the toolbar collapses to zero width without an
		// explicit size and hides everything, hence the flat layout.
		this.enabledSources = {}; // name => bool, set by loadSources
		this.sourceButtons = {}; // name => Ext.button.Button, set by loadSources
		this.sourcesPlaceholder = Ext.create('Ext.toolbar.TextItem', {
			html: '<i class="limas-text-muted">' + i18n('Discovering providers…') + '</i>'
		});
		this.SOURCES_STORAGE_KEY = 'limas.aggregator.enabledSources';
		this.SETTINGS_STORAGE_KEY = 'limas.aggregator.settings'; // {sourceOrder, mergeStrategy}

		this.searchBar = Ext.create('Ext.form.field.Text', {
			emptyText: i18n('Enter MPN (manufacturer part number)'),
			flex: 1,
			listeners: {
				specialkey: function (field, e) {
					if (e.getKey() === e.ENTER) {
						let val = field.getValue();
						if (val && val.trim() !== '') {
							this.startSearch(val.trim());
						}
					}
				},
				scope: this
			}
		});

		// Apply data to the Part record opened in the host editor (Octopart-style
		// flow). User still sets Category/Storage/Stock and saves manually.
		this.contextLabel = Ext.create('Ext.form.Label', {
			html: '<i class="limas-text-muted">' + i18n('Picked data will fill the Part editor; set category, storage and save there as usual.') + '</i>'
		});

		this.applyCheckboxes = ['parameters', 'distributors', 'bestDatasheet', 'images'].map(function (key) {
			let labels = {
				parameters: i18n('Parameters'),
				distributors: i18n('Distributors'),
				bestDatasheet: i18n('Best Datasheet'),
				images: i18n('Image')
			};
			return Ext.create('Ext.form.field.Checkbox', {
				boxLabel: labels[key],
				checked: this.applyFlags[key],
				margin: '0 8 0 0',
				listeners: {
					change: function (cb, v) {
						this.applyFlags[key] = v;
					},
					scope: this
				}
			});
		}, this);

		this.applyButton = Ext.create('Ext.button.Button', {
			text: i18n('Apply Data'),
			iconCls: 'fugue-icon blueprint--plus',
			disabled: true,
			handler: this.onApplyClick,
			scope: this
		});

		this.showMoreButton = Ext.create('Ext.button.Button', {
			text: i18n('Show more'),
			tooltip: i18n('Fetch up to 100 candidates per source. Default is 20; bump only when you see the cap was hit (e.g. ambiguous MPN like FG-06).'),
			iconCls: 'fugue-icon arrow-circle-225',
			hidden: true,
			handler: this.onShowMoreClick,
			scope: this
		});

		// "Complete more" — opt-in trigger that lifts the backend's
		// COMPLETION_AUTO_CAP (=10) for the next query, so EVERY incomplete
		// candidate's missing sources get fetched. Hidden until the result
		// set shows the cap could have clipped data.
		this.completeMoreButton = Ext.create('Ext.button.Button', {
			text: i18n('Complete more'),
			tooltip: i18n('Re-run with the completion cap lifted — fills missing sources for every candidate, not just the top 10. Slower, but useful when you scroll past the first batch and see lone-source rows.'),
			iconCls: 'fugue-icon arrow-merge-090',
			hidden: true,
			handler: this.onCompleteMoreClick,
			scope: this
		});

		// Per-distributor detail panel — populated from the selected row's
		// providerSpecific map: SKU, stock, lifecycle, category, datasheet
		// link and the price ladder per source. Collapsible so the user can
		// reclaim grid width when not needed.
		this.detailPanel = Ext.create('Ext.panel.Panel', {
			region: 'east',
			title: i18n('Per-distributor details'),
			width: 320,
			split: true,
			collapsible: true,
			titleCollapse: true,
			autoScroll: true,
			bodyPadding: 8,
			html: '<i class="limas-text-muted">' + i18n('Select a candidate to see per-distributor SKU, stock, prices, …') + '</i>'
		});

		this.topToolbar = Ext.create('Ext.toolbar.Toolbar', {
			region: 'north',
			height: 36,
			items: [
				{xtype: 'tbtext', text: i18n('MPN:')},
				this.searchBar,
				{
					xtype: 'button',
					text: i18n('Search'),
					iconCls: 'fugue-icon magnifier',
					handler: function () {
						let val = this.searchBar.getValue();
						if (val && val.trim() !== '') this.startSearch(val.trim());
					},
					scope: this
				},
				'->',
				{xtype: 'tbtext', text: i18n('Sources:'), cls: 'limas-text-muted'},
				this.sourcesPlaceholder,
				// Settings gear sits AFTER a hard separator so it reads as
				// "configure the row" rather than as another source chip.
				// We keep direct refs so chip add/remove can insert BEFORE
				// the separator without disturbing the trailing gear.
				(this.settingsSeparator = Ext.create('Ext.toolbar.Separator', {margin: '0 8 0 8'})),
				(this.settingsButton = Ext.create('Ext.button.Button', {
					iconCls: 'fugue-icon gear',
					tooltip: i18n('Aggregator settings — source order, merge strategy'),
					width: 28,
					handler: this.onSettingsClick,
					scope: this
				}))
			]
		});

		this.items = [
			this.topToolbar,
			this.grid,
			this.detailPanel,
			{
				region: 'south',
				xtype: 'toolbar',
				height: 40,
				padding: '6 8',
				items: [
					{xtype: 'tbtext', text: i18n('Apply:')},
					...this.applyCheckboxes,
					'->',
					this.completeMoreButton,
					this.showMoreButton,
					this.contextLabel,
					this.applyButton
				]
			}
		];

		this.grid.on('selectionchange', this.refreshImportEnabled, this);
		this.grid.on('selectionchange', this.refreshDetailPanel, this);
		this.grid.on('selectionchange', this.maybeDeepenSelectedRow, this);
		this.store.on('load', this.onResultsLoaded, this);

		this.callParent(arguments);

		this.loadSources();
	},

	loadSources: function () {
		Ext.Ajax.request({
			url: Limas.getBasePath() + '/api/distributor-aggregator/sources',
			headers: Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders(),
			method: 'GET',
			success: function (response) {
				let data = Ext.decode(response.responseText);
				let configured = (data.sources || []).filter(s => s.configured);
				this.serverDefaults = data.defaults || {priority: [], mergeStrategy: 'majority'};
				this.configuredSourceData = configured;
				// Drop the "Discovering providers…" placeholder; we're
				// about to fill that slot with the real chips. Any leftover
				// chips from a previous call get removed too (shouldn't
				// happen — loadSources runs once — but cheap insurance).
				if (this.sourcesPlaceholder && !this.sourcesPlaceholder.destroyed) {
					this.topToolbar.remove(this.sourcesPlaceholder, true);
				}
				Object.values(this.sourceButtons).forEach(b => this.topToolbar.remove(b, true));
				this.sourceButtons = {};
				if (configured.length === 0) {
					this.topToolbar.add({
						xtype: 'tbtext',
						html: '<span class="limas-text-warning">⚠ ' +
							i18n('No info providers configured. Set provider API keys in .env.local.') +
							'</span>'
					});
					this.searchBar.disable();
					return;
				}
				// Restore saved enabled set from localStorage. Missing key or
				// unknown source defaults to enabled — so a freshly added
				// distributor starts visible instead of silently off.
				let saved = this.readSavedSources();
				configured.forEach(s => {
					this.enabledSources[s.name] = saved ? (saved[s.name] !== false) : true;
				});
				// Restore order + merge strategy from localStorage; fall back
				// to server defaults (services.yaml) for missing keys so a
				// fresh install matches what the operator configured.
				let settings = this.readSavedSettings();
				this.mergeStrategy = (settings && settings.mergeStrategy) || this.serverDefaults.mergeStrategy || 'majority';
				this.sourceOrder = this.resolveSourceOrder(configured, settings);
				// Render chips in priority order. Saved chips that no longer match a configured source are skipped silently.
				let orderedSources = this.sourceOrder
					.map(name => configured.find(s => s.name === name))
					.filter(s => !!s);
				orderedSources.forEach(s => {
					let caps = (s.capabilities || []).join(', ').toLowerCase() || i18n('basic only');
					let on = this.enabledSources[s.name];
					let btn = Ext.create('Ext.button.Button', {
						enableToggle: true,
						pressed: on,
						iconCls: 'distributor-icon ' + s.name,
						tooltip: Ext.String.format('{0} — {1}', s.name, caps),
						width: 32,
						scale: 'small',
						cls: 'aggregator-source-toggle ' + (on ? 'aggregator-source-on' : 'aggregator-source-off'),
						toggleHandler: this.onSourceToggle,
						scope: this,
						sourceName: s.name
					});
					this.sourceButtons[s.name] = btn;
					this.insertChip(btn);
				});
			},
			failure: function (response) {
				if (this.sourcesPlaceholder && !this.sourcesPlaceholder.destroyed) {
					this.topToolbar.remove(this.sourcesPlaceholder, true);
				}
				this.topToolbar.add({
					xtype: 'tbtext',
					html: '<span class="limas-text-error">' + i18n('Could not query /sources (HTTP ') + response.status + ')</span>'
				});
			},
			scope: this
		});
	},

	/**
	 * Render a colored badge for the canonical `ManufacturingStatus` enum
	 * value (one of: active, pre_release, nrnd, eol, discontinued, unknown).
	 * Returns the empty string for null OR unknown — there's no point in
	 * decorating a "the vendor said something but we don't know what"
	 * with noise. The leading space lets it sit inline next to Stock
	 * without a manual separator.
	 */
	formatLifecycle: function (status) {
		if (!status || status === 'unknown') return '';
		let labels = {
			active: i18n('Active'),
			pre_release: i18n('Pre-release'),
			nrnd: i18n('NRND'),
			eol: i18n('EOL'),
			discontinued: i18n('Discontinued')
		};
		if (!labels[status]) return '';
		return ' <span class="limas-lifecycle limas-lifecycle-' + status + '">' + Ext.htmlEncode(labels[status]) + '</span>';
	},

	readSavedSources: function () {
		try {
			let raw = window.localStorage.getItem(this.SOURCES_STORAGE_KEY);
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	},

	writeSavedSources: function () {
		try {
			window.localStorage.setItem(
				this.SOURCES_STORAGE_KEY,
				JSON.stringify(this.enabledSources)
			);
		} catch (e) { /* quota / disabled → silently ignore, runtime state still works */
		}
	},

	readSavedSettings: function () {
		try {
			let raw = window.localStorage.getItem(this.SETTINGS_STORAGE_KEY);
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	},

	writeSavedSettings: function () {
		try {
			window.localStorage.setItem(this.SETTINGS_STORAGE_KEY, JSON.stringify({
				sourceOrder: this.sourceOrder,
				mergeStrategy: this.mergeStrategy
			}));
		} catch (e) { /* same handling as writeSavedSources */
		}
	},

	/**
	 * Build the effective render-and-priority order from (saved order ∪
	 * server defaults ∪ configured set). Saved order wins for known names;
	 * server defaults fill gaps; anything still missing gets appended in the
	 * order /sources returned it. Sources no longer configured are dropped.
	 */
	resolveSourceOrder: function (configured, settings) {
		let configuredNames = configured.map(s => s.name);
		let configuredSet = {};
		configuredNames.forEach(n => configuredSet[n] = true);
		let order = [];
		let seen = {};
		let pushKnown = (list) => {
			(list || []).forEach(name => {
				if (configuredSet[name] && !seen[name]) {
					order.push(name);
					seen[name] = true;
				}
			});
		};
		pushKnown(settings && settings.sourceOrder);
		pushKnown(this.serverDefaults && this.serverDefaults.priority);
		pushKnown(configuredNames);
		return order;
	},

	onSettingsClick: function () {
		if (!this.configuredSourceData || this.configuredSourceData.length === 0) {
			return;
		}
		let win = Ext.create('Limas.Components.InfoProviderAggregator.SettingsWindow', {
			sources: this.configuredSourceData,
			enabledSources: Ext.clone(this.enabledSources),
			sourceOrder: Ext.clone(this.sourceOrder || []),
			mergeStrategy: this.mergeStrategy,
			defaults: this.serverDefaults
		});
		win.on('settingschanged', this.applySettings, this);
		win.show();
	},

	applySettings: function (settings) {
		this.enabledSources = settings.enabledSources || {};
		this.sourceOrder = settings.sourceOrder || [];
		this.mergeStrategy = settings.mergeStrategy || 'majority';
		this.writeSavedSources();
		this.writeSavedSettings();
		// Re-render chips in the new order. Cheaper than diffing — there are at most ~10 chips.
		Object.values(this.sourceButtons).forEach(b => this.topToolbar.remove(b, true));
		this.sourceButtons = {};
		let configured = this.configuredSourceData || [];
		this.sourceOrder.forEach(name => {
			let s = configured.find(x => x.name === name);
			if (!s) return;
			let on = this.enabledSources[s.name] !== false;
			let caps = (s.capabilities || []).join(', ').toLowerCase() || i18n('basic only');
			let btn = Ext.create('Ext.button.Button', {
				enableToggle: true,
				pressed: on,
				iconCls: 'distributor-icon ' + s.name,
				tooltip: Ext.String.format('{0} — {1}', s.name, caps),
				width: 32,
				scale: 'small',
				cls: 'aggregator-source-toggle ' + (on ? 'aggregator-source-on' : 'aggregator-source-off'),
				toggleHandler: this.onSourceToggle,
				scope: this,
				sourceName: s.name
			});
			this.sourceButtons[s.name] = btn;
			this.insertChip(btn);
		});
	},

	/**
	 * Insert a source chip before the settings separator so the trailing
	 * `| ⚙` stays visually anchored to the right of the strip regardless
	 * of how many chips we add or remove
	 */
	insertChip: function (btn) {
		let idx = this.topToolbar.items.indexOf(this.settingsSeparator);
		if (idx < 0) {
			this.topToolbar.add(btn);
		} else {
			this.topToolbar.insert(idx, btn);
		}
	},

	onSourceToggle: function (btn, pressed) {
		this.enabledSources[btn.sourceName] = pressed;
		btn.removeCls(pressed ? 'aggregator-source-off' : 'aggregator-source-on');
		btn.addCls(pressed ? 'aggregator-source-on' : 'aggregator-source-off');
		this.writeSavedSources();
	},

	/**
	 * @returns {string[]} configured sources whose toggle is currently on, in iteration order
	 */
	currentEnabledSources: function () {
		return Object.keys(this.enabledSources).filter(n => this.enabledSources[n]);
	},

	/**
	 * Default and "show more" per-source limits. Backend caps the request,
	 * TME chunks >50 internally so 100 is safe; beyond that, UX (giant
	 * scrollable grid) degrades faster than completeness improves.
	 */
	DEFAULT_LIMIT: 20,
	EXPANDED_LIMIT: 100,
	// Mirrors `InfoProviderAggregator::COMPLETION_AUTO_CAP` — the cutoff
	// beyond which the backend stops auto-filling missing sources. We use
	// this to decide whether to surface the "Complete more" button.
	COMPLETION_AUTO_CAP: 10,

	/**
	 * Hit the backend `/resolve-url` endpoint to lift mpn+manufacturer
	 * from a distributor product URL, then re-enter `startSearch()` with
	 * the resolved MPN. On 404 (no URL handler matches) we toast a clear
	 * error so the user knows to try a different distributor URL or
	 * fall back to typing the MPN directly.
	 */
	resolveAndSearch: function (url, limit, completeAll) {
		Ext.Ajax.request({
			url: Limas.getBasePath() + '/api/distributor-aggregator/resolve-url?url=' + encodeURIComponent(url),
			method: 'GET',
			headers: Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders(),
			success: function (response) {
				let r = Ext.decode(response.responseText);
				if (!r || !r.mpn) {
					Ext.toast({html: i18n('URL resolved but no MPN extracted.'), align: 't', closable: true});
					return;
				}
				// Remember the resolved fields so we can auto-pick the
				// matching candidate once the search results land.
				// Preferred match: `providerSpecific[source].sourceSku`
				// equals `r.sourceSku` (unique per package variant —
				// beats string-matching MPN that may have suffix dupes).
				// Fallback: (mfr, mpn) string match. Empty manufacturer
				// (Mouser compact URL shape) → match by MPN alone.
				// onResultsLoaded consumes + clears this.
				this.pendingResolvedMatch = {
					mpn: String(r.mpn).trim().toLowerCase(),
					manufacturer: String(r.manufacturer || '').trim().toLowerCase(),
					source: r.source || '',
					sourceSku: String(r.sourceSku || '').trim()
				};
				this.searchBar.setValue(r.mpn);
				this.startSearch(r.mpn, limit, completeAll);
			},
			failure: function (response) {
				let detail = '';
				try {
					let err = Ext.decode(response.responseText);
					if (err && err.error) detail = ': ' + err.error;
				} catch (e) { /* non-JSON body, ignore */
				}
				Ext.toast({
					html: i18n('Could not resolve URL (HTTP ') + response.status + ')' + Ext.htmlEncode(detail),
					align: 't',
					closable: true
				});
			},
			scope: this
		});
	},

	// Flatten one AggregatedPartCandidate JSON into the store row shape.
	// Same projection used by:
	//   1. The reader's transform on /search response (initial grid load)
	//   2. The /deepen response handler (row upgrade after user selection)
	// Keeping both code paths through one method guarantees the heavy and
	// light variants stay structurally identical — row consumers (Apply
	// Data, detail panel, columns) don't need to know which phase produced
	// the data.
	candidateToRow: function (c, needle) {
		// De-dupe + Min/Max collapse across providers by CANONICAL name.
		// Vendor rawNames like "Resistance" / "Resistance Value" all
		// map to the same canonical via the backend's ParameterNormalizer
		// (Stage 1). The backend's ParameterValueParser (Stage 2) further
		// parses each value into numeric + unit + siPrefix + qualifier.
		// Per canonical we collapse to ONE row: qualifier=max writes
		// maxValue, qualifier=min writes minValue, untagged → value.
		let collected = {}, paramsFlat = [];
		Ext.Object.each(c.parameters || {}, function (src, list) {
			Ext.Array.each(list || [], function (p) {
				let key = (p.canonicalName || p.rawName || '').toLowerCase();
				if (!key || !p.rawValue) return;
				let entry = collected[key];
				if (!entry) {
					entry = {
						name: p.canonicalName || p.rawName,
						value: p.rawValue,
						numericValue: null, numericMin: null, numericMax: null,
						unit: null, siPrefix: null, valueText: null
					};
					collected[key] = entry;
					paramsFlat.push(entry);
				}
				if (entry.numericMin === null && p.numericMin !== null) entry.numericMin = p.numericMin;
				if (entry.numericMax === null && p.numericMax !== null) entry.numericMax = p.numericMax;
				if (p.qualifier === 'max') {
					if (entry.numericMax === null && p.numericValue !== null) entry.numericMax = p.numericValue;
				} else if (p.qualifier === 'min') {
					if (entry.numericMin === null && p.numericValue !== null) entry.numericMin = p.numericValue;
				} else if (entry.numericValue === null && p.numericValue !== null
					&& entry.numericMin === null && entry.numericMax === null
				) {
					entry.numericValue = p.numericValue;
				}
				if (!entry.unit && p.unit) entry.unit = p.unit;
				if (!entry.siPrefix && p.siPrefix) entry.siPrefix = p.siPrefix;
				if (!entry.valueText && p.valueText) entry.valueText = p.valueText;
			});
		});
		let mpn = c.manufacturerPartNumber ? c.manufacturerPartNumber.chosenValue : '';
		// Severity ranking — "discontinued" trumps "active" because the most worrying claim should bubble up
		let worstLifecycle = (function () {
			let order = ['active', 'pre_release', 'nrnd', 'eol', 'discontinued'];
			let worst = null, worstIdx = -1;
			Ext.Object.each(c.providerSpecific || {}, function (src, info) {
				let v = info && info.lifecycleStatus;
				if (!v) return;
				let idx = order.indexOf(v);
				if (idx > worstIdx) {
					worstIdx = idx;
					worst = v;
				}
			});
			return worst;
		})();
		let conflictsDetail = (function () {
			if (!Ext.isArray(c.conflicts)) return {};
			let out = {};
			c.conflicts.forEach(function (fname) {
				if (c[fname] && typeof c[fname] === 'object') {
					out[fname] = {chosen: c[fname].chosenValue, sources: c[fname].sourcesValues || {}};
				}
			});
			return out;
		})();
		return {
			manufacturer: c.manufacturerName ? c.manufacturerName.chosenValue : '',
			mpn: mpn,
			isExactMatch: needle !== '' && String(mpn).trim().toLowerCase() === needle,
			description: c.description ? c.description.chosenValue : '',
			sources: c.contributingSources || [],
			conflicts: c.conflicts || [],
			package: c.packageName ? c.packageName.chosenValue : '',
			datasheetUrl: c.datasheetUrl ? c.datasheetUrl.chosenValue : '',
			datasheetSources: c.datasheetUrl ? (c.datasheetUrl.sourcesValues || {}) : {},
			imageUrl: c.imageUrl ? c.imageUrl.chosenValue : '',
			imageSources: c.imageUrl ? (c.imageUrl.sourcesValues || {}) : {},
			paramCount: paramsFlat.length,
			worstLifecycle: worstLifecycle,
			providerSpecific: c.providerSpecific || {},
			paramsFlat: paramsFlat,
			conflictsDetail: conflictsDetail,
			inDb: !!c.existingPart,
			existingPartId: c.existingPart ? c.existingPart.partId : 0,
			existingPartName: c.existingPart ? c.existingPart.partName : '',
			existingStorageLocation: c.existingPart ? c.existingPart.storageLocationName : '',
			existingStock: (c.existingPart && c.existingPart.totalStock !== null && c.existingPart.totalStock !== undefined)
				? c.existingPart.totalStock : 0
		};
	},

	// Selection handler that fires the on-demand POST /deepen call so the
	// selected row gets its parameters + pricing + lifecycle backfilled.
	// No-op when the row is already deepened OR a deepen is currently
	// pending. Records loaded from a `phase=full` search start already
	// deepened so this path is purely the light-phase optimisation.
	maybeDeepenSelectedRow: function (sm, selected) {
		let rec = selected && selected.length ? selected[0] : null;
		if (!rec) return;
		let state = rec.get('deepened');
		if (state === true || state === 'pending') return;

		let ps = rec.get('providerSpecific') || {};
		let sourceSkuMap = {};
		Ext.Object.each(ps, function (name, info) {
			if (info && typeof info.sourceSku === 'string' && info.sourceSku !== '') {
				sourceSkuMap[name] = info.sourceSku;
			}
		});
		if (Object.keys(sourceSkuMap).length === 0) {
			rec.set('deepened', true);  // nothing to fetch; mark settled.
			return;
		}

		rec.set('deepened', 'pending');
		// Refresh button + detail panel render the pending state.
		this.refreshImportEnabled();
		this.refreshDetailPanel();

		let body = {sources: sourceSkuMap};
		let defPriority = (this.serverDefaults && this.serverDefaults.priority) || [];
		let order = this.sourceOrder || [];
		let priorityDiverges = order.length !== defPriority.length
			|| order.some((n, i) => n !== defPriority[i]);
		if (priorityDiverges && order.length > 0) body.priority = order;
		let defStrategy = (this.serverDefaults && this.serverDefaults.mergeStrategy) || 'majority';
		if (this.mergeStrategy && this.mergeStrategy !== defStrategy) body.mergeStrategy = this.mergeStrategy;

		Ext.Ajax.request({
			url: Limas.getBasePath() + '/api/distributor-aggregator/deepen',
			method: 'POST',
			jsonData: body,
			headers: Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders(),
			success: function (response) {
				let c;
				try {
					c = Ext.decode(response.responseText);
				} catch (e) {
					c = null;
				}
				if (!c) {
					rec.set('deepened', true);
					this.refreshImportEnabled();
					this.refreshDetailPanel();
					return;
				}
				let needle = (this.lastQuery || '').trim().toLowerCase();
				let row = this.candidateToRow(c, needle);
				row.deepened = true;
				rec.beginEdit();
				Ext.Object.each(row, function (k, v) {
					rec.set(k, v);
				});
				rec.endEdit();
				rec.commit();
				this.refreshImportEnabled();
				this.refreshDetailPanel();
			},
			failure: function () {
				rec.set('deepened', true); // unblock UI even on failure
				Ext.toast({
					html: i18n('Could not fetch detail for the selected row.'),
					align: 't', closable: true
				});
				this.refreshImportEnabled();
				this.refreshDetailPanel();
			},
			scope: this
		});
	},

	startSearch: function (query, limit, completeAll) {
		// URL shortcut: if the user pasted a distributor product-detail
		// URL, route through the backend URL-handler to lift mpn +
		// manufacturer out of the path, then re-enter startSearch with
		// the resolved MPN. Saves the copy-paste-MPN dance for users
		// coming from a distributor browser tab.
		if (/^https?:\/\//i.test(query)) {
			this.resolveAndSearch(query, limit, completeAll);
			return;
		}
		let sources = this.currentEnabledSources();
		if (Object.keys(this.enabledSources).length > 0 && sources.length === 0) {
			// User unchecked every source — searching would return no
			// candidates anyway. Block with a clear message instead of
			// silently producing an empty grid.
			Ext.toast({
				html: i18n('Enable at least one distributor (toggle the icons in the toolbar).'),
				align: 't',
				closable: true
			});
			return;
		}
		this.lastQuery = query;
		this.currentLimit = limit || this.DEFAULT_LIMIT;
		this.currentCompleteAll = !!completeAll;
		this.searchBar.setValue(query);
		// Only append `sources=` when the user has narrowed the set — if
		// every configured source is on, omit the param so backend default
		// "all" applies (same behaviour as before this UI was added).
		let allOn = sources.length === Object.keys(this.enabledSources).length;
		// Default search phase is light — Phase 1 only, no per-(source, sku)
		// detail fetch upfront. Selecting a row triggers the on-demand POST
		// /deepen call that fills in parameters + pricing.
		// `completeAll=1` opts back into the heavy path because completion +
		// price-comparison only makes sense once details are present.
		this.searchPhase = this.currentCompleteAll ? 'full' : 'light';
		let url = Limas.getBasePath() + '/api/distributor-aggregator/search'
			+ '?mpn=' + encodeURIComponent(query)
			+ '&merged=1&phase=' + this.searchPhase
			+ '&limit=' + this.currentLimit;
		if (!allOn) {
			url += '&sources=' + encodeURIComponent(sources.join(','));
		}
		if (this.currentCompleteAll) {
			url += '&completeAll=1';
		}
		// Pass priority + mergeStrategy ONLY when they diverge from the
		// server defaults — keeps URLs short and lets backend skip the
		// withMergeOverride() clone for vanilla requests.
		let defPriority = (this.serverDefaults && this.serverDefaults.priority) || [];
		let order = this.sourceOrder || [];
		let priorityDiverges = order.length !== defPriority.length
			|| order.some((n, i) => n !== defPriority[i]);
		if (priorityDiverges && order.length > 0) {
			url += '&priority=' + encodeURIComponent(order.join(','));
		}
		let defStrategy = (this.serverDefaults && this.serverDefaults.mergeStrategy) || 'majority';
		if (this.mergeStrategy && this.mergeStrategy !== defStrategy) {
			url += '&mergeStrategy=' + encodeURIComponent(this.mergeStrategy);
		}
		this.store.getProxy().setUrl(url);
		this.store.getProxy().setHeaders(
			Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders()
		);
		this.store.load();
	},

	/** Fired after store.load — toggles the "Show more" / "Complete more" button visibility. */
	onResultsLoaded: function () {
		if (this.showMoreButton) {
			// Show only when the result count hits the current limit (i.e. there
			// might be more truncated by the per-source cap) AND we haven't
			// already expanded to 50.
			let hitCap = this.store.getCount() >= this.currentLimit;
			let canExpand = this.currentLimit < this.EXPANDED_LIMIT;
			this.showMoreButton.setHidden(!(hitCap && canExpand));
		}
		if (this.completeMoreButton) {
			// Visible only when the default cap could have clipped data:
			//   - count > cap (so backend stopped completing past the top-N)
			//   - at least one row has fewer sources than the enabled set
			//   - we haven't already requested completeAll for this query
			let enabledCount = this.currentEnabledSources().length || Object.keys(this.enabledSources || {}).length;
			let total = this.store.getCount();
			let hasIncomplete = false;
			this.store.each(function (rec) {
				let s = rec.get('sources');
				if (Array.isArray(s) && s.length < enabledCount) {
					hasIncomplete = true;
					return false;
				}
			});
			let worthShowing = !this.currentCompleteAll
				&& total > this.COMPLETION_AUTO_CAP
				&& hasIncomplete;
			this.completeMoreButton.setHidden(!worthShowing);
		}

		// URL paste shortcut: if the last search was triggered by
		// resolveAndSearch(), pendingResolvedMatch carries the
		// expected fields from the URL. Find the matching row and
		// auto-select it so the user lands on "the part they were
		// looking at" instead of having to pick from cross-distri
		// candidates. Match strategy in order of precedence:
		//   1. sourceSku — distributor-specific unique id (DigiKey part
		//      number, Farnell /dp/N). Best signal: survives
		//      package-suffix ambiguity that pure MPN string match
		//      would miss.
		//   2. (mfr, mpn) string equality — fallback when URL didn't
		//      carry a sourceSku (LCSC, Mouser compact) or the source
		//      adapter isn't configured.
		// Multi-match or no-match → leave selection alone.
		if (this.pendingResolvedMatch) {
			let target = this.pendingResolvedMatch;
			this.pendingResolvedMatch = null;

			let bySku = null;
			if (target.sourceSku !== '' && target.source !== '') {
				let needleSku = target.sourceSku.toLowerCase();
				this.store.each(function (rec, idx) {
					let ps = rec.get('providerSpecific') || {};
					let info = ps[target.source];
					if (info && String(info.sourceSku || '').toLowerCase() === needleSku) {
						bySku = {rec: rec, idx: idx};
						return false;   // stop iteration — sourceSku is unique
					}
				});
			}

			let matches = bySku ? [bySku] : [];
			if (matches.length === 0) {
				let mpnLc = target.mpn;
				let mfrLc = target.manufacturer;
				this.store.each(function (rec, idx) {
					let recMpn = String(rec.get('mpn') || '').trim().toLowerCase();
					if (recMpn !== mpnLc) return;
					if (mfrLc !== '') {
						let recMfr = String(rec.get('manufacturer') || '').trim().toLowerCase();
						if (recMfr !== mfrLc) return;
					}
					matches.push({rec: rec, idx: idx});
				});
			}

			if (matches.length === 1) {
				this.grid.getSelectionModel().select(matches[0].rec);
				this.grid.getView().focusRow(matches[0].idx);
				Ext.toast({
					html: i18n('URL resolved — matching candidate pre-selected.'),
					align: 't',
					autoCloseDelay: 3000
				});
			} else if (matches.length > 1) {
				Ext.toast({
					html: i18n('URL resolved, but multiple candidates match — please pick one.'),
					align: 't',
					autoCloseDelay: 4000
				});
			}
		}
	},

	onCompleteMoreClick: function () {
		if (!this.lastQuery) return;
		// Re-runs the same query (preserving any current limit) but with
		// completeAll on. Backend may take noticeably longer; most underlying
		// calls hit the 5-min cache so it's typically still sub-second on
		// repeat.
		this.startSearch(this.lastQuery, this.currentLimit, true);
	},

	onShowMoreClick: function () {
		if (!this.lastQuery) return;
		this.startSearch(this.lastQuery, this.EXPANDED_LIMIT, this.currentCompleteAll);
	},

	/** Called by SearchWindow → from the host PartEditorWindow. */
	setPart: function (partRecord) {
		this.partRecord = partRecord;
	},

	refreshImportEnabled: function () {
		let row = this.grid.getSelectionModel().getSelection()[0];
		// Pending deepen → keep Apply disabled. Parameters / pricing /
		// lifecycle wouldn't be available yet, so applying would produce
		// an under-populated Part.
		let deepenPending = row && row.get('deepened') === 'pending';
		this.applyButton.setDisabled(!row || deepenPending);
	},

	refreshDetailPanel: function () {
		let row = this.grid.getSelectionModel().getSelection()[0];
		if (!row) {
			this.detailPanel.update(
				'<i class="limas-text-muted">' + i18n('Select a candidate to see per-distributor SKU, stock, prices, …') + '</i>'
			);
			return;
		}
		if (row.get('deepened') === 'pending') {
			this.detailPanel.update(
				'<i class="limas-text-muted">' + i18n('Loading parameters, pricing and lifecycle from sources…') + '</i>'
			);
			return;
		}
		this.detailPanel.update(this.renderProviderDetails(row));
	},

	/**
	 * Section at the top of the detail panel listing every field where
	 * sources disagreed. For each conflicting field we show all per-source
	 * values; the one the merger picked gets a ✓ marker. Lets the user spot
	 * which distributor's value the part will inherit before clicking Apply.
	 */
	renderConflictsSection: function (row) {
		let detail = row.get('conflictsDetail') || {};
		let keys = Object.keys(detail);
		if (keys.length === 0) return '';
		// Friendly labels for the technical field names
		let label = {
			description: i18n('Description'),
			datasheetUrl: i18n('Datasheet URL'),
			imageUrl: i18n('Image URL'),
			packageName: i18n('Package'),
			manufacturerName: i18n('Manufacturer'),
			manufacturerPartNumber: i18n('MPN')
		};
		let trunc = (s, max) => {
			if (s === null || s === undefined) return '<i class="limas-text-muted">∅</i>';
			s = String(s);
			return s.length > max
				? Ext.htmlEncode(s.substring(0, max)) + '…'
				: Ext.htmlEncode(s);
		};
		// Borderless table per conflicting field — marker / icon / value as
		// three columns with fixed widths so the checkmark and the icon line
		// up vertically across rows (centred-dot vs check-mark have different
		// visual widths and were jittering the alignment as a plain inline run).
		let out = '<div class="limas-box-warning" style="margin-bottom:14px;">' +
			'<div class="limas-box-warning-header">⚠ ' +
			i18n('Source conflicts') + ' (' + keys.length + ')</div>';
		keys.forEach(function (fname) {
			let info = detail[fname];
			let chosen = info.chosen;
			out += '<div style="margin-bottom:6px;font-size:11px;">';
			out += '<div style="font-weight:bold;">' + Ext.htmlEncode(label[fname] || fname) + '</div>';
			out += '<table style="border-collapse:collapse;width:100%;table-layout:fixed;">';
			out += '<colgroup>'
				+ '<col style="width:18px;">'   // marker column — same width for ✓ and ·
				+ '<col style="width:22px;">'   // distributor icon column
				+ '<col>'                       // value (truncated, ellipsis)
				+ '</colgroup>';
			Ext.Object.each(info.sources || {}, function (src, val) {
				let isChosen = (val === chosen) && val !== null && val !== undefined && val !== '';
				let marker = isChosen
					? '<span class="limas-text-success" data-qtip="' +
					Ext.htmlEncode(i18n('Merger picked this value')) + '">✓</span>'
					: '<span class="limas-text-muted">·</span>';
				// Title= shows over native browser tooltip, data-qtip drives
				// Ext's QuickTips overlay. Both keyed off the source name so
				// look-alike sprites (Farnell vs Newark share the element14
				// glyph) become distinguishable on hover.
				let srcEnc = Ext.htmlEncode(src);
				let icon = '<i class="distributor-icon ' + Ext.String.htmlEncode(src) +
					'" title="' + srcEnc + '" data-qtip="' + srcEnc + '"></i>';
				out += '<tr>'
					+ '<td style="padding:2px 0;text-align:center;vertical-align:top;">' + marker + '</td>'
					+ '<td style="padding:2px 0;text-align:center;vertical-align:top;">' + icon + '</td>'
					+ '<td style="padding:2px 4px;vertical-align:top;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" data-qtip="'
					+ Ext.htmlEncode(String(val ?? '')) + '">' + trunc(val, 70) + '</td>'
					+ '</tr>';
			});
			out += '</table>';
			out += '</div>';
		});
		out += '</div>';
		return out;
	},

	/**
	 * Build HTML blocks (one per contributing distributor) showing SKU, stock,
	 * lifecycle, category, datasheet link and the price ladder. Data comes
	 * straight from the candidate row's `providerSpecific` map (per-source
	 * fields the merger intentionally does NOT collapse — each distributor
	 * owns stock + prices for their own SKU).
	 */
	renderProviderDetails: function (row) {
		let providerSpecific = row.get('providerSpecific') || {};
		let datasheetSources = row.get('datasheetSources') || {};
		let imageSources = row.get('imageSources') || {};
		let html = this.renderConflictsSection(row);
		let fmtPrice = (p, currency) => {
			if (p === null || p === undefined) return '—';
			let s = (Math.abs(p) < 0.01 ? p.toExponential(2) : p.toFixed(4)).replace(/\.?0+$/, '');
			return s + (currency ? ' ' + Ext.htmlEncode(currency) : '');
		};
		let fmtStock = s => (s === null || s === undefined) ? '—' : Ext.util.Format.number(s, '0,000');
		Ext.Object.each(providerSpecific, (source, info) => {
			if (!info) return;
			let srcEnc = Ext.htmlEncode(source);
			let icon = '<i class="distributor-icon ' + Ext.String.htmlEncode(source) +
				'" title="' + srcEnc + '" data-qtip="' + srcEnc + '" style="margin-right:6px;"></i>';
			let header = '<span style="font-weight:bold;font-size:13px;">' + icon + srcEnc + '</span>';
			let openLink = info.productUrl
				? '<a href="' + Ext.htmlEncode(info.productUrl) + '" target="_blank" rel="noopener" style="float:right;font-size:11px;">' + i18n('Open ↗') + '</a>'
				: '';
			let lines = [];
			lines.push('<div><strong>' + i18n('SKU') + ':</strong> ' + Ext.htmlEncode(info.sourceSku || '—') + '</div>');
			lines.push('<div><strong>' + i18n('Stock') + ':</strong> ' + fmtStock(info.stock) +
				this.formatLifecycle(info.lifecycleStatus) + '</div>');
			if (info.categoryName) {
				lines.push('<div><strong>' + i18n('Category') + ':</strong> ' + Ext.htmlEncode(info.categoryName) + '</div>');
			}
			let extras = [];
			if (datasheetSources[source]) {
				extras.push('<a href="' + Ext.htmlEncode(datasheetSources[source]) + '" target="_blank" rel="noopener">📄 ' + i18n('Datasheet') + '</a>');
			}
			if (imageSources[source]) {
				extras.push('<a href="' + Ext.htmlEncode(imageSources[source]) + '" target="_blank" rel="noopener">🖼 ' + i18n('Image') + '</a>');
			}
			if (extras.length) {
				lines.push('<div style="margin-top:4px;">' + extras.join(' &nbsp;|&nbsp; ') + '</div>');
			}
			let breaks = info.priceBreaks || [];
			let priceTable = '';
			if (breaks.length) {
				let rows = breaks.map(b =>
					'<tr><td style="padding:2px 6px;">' + fmtStock(b.quantity) + '</td>' +
					'<td style="padding:2px 6px;text-align:right;">' + fmtPrice(b.price, info.currency) + '</td></tr>'
				).join('');
				priceTable = '<table style="width:100%;border-collapse:collapse;margin-top:6px;font-size:11px;">' +
					'<thead><tr class="limas-bg-header"><th style="text-align:left;padding:2px 6px;">' + i18n('Qty') + '</th>' +
					'<th style="text-align:right;padding:2px 6px;">' + i18n('Price') + '</th></tr></thead>' +
					'<tbody>' + rows + '</tbody></table>';
			}
			html += '<div class="limas-border-subtle limas-bg-subtle" style="margin-bottom:10px;padding:6px 8px;border-radius:4px;">' +
				'<div style="overflow:hidden;margin-bottom:4px;">' + header + openLink + '</div>' +
				lines.join('') +
				priceTable +
				'</div>';
		});
		return html || '<i class="limas-text-muted">' + i18n('No per-distributor info on this candidate.') + '</i>';
	},

	/**
	 * Octopart-style apply: pour the picked candidate's data into the host
	 * editor's Part record. User then sees a filled form, sets Category +
	 * StorageLocation + stock + comment and saves through the editor as
	 * normal. We do NOT call /import here — the backend importer is reserved
	 * for the CLI flow.
	 */
	onApplyClick: function () {
		let row = this.grid.getSelectionModel().getSelection()[0];
		if (!row) return;
		if (!this.partRecord) {
			Ext.Msg.alert(i18n('No part editor'), i18n('Cannot apply — no Part editor is open.'));
			return;
		}

		// Octopart-style preflight: ensure any required Manufacturer /
		// Distributor exists in DB (auto-create when missing) BEFORE we start
		// composing the Part. The pre-flight runs async and re-enters
		// `doApply` once all stores are populated — same pattern Octopart's
		// DataApplicator.checkRequirements uses.
		//
		// We intentionally do NOT re-enable applyButton on the success path:
		// doApply fires `applied`, which the host PartEditorWindow handles by
		// destroying this whole window; touching applyButton after that would
		// crash on a null component. The abort path keeps the window open, so
		// the button must come back enabled there.
		this.applyButton.disable();
		this.ensureRequirements(row, () => {
			this.doApply(row);
		}, () => {
			if (this.applyButton && !this.applyButton.destroyed) {
				this.applyButton.enable();
			}
		});
	},

	doApply: function (row) {
		let part = this.partRecord;
		part.set('name', row.get('mpn'));
		part.set('description', row.get('description'));

		// Default partUnit if none set yet — without this, the parts grid's
		// stockLevel renderer shows the count without a "pcs" suffix
		// because `part.getPartUnit()` returns null. Octopart's DataApplicator
		// doesn't set this either, but the legacy flow expects the editor
		// to have already wired the default; aggregator-opened editors
		// often haven't.
		if (!part.getPartUnit()) {
			let defaultUnit = Limas.getDefaultPartUnit();
			if (defaultUnit) {
				part.setPartUnit(defaultUnit);
			}
		}

		this.applyManufacturer(part, row.get('manufacturer'), row.get('mpn'));

		if (this.applyFlags.distributors) {
			this.applyDistributors(part, row.get('providerSpecific') || {});
		}
		if (this.applyFlags.parameters) {
			this.applyParameters(part, row.get('paramsFlat') || []);
		}

		this.applyAttachments(part, row, this.applyFlags, () => {
			this.fireEvent('applied');
		});
	},

	/**
	 * Ensure every entity we're about to reference exists in the local stores;
	 * if not, create it on the server (async) and reload the relevant store.
	 * Mirrors Octopart's DataApplicator.checkRequirements (assets/limas/
	 * Components/OctoPart/DataApplicator.js:44-122) — needed for the aggregator
	 * to be a true Octopart drop-in replacement.
	 *
	 * The chain is sequential to keep the wait-window text useful:
	 *   1. Manufacturer (one)
	 *   2. Distributors (zero..N from providerSpecific keys)
	 *
	 * Calls `onReady` when all entities exist. `onAbort` if user cancels or
	 * server save fails.
	 */
	ensureRequirements: function (row, onReady, onAbort) {
		let neededDistributors = [];
		if (this.applyFlags.distributors) {
			let providerSpecific = row.get('providerSpecific') || {};
			Ext.Object.each(providerSpecific, function (sourceKey) {
				neededDistributors.push(sourceKey);
			});
		}
		this.ensureManufacturer(row.get('manufacturer'), () => {
			this.ensureDistributors(neededDistributors, onReady, onAbort);
		}, onAbort);
	},

	ensureManufacturer: function (name, onReady, onAbort) {
		if (!name) {
			onReady();
			return;
		}
		let store = Ext.data.StoreManager.lookup('ManufacturerStore');
		if (this.findStoreRecordCi(store, 'name', name)) {
			onReady();
			return;
		}
		this.showWaitWindow(i18n('Creating Manufacturer…'), name);
		let mfr = Ext.create('Limas.Entity.Manufacturer');
		mfr.set('name', name);
		mfr.save({
			success: () => {
				store.load({
					callback: () => {
						this.hideWaitWindow();
						onReady();
					},
					scope: this
				});
			},
			failure: (rec, op) => {
				this.hideWaitWindow();
				Ext.Msg.alert(i18n('Could not create Manufacturer'),
					Ext.htmlEncode(name) + '<br>' + Ext.htmlEncode((op && op.getError && op.getError()) || ''));
				onAbort();
			},
			scope: this
		});
	},

	ensureDistributors: function (names, onReady, onAbort) {
		let store = Ext.data.StoreManager.lookup('DistributorStore');
		let missing = names.filter(n => !this.findStoreRecordCi(store, 'name', n));
		if (missing.length === 0) {
			onReady();
			return;
		}
		let createNext = () => {
			let name = missing.shift();
			if (!name) {
				onReady();
				return;
			}
			this.showWaitWindow(i18n('Creating Distributor…'), name);
			let dist = Ext.create('Limas.Entity.Distributor');
			dist.set('name', name);
			dist.save({
				success: () => {
					store.load({
						callback: () => {
							this.hideWaitWindow();
							createNext();
						},
						scope: this
					});
				},
				failure: (rec, op) => {
					this.hideWaitWindow();
					Ext.Msg.alert(i18n('Could not create Distributor'),
						Ext.htmlEncode(name) + '<br>' + Ext.htmlEncode((op && op.getError && op.getError()) || ''));
					onAbort();
				},
				scope: this
			});
		};
		createNext();
	},

	showWaitWindow: function (text, value) {
		this.waitMessage = Ext.MessageBox.show({
			msg: text + '<br/>' + Ext.htmlEncode(value || ''),
			width: 320,
			wait: {interval: 100}
		});
	},

	hideWaitWindow: function () {
		if (this.waitMessage instanceof Ext.window.MessageBox) {
			this.waitMessage.hide();
		}
	},

	/**
	 * Distributor-hosted PDF/image CDNs that we know don't ship Cloudflare
	 * challenges or hotlink protection. URLs on these hosts get tried first
	 * when applying datasheets / images so we don't waste an upload on a
	 * manufacturer site that's likely to 403.
	 */
	DISTRIBUTOR_HOSTS: /(^|\.)(farnell\.com|element14\.com|newark\.com|digikey\.com|digikey\.[a-z]{2}|mouser\.com|tme\.eu|tme\.com)$/i,

	/**
	 * Rank the per-source URL map so distributor-hosted CDNs come first.
	 * Returns a deduplicated URL list (chosen URL is always tried first to
	 * preserve merge-strategy intent).
	 */
	rankAttachmentUrls: function (chosen, sources) {
		let urls = [];
		let seen = {};
		let push = function (u) {
			if (!u || seen[u]) return;
			seen[u] = true;
			urls.push(u);
		};
		let isDistributorHost = (u) => {
			try {
				let h = new URL(u).hostname;
				return this.DISTRIBUTOR_HOSTS.test(h);
			} catch (e) {
				return false;
			}
		};
		if (chosen && isDistributorHost(chosen)) push(chosen);
		Ext.Object.each(sources || {}, function (k, v) {
			if (v && isDistributorHost(v)) push(v);
		});
		if (chosen) push(chosen);
		Ext.Object.each(sources || {}, function (k, v) {
			if (v) push(v);
		});
		return urls;
	},

	/**
	 * Chain: best datasheet, then image. We POST to the existing
	 * /api/temp_uploaded_files/upload endpoint (same one Octopart's
	 * DataApplicator uses) so the server downloads the asset and returns a
	 * TempUploadedFile we can attach to the Part. Calls `done` exactly once.
	 *
	 * Each task carries a ranked list of candidate URLs (distributor-hosted
	 * first, manufacturer sites as fallback); the first one that uploads OK
	 * wins. We only toast about failure once all candidates have failed —
	 * since a Cloudflare-blocked manufacturer PDF is the common case and
	 * Farnell/DigiKey usually have a working hostname-cached copy.
	 *
	 * Filename de-dup mirrors DataApplicator.checkIfAttachmentFilenameExists —
	 * skips re-upload if an attachment with the same originalFilename already
	 * exists on the Part.
	 */
	applyAttachments: function (part, row, flags, done) {
		let tasks = [];
		if (flags.bestDatasheet) {
			let urls = this.rankAttachmentUrls(row.get('datasheetUrl'), row.get('datasheetSources'));
			if (urls.length) tasks.push({urls: urls, description: i18n('Datasheet')});
		}
		if (flags.images) {
			let urls = this.rankAttachmentUrls(row.get('imageUrl'), row.get('imageSources'));
			if (urls.length) tasks.push({urls: urls, description: i18n('Image')});
		}
		if (tasks.length === 0) {
			done();
			return;
		}
		let runNext = () => {
			let task = tasks.shift();
			if (!task) {
				done();
				return;
			}

			// Find first URL whose filename isn't already on the Part — if any exists, we treat that task as already satisfied
			let pendingUrls = task.urls.filter(u => !this.attachmentExists(part, u));
			if (pendingUrls.length === 0) {
				runNext();
				return;
			}

			let lastErr = '';
			let lastUrl = '';
			let tryUrl = () => {
				let url = pendingUrls.shift();
				if (!url) {
					// All candidate URLs failed — give up on this attachment, surface the last attempt so user can grab it manually
					Ext.toast({
						html: '<b>' + Ext.htmlEncode(task.description) + '</b> ' + i18n('could not be downloaded') +
							' (' + Ext.htmlEncode(lastErr.substring(0, 120)) + ').<br><a href="' +
							Ext.htmlEncode(lastUrl) + '" target="_blank" rel="noopener">' + i18n('Open URL') + '</a>',
						align: 't',
						slideInDuration: 200,
						autoCloseDelay: 8000
					});
					runNext();
					return;
				}
				lastUrl = url;
				Limas.getApplication().uploadFileFromURL(url, task.description, function (options, success, response) {
					if (success) {
						let result = Ext.decode(response.responseText);
						if (result && result.response) {
							let f = Ext.create('Limas.Entity.TempUploadedFile', result.response);
							part.attachments().add(f);
							// Backend returns downloaded=false when the proxy
							// download failed but persisted URL-only. Surface
							// that so the user knows the cron has to fill it
							// in later (or they need to grab it manually).
							if (result.response.downloaded === false) {
								Ext.toast({
									html: '<b>' + Ext.htmlEncode(task.description) + '</b> ' +
										i18n('saved as link only (download blocked). The daily cron will retry.') +
										'<br><a href="' + Ext.htmlEncode(url) + '" target="_blank" rel="noopener">' +
										i18n('Open URL') + '</a>',
									align: 't',
									slideInDuration: 200,
									autoCloseDelay: 8000
								});
							}
						}
						runNext();
						return;
					}
					// Hard failure (validation, 500, …) — try next candidate
					try {
						let r = Ext.decode(response.responseText);
						lastErr = (r && (r['hydra:description'] || r.detail)) || (i18n('HTTP') + ' ' + response.status);
					} catch (e) {
						lastErr = i18n('HTTP') + ' ' + response.status;
					}
					tryUrl();
				}, this);
			};
			tryUrl();
		};
		runNext();
	},

	attachmentExists: function (part, uri) {
		let filename = String(uri).split(/[\\/]/).pop();
		for (let k = 0; k < part.attachments().count(); k++) {
			if (part.attachments().getAt(k).get('originalFilename') === filename) {
				return true;
			}
		}
		return false;
	},

	/**
	 * Robust case-insensitive exact lookup. ExtJS findRecord's `exactMatch=true`
	 * + `caseSensitive=false` combo has been flaky for vendor-cased names
	 * (`STMICROELECTRONICS` vs `STMicroelectronics`), so we iterate manually.
	 */
	findStoreRecordCi: function (store, fieldName, needle) {
		if (!store || !needle) return null;
		let target = String(needle).toLowerCase().trim();
		let hit = null;
		store.each(function (r) {
			if (String(r.get(fieldName) || '').toLowerCase().trim() === target) {
				hit = r;
				return false;
			}
		});
		return hit;
	},

	applyManufacturer: function (part, name, mpn) {
		if (!name) return;
		let store = Ext.data.StoreManager.lookup('ManufacturerStore');
		let mfr = this.findStoreRecordCi(store, 'name', name);
		if (mfr === null) {
			Ext.toast({
				html: i18n('Manufacturer not in DB:') + ' <b>' + Ext.htmlEncode(name) + '</b>. ' +
					i18n('Create it manually and re-apply, or set it on the part after saving.'),
				align: 't',
				slideInDuration: 200,
				autoCloseDelay: 5000
			});
			return;
		}
		let pm = Ext.create('Limas.Entity.PartManufacturer');
		pm.setManufacturer(mfr);
		pm.set('partNumber', mpn);
		let dup = null;
		for (let k = 0; k < part.manufacturers().count(); k++) {
			let existing = part.manufacturers().getAt(k);
			if (existing.isPartiallyEqualTo(pm, ['manufacturer.name'])) {
				dup = existing;
				break;
			}
		}
		if (dup) {
			dup.set('partNumber', mpn);
		} else {
			part.manufacturers().add(pm);
		}
	},

	applyDistributors: function (part, providerSpecific) {
		let store = Ext.data.StoreManager.lookup('DistributorStore');
		Ext.Object.each(providerSpecific, function (sourceKey, info) {
			if (!info || !store) return;
			let dist = this.findStoreRecordCi(store, 'name', sourceKey);
			if (dist === null) {
				Ext.toast({
					html: i18n('Distributor not in DB:') + ' <b>' + Ext.htmlEncode(sourceKey) + '</b>. ' +
						i18n('Create it manually and re-apply.'),
					align: 't',
					slideInDuration: 200,
					autoCloseDelay: 5000
				});
				return;
			}
			let breaks = info.priceBreaks || [];
			if (breaks.length === 0) {
				breaks = [{quantity: 1, price: null}];
			}
			Ext.Array.each(breaks, function (b) {
				let pd = Ext.create('Limas.Entity.PartDistributor');
				pd.setDistributor(dist);
				pd.set('sku', info.sourceSku || '');
				pd.set('orderNumber', info.sourceSku || '');
				pd.set('packagingUnit', Math.max(b.quantity || 1, 1));
				if (info.currency) pd.set('currency', String(info.currency).substring(0, 3));
				if (b.price !== null && b.price !== undefined) pd.set('price', b.price);
				let dup = null;
				for (let k = 0; k < part.distributors().count(); k++) {
					let existing = part.distributors().getAt(k);
					if (pd.isPartiallyEqualTo(existing, ['sku', 'packagingUnit', 'currency', 'distributor.name'])) {
						dup = existing;
						break;
					}
				}
				if (dup) {
					if (b.price !== null && b.price !== undefined) dup.set('price', b.price);
				} else {
					part.distributors().add(pd);
				}
			});
		}, this);
	},

	applyParameters: function (part, paramsFlat) {
		let unitStore = Ext.data.StoreManager.lookup('UnitStore');
		let siPrefixStore = Ext.data.StoreManager.lookup('SiPrefixStore');
		Ext.Array.each(paramsFlat, function (p) {
			let pp = Ext.create('Limas.Entity.PartParameter');
			pp.set('name', p.name);
			// Resolve Unit + SiPrefix entities by symbol — backend Stage-2
			// parser already split prefix vs base unit so we just do
			// direct exact-match store lookups, no SIUnitPrefix() guessing.
			let unitRec = (p.unit && unitStore)
				? unitStore.findRecord('symbol', p.unit, 0, false, true, true)
				: null;
			let siPrefixRec = (p.siPrefix && siPrefixStore)
				? siPrefixStore.findRecord('symbol', p.siPrefix, 0, false, true, true)
				: null;
			// Has the parser produced anything numeric? If yes, valueType
			// becomes 'numeric' and we populate value / minValue / maxValue
			// + unit + siPrefix FKs. If no, fall back to stringValue.
			let hasNumeric = (p.numericValue !== null && p.numericValue !== undefined)
				|| (p.numericMin !== null && p.numericMin !== undefined)
				|| (p.numericMax !== null && p.numericMax !== undefined);
			if (hasNumeric) {
				pp.set('valueType', 'numeric');
				if (p.numericValue !== null && p.numericValue !== undefined) {
					pp.set('value', p.numericValue);
				}
				if (p.numericMin !== null && p.numericMin !== undefined) {
					pp.set('minValue', p.numericMin);
				}
				if (p.numericMax !== null && p.numericMax !== undefined) {
					pp.set('maxValue', p.numericMax);
				}
				if (unitRec) {
					pp.setUnit(unitRec);
				}
				if (siPrefixRec) {
					pp.setSiPrefix(siPrefixRec);
				}
			} else {
				pp.set('valueType', 'string');
			}
			// Always set stringValue so the grid has a display fallback
			// even when valueType=numeric — Limas's PartParameter editor
			// renders stringValue when value is null.
			pp.set('stringValue', p.value);
			let dup = null;
			for (let k = 0; k < part.parameters().count(); k++) {
				let existing = part.parameters().getAt(k);
				if (existing.isPartiallyEqualTo(pp, ['name'])) {
					dup = existing;
					break;
				}
			}
			if (dup) {
				dup.set('valueType', pp.get('valueType'));
				if (hasNumeric) {
					if (p.numericValue !== null && p.numericValue !== undefined) dup.set('value', p.numericValue);
					if (p.numericMin !== null && p.numericMin !== undefined) dup.set('minValue', p.numericMin);
					if (p.numericMax !== null && p.numericMax !== undefined) dup.set('maxValue', p.numericMax);
					if (unitRec) dup.setUnit(unitRec);
					if (siPrefixRec) dup.setSiPrefix(siPrefixRec);
				}
				dup.set('stringValue', p.value);
			} else {
				part.parameters().add(pp);
			}
		});
	}
});
