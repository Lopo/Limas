Ext.define('Limas.Components.Widgets.PreferencePlugin', {
	extend: 'Ext.plugin.Abstract',
	alias: 'plugin.preference',

	/**
	 * @var {String} Specifies the preference key to bind the component to
	 */
	preferenceKey: null,
	/**
	 * @var {String} Specifies if the preference is a system or user preference. Allowed values are "system" or "user"
	 */
	preferenceScope: 'system',
	preferenceDefault: null,
	pluginId: 'preference',

	init: function (cmp) {
		this.setCmp(cmp);

		cmp.on('beforerender', this.loadPreference, this);
	},
	loadPreference: function () {
		if (this.preferenceScope === 'system') {
			this.getCmp().setValue(Limas.getApplication().getSystemPreference(this.preferenceKey, this.preferenceDefault));
		} else {
			this.getCmp().setValue(Limas.getApplication().getUserPreference(this.preferenceKey, this.preferenceDefault));
		}
	},
	savePreference: function () {
		if (this.preferenceScope === 'system') {
			Limas.getApplication().setSystemPreference(this.preferenceKey, this.getCmp().getValue());
		} else {
			Limas.getApplication().setUserPreference(this.preferenceKey, this.getCmp().getValue());
		}
	}
});
