Ext.define('Limas.ServiceCall', {
	extend: 'Ext.util.Observable',

	service: null,
	call: null,

	sHandler: null,
	parameters: {},
	loadMessage: null,
	anonymous: false,

	constructor: function (service, call) {
		this.setService(service);
		this.setCall(call);
		this.parameters = {};
	},

	/**
	 * <p>This method activates anonymous mode.</p>
	 * <p>Anonymous mode defines that the service is called without passing a valid session. Usually, the only anonymous call is to authenticate a user.</p>
	 */
	enableAnonymous: function () {
		this.anonymous = true;
	},
	/**
	 * <p>This method deactivates anonymous mode.</p>
	 */
	disableAnonymous: function () {
		this.anonymous = false;
	},
	setService: function (service) {
		this.service = service;
	},
	setCall: function (call) {
		this.call = call;
	},
	setParameter: function (parameter, value) {
		this.parameters[parameter] = value;
	},
	setParameters: function (obj) {
		Ext.apply(this.parameters, obj);
	},
	setLoadMessage: function (message) {
		this.loadMessage = message;
	},
	setHandler: function (handler) {
		this.sHandler = handler;
	},
	doCall: function () {
		/* Update the status bar to indicate that the call is in progress. */
		Limas.getApplication().getStatusbar().startLoad(this.loadMessage);

		this.parameters._format = "json";

		let headers = {
			call: this.call,
			lang: Ext.getLocale()
		};

		if (!this.anonymous) {
			Ext.apply(headers, Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders());
		}

		Ext.Ajax.request({
			url: Limas.getBasePath() + '/' + this.service + '/' + this.call,
			success: Ext.bind(this.onSuccess, this),
			failure: Ext.bind(this.onError, this),
			method: 'POST',
			jsonData: this.parameters,
			headers: headers
		});
	},
	onSuccess: function (responseObj, options) {
		Limas.getApplication().getStatusbar().endLoad();

		try {
			var response = Ext.decode(responseObj.responseText);
		} catch (ex) {
			Limas.ExceptionWindow.showException(responseObj);
			return;
		}

		if (response.status == 'error') {
			this.displayError(response.exception);
			Limas.getApplication().getStatusbar().setStatus({
				text: this.getErrorMessage(response.exception),
				iconCls: 'x-status-error',
				clear: {
					useDefaults: true,
					anim: false
				}
			});
			return;
		}

		if (response.status == 'systemerror') {
			this.displaySystemError(response);
			Limas.getApplication().getStatusbar().setStatus({
				text: this.getErrorMessage(response),
				iconCls: 'x-status-error',
				clear: {
					useDefaults: true,
					anim: false
				}
			});

			return;
		}

		if (this.sHandler) {
			this.sHandler(response);
		}
	},
	onError: function (response, options) {
		Limas.ExceptionWindow.showException(response);
		Limas.getApplication().getStatusbar().endLoad();
	},
	displayError: function (obj) {
		Ext.Msg.show({
			title: i18n('Error'),
			msg: this.getErrorMessage(obj),
			buttons: Ext.MessageBox.OK,
			icon: Ext.MessageBox.ERROR
		});
	},
	getErrorMessage: function (obj) {
		return obj.message === ''
			? obj.exception
			: obj.message;
	},
	displaySystemError: function (obj) {
		let errorMsg = 'Error Message: ' + obj.message + '<br/>'
			+ 'Exception:' + obj.exception + '<br/>'
			+ 'Backtrace:<br/>' + str_replace('\n', '<br/>', obj.backtrace);

		Ext.Msg.maxWidth = 800;

		Ext.Msg.show({
			title: i18n('System Error'),
			msg: errorMsg,
			buttons: Ext.MessageBox.OK,
			icon: Ext.MessageBox.ERROR
		});
	}
});
