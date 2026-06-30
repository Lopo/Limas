/**
 * Side-by-side editor for a single FootprintAlias row. Most edits happen
 * inline in the grid; this form is for the occasional flag-only edits
 * (verified / source) and the per-row Footprint assignment.
 */
Ext.define('Limas.FootprintAliasEditor', {
	extend: 'Limas.Editor',
	alias: 'widget.FootprintAliasEditor',
	fieldDefaults: {
		labelAlign: 'top',
		anchor: '100%'
	},
	items: [
		{
			xtype: 'textfield',
			name: 'alias',
			fieldLabel: i18n('Raw alias'),
			readOnly: true,
			cls: 'limas-field-readonly'
		},
		{
			xtype: 'textfield',
			name: 'aliasNormalized',
			fieldLabel: i18n('Normalized'),
			readOnly: true,
			cls: 'limas-field-readonly'
		},
		{
			xtype: 'FootprintComboBox',
			name: 'footprint',
			fieldLabel: i18n('Canonical Footprint'),
			emptyText: i18n('— unassigned —'),
			returnObject: true
		},
		{
			xtype: 'combo',
			name: 'source',
			fieldLabel: i18n('Source'),
			store: ['auto', 'seed', 'user'],
			editable: false
		},
		{
			xtype: 'checkbox',
			name: 'verified',
			boxLabel: i18n('Verified (mapping confirmed)'),
			hideEmptyLabel: false
		},
		{
			xtype: 'numberfield',
			name: 'usageCount',
			fieldLabel: i18n('Usage count'),
			readOnly: true,
			cls: 'limas-field-readonly'
		}
	]
});
