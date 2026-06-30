/**
 * Admin grid for the InfoProvider aggregator's `ManufacturerAlias` table.
 * Lets the user review auto-discovered manufacturer name spellings, assign a
 * Manufacturer to unverified rows inline, and flip the verified flag.
 *
 * Sort defaults to (verified ASC, usageCount DESC) so the most-used
 * unverified rows surface at the top — those are the highest-ROI manual
 * mappings to promote.
 */
Ext.define('Limas.ManufacturerAliasGrid', {
	extend: 'Limas.EditorGrid',
	alias: 'widget.ManufacturerAliasGrid',

	addButton: false, // rows are auto-discovered by the aggregator
	plugins: ['gridfilters', {ptype: 'cellediting', clicksToEdit: 1}],

	searchFieldSystemPreference: 'limas.manufacturerAlias.searchFields',
	searchFieldSystemPreferenceDefaults: ['alias', 'aliasNormalized'],

	initComponent: function () {
		this.columns = [
			{
				header: i18n('Raw alias'), dataIndex: 'alias', flex: 2,
				filter: {type: 'string'}
			},
			{
				header: i18n('Normalized'), dataIndex: 'aliasNormalized', flex: 1,
				filter: {type: 'string'}
			},
			{
				header: i18n('Manufacturer'), dataIndex: 'manufacturer', flex: 2,
				filter: {type: 'string'},
				renderer: function (v, m, rec) {
					// returnObject combo writes an ExtJS Model into the cell;
					// v.name would be undefined. Prefer the model getter.
					if (rec && typeof rec.getManufacturer === 'function') {
						let mfr = rec.getManufacturer();
						if (mfr && typeof mfr.get === 'function') {
							return Ext.htmlEncode(mfr.get('name') || '');
						}
					}
					if (!v) {
						return '<i class="limas-text-muted">— unassigned —</i>';
					}
					if (v && typeof v.get === 'function') {
						return Ext.htmlEncode(v.get('name') || '');
					}
					if (typeof v === 'object' && v.name) {
						return Ext.htmlEncode(v.name);
					}
					return Ext.htmlEncode(String(v));
				},
				editor: {
					xtype: 'ManufacturerComboBox',
					returnObject: true,
					emptyText: i18n('— unassigned —')
				}
			},
			{
				header: i18n('Source'), dataIndex: 'source', width: 90,
				filter: {type: 'list', options: ['auto', 'seed', 'user']},
				renderer: function (v) {
					let cls = {seed: 'limas-text-link', auto: 'limas-text-warning', user: 'limas-text-success'};
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
			}
		];

		this.verifyButton = Ext.create('Ext.button.Button', {
			text: i18n('Mark verified'),
			iconCls: 'web-icon accept',
			disabled: true,
			handler: this.onVerifyClick,
			scope: this
		});

		this.callParent();

		if (this.topToolbar) {
			this.topToolbar.insert(0, this.verifyButton);
		}

		this.getSelectionModel().on('selectionchange', this.onSelectChange, this);

		this.store.sort([
			{property: 'verified', direction: 'ASC'},
			{property: 'usageCount', direction: 'DESC'}
		]);
	},

	onSelectChange: function (sm, selections) {
		this.verifyButton.setDisabled(selections.length === 0);
	},

	onVerifyClick: function () {
		let sel = this.getSelectionModel().getSelection();
		sel.forEach(rec => {
			rec.set('verified', true);
			rec.save();
		});
	}
});
