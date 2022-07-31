Ext.define('Limas.Auth.HTTPBasicAuthenticationProvider', {
	extend: 'Limas.Auth.AuthenticationProvider',

	/**
	 * @method add
	 * @inheritdoc Limas.Auth.AuthenticationProvider#getHeaders
	 */
	getHeaders: function () {
		return {
			'Authorization': 'Basic ' + Ext.util.Base64.encode(this.getUsername() + ':' + this.getPassword())
		};
	}
});
