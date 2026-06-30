Ext.define('Limas.StockHistoryGrid', {
	extend: 'Limas.AbstractStockHistoryGrid',
	alias: 'widget.PartStockHistoryGrid',

	pageSize: 25,

	defineColumns: function () {
		this.callParent();

		this.columns.splice(2, 0, {
			header: i18n('Part'),
			renderer: function (val, q, rec) {
				let part = rec.getPart();
				return part !== null ? part.get('name') : '';
			},
			flex: 1,
			minWidth: 200
		});

		this.columns.splice(3, 0, {
			header: i18n('Storage Location'),
			renderer: function (val, q, rec) {
				let part = rec.getPart();
				if (part === null) {
					return '';
				}
				let location = part.getStorageLocation();
				return location !== null ? location.get('name') : '';
			},
			flex: 1,
			minWidth: 200
		});
	},
	initComponent: function () {
		this.callParent();

		this.on('activate', this.onActivate, this);
	},
	onActivate: function () {
		this.store.load();
	},
	statics: {
		iconCls: 'fugue-icon notebook',
		title: i18n('Stock History'),
		closable: true,
		menuPath: [{text: i18n('View')}]
	}
});
