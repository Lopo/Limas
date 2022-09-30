Ext.define('Limas.Data.TreeReader', {
	extend: 'Ext.data.reader.Json',
	alias: 'reader.tree',

	getResponseData: function (response) {
		let data = this.callParent(arguments);
		return {
			children: data
		};
	}
});
