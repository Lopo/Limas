Ext.define('Limas.DistributorEditor', {
	extend: 'Limas.Editor',
	alias: 'widget.DistributorEditor',
	items: [
		{
			xtype: 'textfield',
			name: 'name',
			fieldLabel: i18n('Distributor')
		}, {
			xtype: 'checkbox',
			name: 'enabledForReports',
			boxLabel: i18n('Use this distributor for price calculations in project reports'),
			hideEmptyLabel: false
		}, {
			xtype: 'textarea',
			name: 'address',
			fieldLabel: i18n('Address')
		}, {
			xtype: 'urltextfield',
			name: 'url',
			fieldLabel: i18n('Website')
		}, {
			xtype: 'textfield',
			name: 'skuurl',
			fieldLabel: i18n('SKU URL'),
			triggers: {
				help: {
					cls: 'x-form-trigger-help',
					handler: function () {
						Ext.Msg.alert(i18n('Help'), i18n("Enter the URL of the distributor's SKU URL. Use %s as a placeholder for the SKU. Example: http://de.farnell.com/product/dp/%s"));
					}
				}
			}
		}, {
			xtype: 'textfield',
			name: 'email',
			fieldLabel: i18n('Email')
		}, {
			xtype: 'textfield',
			name: 'phone',
			fieldLabel: i18n('Phone')
		}, {
			xtype: 'textfield',
			name: 'fax',
			fieldLabel: i18n('Fax')
		}, {
			xtype: 'textarea',
			name: 'comment',
			fieldLabel: i18n('Comment')
		}
	],
	saveText: i18n('Save Distributor')
});
