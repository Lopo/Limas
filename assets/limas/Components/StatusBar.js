Ext.define('Limas.Statusbar', {
	extend: 'Ext.ux.statusbar.StatusBar',

	defaultText: i18n('Ready.'),
	defaultIconCls: 'x-status-valid',
	iconCls: 'x-status-valid',
	autoClear: 3000,
	initComponent: function () {
		this.connectionButton = new Limas.ConnectionButton();
		this.connectionButton.on('click', this.onConnectionButtonClick, this);
		this.timeDisplay = Ext.create('Limas.TimeDisplay');
		this.currentUserDisplay = Ext.create('Ext.toolbar.TextItem');

		this.showMessageLog = Ext.create('Ext.Button', {
			iconCls: 'web-icon application_osx_terminal',
			cls: 'x-btn-icon',
			handler: function () {
				Limas.getApplication().toggleMessageLog();
			}
		});

		this.systemNoticeButton = Ext.create('Limas.SystemNoticeButton', {
			hidden: true
		});

		Ext.apply(this, {
			items: [
				this.currentUserDisplay,
				{xtype: 'tbseparator'},
				this.timeDisplay,
				{xtype: 'tbseparator'},
				this.showMessageLog,
				{xtype: 'tbseparator'},
				this.connectionButton,
				this.systemNoticeButton
			]
		});

		this.setDisconnected();

		this.callParent();
	},
	getConnectionButton: function () {
		return this.connectionButton;
	},
	setCurrentUser: function (username) {
		this.currentUserDisplay.setText(i18n('Logged in as') + ': ' + username);
	},
	startLoad: function (message) {
		if (message !== null) {
			this.showBusy({text: message, iconCls: 'x-status-busy'});
		} else {
			this.showBusy();
		}
	},
	endLoad: function () {
		this.clearStatus({useDefaults: true});
	},
	setConnected: function () {
		let user = Limas.getApplication().getLoginManager().getUser();

		this.setCurrentUser(user.get('username'));
		this.connectionButton.setConnected();
	},
	setDisconnected: function () {
		this.connectionButton.setDisconnected();
		this.currentUserDisplay.setText(i18n('Not logged in'));
	},
	onConnectionButtonClick: function () {
		let loginManager = Limas.getApplication().getLoginManager();
		if (loginManager.isLoggedIn()) {
			loginManager.logout();
		} else {
			loginManager.login();
		}
	}
});
