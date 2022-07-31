Ext.define('Limas.Actions.LogoutAction', {
	extend: 'Limas.Actions.BaseAction',

	execute: function () {
		Limas.getApplication().getLoginManager().logout();
	},
	statics: {
		iconCls: 'web-icon disconnect',
		title: i18n('Disconnect'),
		closable: true,
		menuPath: [{text: i18n('System')}]
	}
});
