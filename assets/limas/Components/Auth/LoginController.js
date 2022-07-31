Ext.define('Limas.Components.Auth.LoginController', {
	extend: 'Ext.app.ViewController',
	alias: 'controller.LoginController',

	login: function () {
		let view = this.getView();
		view.fireEvent('login', view.getUsername(), view.getPassword());
	},
	onEsc: function () {
	}
});
