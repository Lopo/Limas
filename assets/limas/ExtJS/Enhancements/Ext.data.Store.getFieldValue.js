Ext.define('Limas.Data.Store', {
	override: 'Ext.data.Store',

	/**
	 * Retrieves a specific field from all records in the store
	 * @param field
	 * @returns {Array}
	 */
	getFieldValues: function (field) {
		let result = [];
		for (let i = 0; i < this.getCount(); i++) {
			result.push(this.getAt(i).get(field));
		}
		return result;
	}
});
