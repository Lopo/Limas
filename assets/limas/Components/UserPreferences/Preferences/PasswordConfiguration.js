Ext.define('Limas.Components.UserPreferences.Preferences.PasswordConfiguration', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	layout: 'card',

	initComponent: function () {
		this.oldPassword = Ext.create('Ext.form.field.Text', {
			inputType: 'password',
			name: 'password',
			labelWidth: 150,
			style: 'border-bottom: 1px solid grey; padding-bottom: 10px;',
			width: 300,
			fieldLabel: i18n('Current Password')
		});

		this.newPassword = Ext.create('Ext.form.field.Text', {
			style: 'margin-top: 10px',
			inputType: 'password',
			name: 'password',
			labelWidth: 150,
			width: 300,
			fieldLabel: i18n('New Password')
		});

		this.newPasswordConfirm = Ext.create('Ext.form.field.Text', {
			inputType: 'password',
			name: 'password',
			labelWidth: 150,
			width: 300,
			validator: Ext.bind(this.validatePassword, this),
			fieldLabel: i18n('New Password (Confirm)')
		});

		this.items = [
			{
				border: false,
				items: [
					this.oldPassword,
					this.newPassword,
					this.newPasswordConfirm
				]
			}, {
				border: false,
				html: i18n('You are authenticated via an external user provider, password changing is not available.')
			}
		];

		this.callParent();

		if (Limas.getApplication().getLoginManager().getUser().getProvider().get('editable') === false) {
			this.layout.setActiveItem(1);
		}
	},
	onSave: function () {
		if (this.getForm().isValid()) {
			let user = Limas.getApplication().getLoginManager().getUser();
			user.callPatchAction('changePassword', {
				'oldpassword': this.oldPassword.getValue(),
				'newpassword': this.newPassword.getValue()
			}, Ext.bind(this.onAfterPasswordChange, this));
		}
	},
	onAfterPasswordChange: function (opts, success) {
		if (success) {
			Ext.MessageBox.alert(i18n('Password successfully changed'),
				i18n('You need to re-login with the new password. Click OK to re-login.'), this.relogin, this);
		}
	},
	relogin: function () {
		Limas.getApplication().getLoginManager().logout();
		Limas.getApplication().getLoginManager().login();
	},
	validatePassword: function () {
		if (this.newPassword.getValue() !== this.newPasswordConfirm.getValue()) {
			return i18n("Passwords don't match");
		}
		return true;
	},
	statics: {
		iconCls: 'fugue-icon ui-text-field-password-green',
		title: i18n('Password'),
		menuPath: []
	}
});
