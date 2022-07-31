Ext.define('Limas.JsonWithAssociations', {
	extend: 'Ext.data.writer.Json',
	alias: 'writer.jsonwithassociations',

	/**
	 * @cfg {Array} associations Which associations to include
	 */
	associations: [],
	writeRecordId: false,

	getRecordData: function (record) {
		let data = this.callParent(arguments);
		Ext.apply(data, record.getAssociatedData());
		return data;
	}
});
