/**
 * Review/override dialog opened from the InfoProvider aggregator's
 * "Review…" button. Lets the user override the merger's per-field consensus
 * before applying, and multi-select datasheets / images so multiple variants
 * land as separate attachments (different distributors ship different
 * revisions / generic-vs-real photos; the CAS layer dedupes identical bytes
 * automatically).
 *
 * The dialog mutates an `overrides` object and hands it back to the caller
 * via the `apply` event. SearchPanel.doApply consumes it.
 *
 * Sections are only shown when there is a meaningful choice — single-value
 * fields collapse out of the dialog to keep it short.
 */
Ext.define('Limas.InfoProviderAggregator.ApplyReviewDialog', {
	extend: 'Ext.window.Window',
	alias: 'widget.ApplyReviewDialog',

	title: i18n('Customize apply'),
	width: 720,
	height: 560,
	modal: true,
	resizable: true,
	closable: true,
	layout: 'fit',

	candidateRow: null,
	applyFlags: null,
	overrides: null,

	initComponent: function () {
		this.overrides = {};

		this.formPanel = Ext.create('Ext.form.Panel', {
			border: false,
			autoScroll: true,
			bodyPadding: 12,
			defaults: {anchor: '100%'},
			items: this.buildSections()
		});

		this.items = [this.formPanel];

		this.dockedItems = [{
			xtype: 'toolbar',
			dock: 'bottom',
			ui: 'footer',
			items: [
				'->',
				{
					text: i18n('Cancel'),
					iconCls: 'web-icon cancel',
					handler: Ext.bind(this.close, this)
				},
				{
					text: i18n('Apply'),
					iconCls: 'fugue-icon blueprint--plus',
					handler: Ext.bind(this.onApplyClick, this)
				}
			]
		}];

		this.callParent();
	},

	buildSections: function () {
		let sections = [];
		let row = this.candidateRow;
		let flags = this.applyFlags;

		// Description — always applied by doApply, always reviewable
		sections.push(this.buildSingleChoiceSection({
			key: 'description',
			label: i18n('Description'),
			chosen: row.get('description'),
			sources: row.get('descriptionSources') || {}
		}));

		// Manufacturer — always applied. Only show picker if there's
		// disagreement (single value = nothing to override).
		let mfrSources = row.get('manufacturerSources') || {};
		if (Object.keys(mfrSources).length > 1) {
			sections.push(this.buildSingleChoiceSection({
				key: 'manufacturer',
				label: i18n('Manufacturer'),
				chosen: row.get('manufacturer'),
				sources: mfrSources
			}));
		}

		// Footprint — only relevant when the flag is on
		if (flags.footprint) {
			let pkgSources = row.get('packageSources') || {};
			if (Object.keys(pkgSources).length > 0) {
				sections.push(this.buildSingleChoiceSection({
					key: 'package',
					label: i18n('Footprint (package)'),
					chosen: row.get('package'),
					sources: pkgSources
				}));
			}
		}

		if (flags.bestDatasheet) {
			let dsSources = row.get('datasheetSources') || {};
			let urls = this.distinctUrlPerSource(dsSources);
			if (urls.length > 0) {
				sections.push(this.buildMultiUrlSection({
					key: 'datasheetUrls',
					label: i18n('Datasheets'),
					hint: i18n('Tick each variant you want attached. Different distributors often ship different revisions; the storage layer dedupes identical bytes.'),
					chosenUrl: row.get('datasheetUrl'),
					entries: urls
				}));
			}
		}

		if (flags.images) {
			let imgSources = row.get('imageSources') || {};
			let urls = this.distinctUrlPerSource(imgSources);
			if (urls.length > 0) {
				sections.push(this.buildMultiUrlSection({
					key: 'imageUrls',
					label: i18n('Images'),
					hint: i18n('One distributor often returns generic package art, another a real product photo. Tick what you want kept.'),
					chosenUrl: row.get('imageUrl'),
					entries: urls,
					isImage: true
				}));
			}
		}

		// Per-parameter override — only where distributors actually disagree.
		// Parameters with a single value (consensus / one source) apply as-is
		// and would only bloat the dialog, so they are left out entirely.
		if (flags.parameters) {
			this.buildParameterConflictSections(sections);
		}

		if (sections.length === 0) {
			sections.push({
				xtype: 'displayfield',
				value: '<i class="limas-text-muted">' + i18n('All fields have a single source value — nothing to customize.') + '</i>'
			});
		}

		return sections;
	},

	/**
	 * Append one radio fieldset per CONFLICTING parameter (sources disagree on
	 * the value). Records a {radioName, key, valueToEntry} descriptor per
	 * section in this.paramSections so onApplyClick can map the chosen display
	 * value back to that source's fully-parsed entry (numeric/unit/siPrefix),
	 * not just the string.
	 */
	buildParameterConflictSections: function (sections) {
		this.paramSections = [];
		let bySource = this.candidateRow.get('paramsBySource') || {};
		let flatByKey = {};
		Ext.Array.each(this.candidateRow.get('paramsFlat') || [], function (e) {
			flatByKey[(e.name || '').toLowerCase()] = e;
		});

		let idx = 0;
		Ext.Object.each(bySource, function (key, bs) {
			let options = this.groupParamOptions(bs);
			if (options.length < 2) {
				return; // one effective value — nothing to override
			}

			// Pre-check the value the merger currently applies
			let flat = flatByKey[key];
			let chosenDisp = flat ? (flat.valueText || flat.value) : null;
			let radioName = 'param_' + idx + '_radio';
			let radios = [], valueToEntry = {}, anyChecked = false;
			options.forEach(function (opt) {
				let isChosen = opt.disp === chosenDisp;
				anyChecked = anyChecked || isChosen;
				valueToEntry[opt.disp] = opt.entry;
				radios.push({
					xtype: 'radio',
					name: radioName,
					inputValue: opt.disp,
					checked: isChosen,
					boxLabel: '<b>' + Ext.htmlEncode(opt.sources.map(Limas.distributorLabel).join(', ')) + (isChosen ? ' · <span class="limas-text-success">' + i18n('current') + '</span>' : '') + '</b> <span class="limas-text-muted" style="margin-left:8px;">' + Ext.htmlEncode(opt.disp) + '</span>'
				});
			});
			if (!anyChecked && radios.length > 0) {
				radios[0].checked = true;
			}

			sections.push({
				xtype: 'fieldset',
				title: i18n('Parameter') + ': ' + Ext.htmlEncode(bs.name),
				margin: '0 0 12 0',
				items: radios
			});
			this.paramSections.push({radioName: radioName, key: key, name: bs.name, valueToEntry: valueToEntry});
			idx++;
		}, this);
	},

	/**
	 * Collapse a parameter's per-source values into distinct pick options,
	 * normalising away spelling that is NOT a real disagreement:
	 *
	 *  - Sources bucket by their parsed NUMBERS, so format variants that decode
	 *    to the same value ("0°C~70°C" vs "0...70°C" → min0/max70) merge.
	 *  - Within a numeric bucket, an entry that only DIFFERS by a missing unit
	 *    ("1" vs "1 MHz") folds into the unit-bearing one — the unit-bearing
	 *    display/entry is kept, since the apply path needs the unit.
	 *  - Two genuinely different units at the same number (100Ω vs 100kΩ) stay
	 *    separate. Pure-text params (SMT vs Surface Mount) can't be reconciled
	 *    and stay separate too.
	 *
	 * @return {Array} [{disp, entry, sources: []}] — length < 2 means no conflict
	 */
	groupParamOptions: function (bs) {
		let buckets = {};
		Ext.Object.each(bs.sources, function (src, se) {
			let disp = se.valueText || se.value;
			if (disp === null || disp === undefined || disp === '') {
				return;
			}
			let hasNum = se.numericValue !== null || se.numericMin !== null || se.numericMax !== null;
			let numKey = hasNum
				? ('n:' + [se.numericValue, se.numericMin, se.numericMax].map(function (x) {
					return (x === null || x === undefined) ? '' : String(x);
				}).join('|'))
				: ('s:' + String(disp).toLowerCase().replace(/\s+/g, ' ').trim());
			let unitKey = (se.siPrefix || '') + '' + (se.unit || '');
			let hasUnit = !!(se.siPrefix || se.unit);
			let b = buckets[numKey] || (buckets[numKey] = {byUnit: {}, all: []});
			let rec = {src: src, se: se, disp: disp, hasUnit: hasUnit};
			b.all.push(rec);
			(b.byUnit[unitKey] || (b.byUnit[unitKey] = [])).push(rec);
		});

		let options = [];
		Ext.Object.each(buckets, function (numKey, b) {
			let realUnitKeys = Ext.Array.filter(Ext.Object.getKeys(b.byUnit), function (uk) {
				return Ext.Array.some(b.byUnit[uk], function (e) {
					return e.hasUnit;
				});
			});
			if (realUnitKeys.length <= 1) {
				// single effective value — prefer a unit-bearing representative
				let rep = Ext.Array.findBy(b.all, function (e) {
					return e.hasUnit;
				}) || b.all[0];
				options.push({
					disp: rep.disp,
					entry: rep.se,
					sources: Ext.Array.map(b.all, function (e) {
						return e.src;
					})
				});
			} else {
				// real unit disagreement — one option per distinct unit
				Ext.Object.each(b.byUnit, function (uk, list) {
					options.push({
						disp: list[0].disp,
						entry: list[0].se,
						sources: Ext.Array.map(list, function (e) {
							return e.src;
						})
					});
				});
			}
		});
		return options;
	},

	/**
	 * Collapse a {sourceName: url} map to one entry per distinct URL but keep
	 * the list of sources that returned it, so the dialog can show
	 * "Farnell + DigiKey" on a row when both link the same revision
	 */
	distinctUrlPerSource: function (sourceMap) {
		let byUrl = {};
		Ext.Object.each(sourceMap, function (src, url) {
			if (!url) {
				return;
			}
			if (!byUrl[url]) {
				byUrl[url] = [];
			}
			byUrl[url].push(src);
		});
		let out = [];
		Ext.Object.each(byUrl, function (url, srcs) {
			out.push({url: url, sources: srcs});
		});
		return out;
	},

	buildSingleChoiceSection: function (cfg) {
		let radios = [];
		let chosenSeen = false;

		// Map of value → list of source names that returned it, deduped
		let byValue = {};
		Ext.Object.each(cfg.sources, function (src, val) {
			if (val === null || val === undefined || val === '') {
				return;
			}
			if (!byValue[val]) {
				byValue[val] = [];
			}
			byValue[val].push(src);
		});

		Ext.Object.each(byValue, function (val, srcs) {
			let isChosen = val === cfg.chosen;
			chosenSeen = chosenSeen || isChosen;
			radios.push({
				xtype: 'radio',
				name: cfg.key + '_radio',
				inputValue: val,
				checked: isChosen,
				boxLabel: '<b>' + Ext.htmlEncode(srcs.join(', ')) + (isChosen ? ' · <span class="limas-text-success">' + i18n('consensus') + '</span>' : '') + '</b>' + ' <span class="limas-text-muted" style="margin-left:8px;">' + Ext.htmlEncode(val) + '</span>'
			});
		});

		// If the chosen value isn't represented in any source map (rare; merger derived it), include a synthetic "consensus" radio at the top so the user can keep it
		if (!chosenSeen && cfg.chosen) {
			radios.unshift({
				xtype: 'radio',
				name: cfg.key + '_radio',
				inputValue: cfg.chosen,
				checked: true,
				boxLabel: '<b><span class="limas-text-success">' + i18n('consensus') + '</span></b>' + ' <span class="limas-text-muted" style="margin-left:8px;">' + Ext.htmlEncode(cfg.chosen) + '</span>'
			});
		}

		return {
			xtype: 'fieldset',
			title: cfg.label,
			margin: '0 0 12 0',
			items: radios.length > 0 ? radios : [{
				xtype: 'displayfield',
				value: '<i class="limas-text-muted">' + i18n('(no source values)') + '</i>'
			}]
		};
	},

	buildMultiUrlSection: function (cfg) {
		let checkboxes = [];
		cfg.entries.forEach(function (entry) {
			let preChecked = entry.url === cfg.chosenUrl;
			// For images show an inline thumbnail so variants can be compared at
			// a glance (generic package art vs a real photo). The browser loads
			// the full image client-side; a broken/blocked URL just hides the
			// thumb. The `open` link stays for viewing full size.
			let thumb = cfg.isImage
				? '<img src="' + Ext.htmlEncode(entry.url) + '" alt="" style="max-height:44px; max-width:64px; vertical-align:middle; margin-right:8px; border:1px solid #ccc; background:#fff;" onerror="this.style.display=\'none\'"/>'
				: '';
			let label = thumb + '<b>' + Ext.htmlEncode(entry.sources.map(Limas.distributorLabel).join(', ')) + (preChecked ? ' · <span class="limas-text-success">' + i18n('consensus') + '</span>' : '') + '</b>' + ' <a href="' + Ext.htmlEncode(entry.url) + '" target="_blank" rel="noopener" style="margin-left:8px;">' + (cfg.isImage ? '🖼 ' : '📄 ') + i18n('open') + '</a>' + ' <span class="limas-text-muted" style="margin-left:6px; font-size:11px;">' + Ext.htmlEncode(entry.url) + '</span>';
			checkboxes.push({
				xtype: 'checkbox',
				name: cfg.key,
				inputValue: entry.url,
				checked: preChecked,
				boxLabel: label
			});
		});

		return {
			xtype: 'fieldset',
			title: cfg.label,
			margin: '0 0 12 0',
			items: [{
				xtype: 'displayfield',
				value: '<i class="limas-text-muted">' + cfg.hint + '</i>'
			}].concat(checkboxes)
		};
	},

	onApplyClick: function () {
		let overrides = {};
		let values = this.formPanel.getForm().getValues();

		// Radios — formvalues returns the inputValue of the checked one keyed by `<key>_radio`
		['description', 'manufacturer', 'package'].forEach(function (k) {
			let v = values[k + '_radio'];
			if (v !== undefined && v !== '') {
				overrides[k] = v;
			}
		});

		// Multi-checkbox URL fields — ExtJS returns either a single string or an array depending on count. Normalize.
		['datasheetUrls', 'imageUrls'].forEach(function (k) {
			let v = values[k];
			if (v === undefined || v === null || v === '') return;
			overrides[k] = Ext.isArray(v) ? v : [v];
		});

		// Per-parameter overrides — resolve the chosen display value back to
		// that source's fully-parsed entry (keyed by canonical name) so the
		// apply path gets numeric/unit/siPrefix, not just the string.
		if (this.paramSections && this.paramSections.length > 0) {
			let params = {};
			this.paramSections.forEach(function (ps) {
				let chosen = values[ps.radioName];
				if (chosen === undefined || chosen === '') return;
				let entry = ps.valueToEntry[chosen];
				if (entry) {
					params[ps.key] = Ext.apply({name: ps.name}, entry);
				}
			});
			if (Ext.Object.getKeys(params).length > 0) {
				overrides.parameters = params;
			}
		}

		this.fireEvent('apply', overrides);
		this.close();
	}
});
