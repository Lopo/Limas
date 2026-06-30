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

		// Description — always applied by doApply, always reviewable.
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

		// Footprint — only relevant when the flag is on.
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

		if (sections.length === 0) {
			sections.push({
				xtype: 'displayfield',
				value: '<i class="limas-text-muted">' + i18n('All fields have a single source value — nothing to customize.') + '</i>'
			});
		}

		return sections;
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

		// Map of value → list of source names that returned it, deduped.
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
			let label = '<b>' + Ext.htmlEncode(entry.sources.join(', ')) + (preChecked ? ' · <span class="limas-text-success">' + i18n('consensus') + '</span>' : '') + '</b>' + ' <a href="' + Ext.htmlEncode(entry.url) + '" target="_blank" rel="noopener" style="margin-left:8px;">' + (cfg.isImage ? '🖼 ' : '📄 ') + i18n('open') + '</a>' + ' <span class="limas-text-muted" style="margin-left:6px; font-size:11px;">' + Ext.htmlEncode(entry.url) + '</span>';
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

		// Multi-checkbox URL fields — ExtJS returns either a single string or
		// an array depending on count. Normalize.
		['datasheetUrls', 'imageUrls'].forEach(function (k) {
			let v = values[k];
			if (v === undefined || v === null || v === '') return;
			overrides[k] = Ext.isArray(v) ? v : [v];
		});

		this.fireEvent('apply', overrides);
		this.close();
	}
});
