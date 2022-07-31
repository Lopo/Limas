Ext.define('Limas.Components.UserPreferences.Preferences.TipOfTheDayConfiguration', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		this.displayTipsOnLoginCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Display tips on login')
		});

		this.displayTipsOnLoginCheckbox.setValue(Limas.getApplication().getUserPreference('limas.tipoftheday.showtips') !== false);

		this.resetTipsButton = Ext.create('Ext.button.Button', {
			text: i18n('Mark all tips unread'),
			handler: this.onMarkAllTipsUnreadClick,
			scope: this
		});

		this.items = [
			this.displayTipsOnLoginCheckbox,
			this.resetTipsButton
		];

		this.callParent();
	},
	onMarkAllTipsUnreadClick: function () {
		Limas.Entity.TipOfTheDay.callPostCollectionAction('markAllTipsAsUnread', {}, function () {
				let msg = i18n('All tips have been marked as unread');
				Ext.Msg.alert(msg, msg);
			}
		);
	},
	onSave: function () {
		Limas.getApplication().setUserPreference('limas.tipoftheday.showtips',
			this.displayTipsOnLoginCheckbox.getValue());
	},
	statics: {
		iconCls: 'fugue-icon light-bulb',
		title: i18n('Tip of the Day'),
		menuPath: [{iconCls: 'fugue-icon ui-scroll-pane-image', text: i18n('User Interface')}]
	}
});
