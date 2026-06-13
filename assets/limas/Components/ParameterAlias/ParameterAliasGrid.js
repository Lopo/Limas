/**
 * Admin grid for the InfoProvider aggregator's `ParameterAlias` table.
 * Lets the user review auto-discovered vendor rawNames, edit `canonicalName`
 * inline, flip the `verified` flag, and bulk-merge several aliases into
 * one canonical via the dedicated toolbar button.
 *
 * Sort defaults to (verified ASC, usageCount DESC) so the most-used
 * unverified rows surface at the top — those are the highest-ROI manual
 * mappings the operator can promote.
 */
Ext.define('Limas.ParameterAliasGrid', {
	extend: 'Limas.EditorGrid',
	alias: 'widget.ParameterAliasGrid',

	addButton: false, // rows are seeded + auto-discovered, never hand-added here
	plugins: ['gridfilters', {ptype: 'cellediting', clicksToEdit: 1}],

	// Drive Limas.EditorGrid's built-in SearchField — OR-search across these
	// columns (rawName / canonical / vendor / shortname). The fake pref-name
	// is intentional: SearchField needs a non-null preference key to enter
	// the multi-field branch; missing prefs fall back to the defaults.
	searchFieldSystemPreference: 'limas.parameterAlias.searchFields',
	searchFieldSystemPreferenceDefaults: ['rawName', 'canonicalName', 'vendor', 'shortname'],

	initComponent: function () {
		this.columns = [
			{
				header: i18n('rawName'), dataIndex: 'rawName', flex: 2,
				filter: {type: 'string'}
			},
			{
				header: i18n('Vendor'), dataIndex: 'vendor', width: 90,
				filter: {type: 'list', options: ['digikey', 'farnell', 'tme', 'mouser', 'arrow', 'avnet', 'lcsc', 'newark'], phpMode: false},
				renderer: function (v) {
					if (!v) return '<i class="limas-text-muted">— global —</i>';
					return '<i class="distributor-icon ' + Ext.String.htmlEncode(v) +
						'" style="margin-right:4px;"></i>' + Ext.htmlEncode(v);
				}
			},
			{
				header: i18n('Canonical'), dataIndex: 'canonicalName', flex: 2,
				filter: {type: 'string'},
				editor: {xtype: 'textfield', allowBlank: false}
			},
			{
				header: i18n('Source'), dataIndex: 'source', width: 90,
				filter: {type: 'list', options: ['octopart', 'vendor', 'auto', 'user']},
				renderer: function (v) {
					let cls = {octopart: 'limas-text-link', vendor: 'limas-text-success', auto: 'limas-text-warning', user: 'limas-text-muted'};
					return '<span class="' + (cls[v] || '') + '" style="font-weight:bold;">' + v + '</span>';
				}
			},
			{
				header: '✓', dataIndex: 'verified', width: 40, align: 'center',
				filter: {type: 'boolean'},
				editor: {xtype: 'checkbox'},
				renderer: function (v) {
					return v ? '<span class="limas-text-success">✓</span>' : '';
				}
			},
			{
				header: i18n('Usage'), dataIndex: 'usageCount', width: 70, align: 'right',
				filter: {type: 'number'}
			},
			{
				header: i18n('Shortname'), dataIndex: 'shortname', flex: 1,
				filter: {type: 'string'}
			}
		];

		this.mergeButton = Ext.create('Ext.button.Button', {
			text: i18n('Merge selected →'),
			iconCls: 'fugue-icon arrow-join',
			tooltip: i18n('Set the same canonical name on all selected rows. Useful for collapsing several rawNames onto one canonical (e.g. "Package / Case" + "Case" → "Case/Package").'),
			disabled: true,
			handler: this.onMergeClick,
			scope: this
		});

		this.verifyButton = Ext.create('Ext.button.Button', {
			text: i18n('Mark verified'),
			iconCls: 'web-icon accept',
			disabled: true,
			handler: this.onVerifyClick,
			scope: this
		});

		this.callParent();

		// Limas.EditorGrid built the top toolbar with [Add(hidden), Delete,
		// → fill, SearchField]. Slip the merge + verify buttons in at the
		// front so the SearchField stays on the right with its built-in
		// magnifier + X triggers.
		if (this.topToolbar) {
			this.topToolbar.insert(0, this.mergeButton);
			this.topToolbar.insert(1, this.verifyButton);
		}

		this.getSelectionModel().on('selectionchange', this.onSelectChange, this);

		// Default sort — most-used unverified rows first so the operator can promote them in descending impact order
		this.store.sort([
			{property: 'verified', direction: 'ASC'},
			{property: 'usageCount', direction: 'DESC'}
		]);
	},

	onSelectChange: function (sm, selections) {
		this.mergeButton.setDisabled(selections.length < 2);
		this.verifyButton.setDisabled(selections.length === 0);
	},

	onMergeClick: function () {
		let sel = this.getSelectionModel().getSelection();
		if (sel.length < 2) return;
		// Default target = canonical of the first selected. User can edit.
		let suggested = sel[0].get('canonicalName');
		Ext.Msg.prompt(
			i18n('Merge canonical'),
			Ext.String.format(i18n('Set canonical for {0} selected rows:'), sel.length),
			(btn, value) => {
				if (btn !== 'ok' || !value || !value.trim()) return;
				sel.forEach(rec => {
					rec.set('canonicalName', value.trim());
					rec.set('verified', true);
					rec.set('source', 'user');
					rec.save();
				});
			},
			this,
			false,
			suggested
		);
	},

	onVerifyClick: function () {
		let sel = this.getSelectionModel().getSelection();
		sel.forEach(rec => {
			rec.set('verified', true);
			rec.save();
		});
	}
});
