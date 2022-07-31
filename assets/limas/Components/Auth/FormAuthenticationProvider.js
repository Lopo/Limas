Ext.define('Limas.Auth.FormAuthenticationProvider', {
	extend: 'Limas.Auth.AuthenticationProvider',

	// getHeaders: function () {
	// 	return {
	// 		// 'Content-Type': 'application/x-www-form-urlencoded'
	// 		// 'content-type': 'text/html'
	// 	}
	// },
	authenticate: function () {
		Limas.Entity.User.callJsonCollectionAction('login',
			{
				username: this.getUsername(),
				password: this.getPassword()
			},
			Ext.bind(this.onLogin, this),
			true
		);
	}
});
