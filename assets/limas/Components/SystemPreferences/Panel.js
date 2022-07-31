Ext.define('Limas.Components.SystemPreferences.Panel', {
	extend: 'Limas.Components.Preferences.Panel',
	title: i18n('System Preferences'),

	getSettingClasses: function () {
		return [
			'Limas.Components.SystemPreferences.Preferences.FulltextSearch',
			'Limas.Components.SystemPreferences.Preferences.RequiredPartFields',
			'Limas.Components.SystemPreferences.Preferences.RequiredPartManufacturerFields',
			'Limas.Components.SystemPreferences.Preferences.RequiredPartDistributorFields',
			'Limas.Components.SystemPreferences.Preferences.BarcodeScannerConfiguration',
			'Limas.Components.SystemPreferences.Preferences.ActionsConfiguration'
		];
	},
	statics: {
		iconCls: 'fugue-icon gear',
		title: i18n('System Preferences'),
		closable: true,
		menuPath: [{text: i18n('System')}]
	}
});
