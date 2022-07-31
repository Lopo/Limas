Ext.define('Limas.data.TreeReader', {
	extend: 'Ext.data.reader.Json',
	alias: 'reader.tree',

	getResponseData: function (response) {
		let data = this.callParent(arguments);
		return {
			children: data
		};
	}
});
