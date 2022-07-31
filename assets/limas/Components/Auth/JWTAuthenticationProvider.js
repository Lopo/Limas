Ext.define('Limas.Auth.JWTAuthenticationProvider', {
	extend: 'Limas.Auth.AuthenticationProvider',

	token: {
		raw: null,
		header: null, // {typ: 'JWT', alg: 'RS256', cty: 'JWT'}
		payload: null, // {iat: 1654755011, exp: 1654758611, roles: ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_USER'], username: 'admin', ip: '127.0.0.1', id: 1}
		secret: null,
	},
	refresh_token: null,

	getHeaders: function () {
		if (this.token.raw !== null) { // already authenticated
			return {
				Authorization: 'Bearer ' + this.token.raw
			};
		}
	},
	authenticate: function () {
		Limas.Entity.User.callJsonCollectionAction('jwt',
			{
				username: this.getUsername(),
				password: this.getPassword()
			},
			Ext.bind(this.onTokenRetrieved, this)
		);
	},
	/**
	 * Callback when the token was received
	 *
	 * @param {Object} options
	 * @param {Object} success
	 * @param {Object} response
	 */
	onTokenRetrieved: function (options, success, response) {
		if (response.status === 401) {
			this.fireEvent('authenticate', false);
			return;
		}

		this.token.raw = JSON.parse(response.responseText).token;
		let parts = this.token.raw.split('.');
		this.token.header = JSON.parse(decodeURIComponent(atob(parts[0])));
		this.token.payload = JSON.parse(decodeURIComponent(atob(parts[1])));
		this.token.secret = parts[2];

		this.refresh_token = JSON.parse(response.responseText).refresh_token;

		Limas.Entity.User.callGetCollectionAction(this.token.payload.id, {}, Ext.bind(this.onLogin, this), true);
	}
});
