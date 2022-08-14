Ext.define('Limas.data.field.Decimal', {
	extend: 'Ext.data.field.Number',

	alias: [
		'data.field.decimal'
	],

	isDecimal: true,
	numericType: 'decimal',

	serialize: function (value, record) {
		if (value != null) {
			return value.toString();
		}
		return null;
	},
	convert: function (v) {
		let t=typeof v;
		return this.callParent(arguments);
	}
});
