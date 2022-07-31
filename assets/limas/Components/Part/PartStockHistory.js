Ext.define('Limas.PartStockHistory', {
	extend: 'Limas.AbstractStockHistoryGrid',
	alias: 'widget.PartStockHistory',

	part: null,

	initComponent: function () {
		this.callParent();
		this.on('activate', this.onActivate, this);
	},
	onActivate: function () {
		var filter = Ext.create('Limas.util.Filter', {
			property: 'part',
			operator: '=',
			value: this.part
		});

		this.store.clearFilter(true);
		this.store.addFilter(filter);
	}
});
