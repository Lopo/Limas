Ext.define('Limas.Data.store.BarcodeScannerActionsStore', {
	extend: 'Ext.data.Store',
	fields: ['action', 'name', 'description'],
	constructor: function () {
		this.callParent(arguments);

		let actions = Limas.getApplication().getBarcodeScannerManager().getActions();
		for (let i = 0; i < actions.length; i++) {
			this.add({
				action: actions[i],
				name: Ext.ClassManager.get(actions[i]).actionName,
				description: Ext.ClassManager.get(actions[i]).actionDescription
			});
		}
	}
});
