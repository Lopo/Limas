/**
 * Side-by-side editor for a single ParameterAlias row. Most edits happen
 * inline in the grid; this form is for the corner cases where the user
 * wants to tweak source / shortname / vendor as well.
 */
Ext.define('Limas.ParameterAliasEditor', {
	extend: 'Limas.Editor',
	alias: 'widget.ParameterAliasEditor',
	// Narrow east panel (~420px); labels above fields use the vertical room
	// the host has instead of fighting for horizontal space against the default labelWidth=150
	fieldDefaults: {
		labelAlign: 'top',
		anchor: '100%'
	},
	items: [
		{
			xtype: 'textfield',
			name: 'rawName',
			fieldLabel: i18n('rawName'),
			readOnly: true,
			cls: 'limas-field-readonly'
		},
		{
			xtype: 'textfield',
			name: 'rawNameNormalized',
			fieldLabel: i18n('Normalized'),
			readOnly: true,
			cls: 'limas-field-readonly'
		},
		{
			xtype: 'textfield',
			name: 'canonicalName',
			fieldLabel: i18n('Canonical Name'),
			allowBlank: false
		},
		{
			xtype: 'textfield',
			name: 'shortname',
			fieldLabel: i18n('Octopart shortname'),
			emptyText: i18n('— optional —')
		},
		{
			xtype: 'textfield',
			name: 'vendor',
			fieldLabel: i18n('Vendor'),
			emptyText: i18n('— global (any vendor) —'),
			readOnly: true,
			cls: 'limas-field-readonly'
		},
		{
			xtype: 'combo',
			name: 'source',
			fieldLabel: i18n('Source'),
			store: ['octopart', 'vendor', 'auto', 'user'],
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
