Ext.define('Limas.Components.UserPreferences.Panel', {
	extend: 'Limas.Components.Preferences.Panel',
	title: i18n('System Preferences'),

	getSettingClasses: function () {
		return [
			'Limas.Components.UserPreferences.Preferences.TipOfTheDayConfiguration',
			'Limas.Components.UserPreferences.Preferences.FormattingConfiguration',
			'Limas.Components.UserPreferences.Preferences.DisplayConfiguration',
			'Limas.Components.UserPreferences.Preferences.StockConfiguration',
			'Limas.Components.UserPreferences.Preferences.PasswordConfiguration',
			'Limas.Components.UserPreferences.Preferences.OctoPartConfiguration'
		];
	},
	statics: {
		iconCls: 'fugue-icon gear',
		title: i18n('User Preferences'),
		closable: true,
		menuPath: [{text: i18n('System')}]
	}
});
