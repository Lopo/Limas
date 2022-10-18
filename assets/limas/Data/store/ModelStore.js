Ext.define('Limas.Data.Store.ModelStore', {
	extend: 'Ext.data.Store',
	storeId: 'ModelStore',

	fields: [
		{
			name: 'model', type: 'string'
		}, {
			name: 'description', type: 'string'
		}
	],

	statics: {
		addModel: function (model, description) {
			Ext.StoreManager.lookup('ModelStore').add({
				model: model,
				description: description
			});
		}
	}
});
Ext.create('Limas.Data.Store.ModelStore');
