Ext.define('Limas.BarcodeScanner.Manager', {
	monitor: false,
	monitoredKeys: '',

	barcodeInputField: null,

	registerBarcodeScannerHotkey: function () {
		this.monitor = false;
		this.runnerTask = new Ext.util.DelayedTask(function () {
			this.stopKeyMonitoring();
		}, this);

		Ext.get(document).on('keydown', this.onKeyPress, this, {
			priority: 10000
		});
	},
	/**
	 * Stops monitoring and executes the action found in the intercepted keys
	 */
	stopKeyMonitoring: function () {
		this.monitor = false;
		this.runnerTask.cancel();

		this.executeAction(this.monitoredKeys);
		this.monitoredKeys = '';
	},
	/**
	 * Starts monitoring for input events, up to a configured timeout
	 */
	startKeyMonitoring: function () {
		this.monitoredKeys = '';
		this.monitor = true;
		this.runnerTask.delay(Limas.getApplication().getSystemPreference('limas.barcodeScanner.timeout', 500));
	},
	/**
	 * Intercepts keypresses when a barcode scanner hotkey was detected up to the configured timeout
	 */
	onKeyPress: function (e) {
		let hotKeyPressed = false,
			hotKey = Limas.getApplication().getSystemPreference('limas.barcodeScanner.key', '');

		if (hotKey === '') {
			return;
		}

		if (e.event.key === hotKey) {
			hotKeyPressed = true;
		}

		if (Limas.getApplication().getSystemPreference('limas.barcodeScanner.modifierCtrl', false)) {
			if (!e.ctrlKey) {
				hotKeyPressed = false;
			}
		}
		if (Limas.getApplication().getSystemPreference('limas.barcodeScanner.modifierShift', false)) {
			if (!e.shiftKey) {
				hotKeyPressed = false;
			}
		}
		if (Limas.getApplication().getSystemPreference('limas.barcodeScanner.modifierAlt', false)) {
			if (!e.altKey) {
				hotKeyPressed = false;
			}
		}

		if (hotKeyPressed) {
			this.startKeyMonitoring();
			return;
		}


		if (this.monitor) {
			if (Limas.getApplication().getSystemPreference('limas.barcodeScanner.enter', true)) {
				if (e.event.code == 'Enter') {
					this.stopKeyMonitoring();
					return;
				}
			}

			if (!e.isSpecialKey()) {
				this.monitoredKeys += e.event.key;
			}
			this.runnerTask.delay(
				Limas.getApplication().getSystemPreference('limas.barcodeScanner.timeout', 500));
			e.stopEvent();
		}
	},
	/**
	 * Returns a list of all class names which provide actions
	 *
	 * @return {Array} An array of action class names
	 */
	getActions: function () {
		return [
			'Limas.BarcodeScanner.Actions.SearchPart',
			'Limas.BarcodeScanner.Actions.AddRemoveStock',
			'Limas.BarcodeScanner.Actions.AddPart'
		];
	},
	/**
	 * Executes an action by parsing the input and deciding which action to execute
	 *
	 * @param {String} input The intercepted keys
	 */
	executeAction: function (input) {
		let actions = this.getActionsByInput(input);
		for (let i = 0; i < actions.length; i++) {
			if (actions[i] !== null) {
				actions[i].execute();
			}
		}
	},
	getActionsByInput: function (input) {
		let i, actions = Limas.getApplication().getSystemPreference('limas.barcodeScanner.actions', []),
			foundActions = [],
			barcodeScannerActionsStore = Ext.create('Ext.data.Store', {
				fields: ['code', 'action', 'configuration'],
				data: []
			}),
			actionStore = Ext.create('Limas.Data.store.BarcodeScannerActionsStore');

		for (i = 0; i < actions.length; i++) {
			let item = actions[i];
			barcodeScannerActionsStore.add({
				code: item.code,
				action: actionStore.findRecord('action', item.action),
				configuration: item.config
			});
		}

		barcodeScannerActionsStore.sort(
			function (data1, data2) {
				if (data1.get('code').length === data2.get('code').length) {
					return 0;
				}
				if (data1.get('code').length > data2.get('code').length) {
					return -1;
				}
				return 1;
			}
		);

		let barcodeScannerActions = barcodeScannerActionsStore.getData(),
			code, className, config;

		for (i = 0; i < barcodeScannerActions.getCount(); i++) {
			code = barcodeScannerActions.getAt(i).get('code');

			if (input.substr(0, code.length) === code) {
				className = barcodeScannerActions.getAt(i).get('action').get('action');
				config = barcodeScannerActions.getAt(i).get('configuration');

				foundActions.push(Ext.create(className, config, input.substr(code.length)));
			}
		}

		return foundActions;
	}
});
