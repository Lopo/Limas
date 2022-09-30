Ext.define('Limas.PartCategoryTree', {
	extend: 'Limas.CategoryEditorTree',
	alias: 'widget.PartCategoryTree',

	viewConfig: {
		plugins: {
			ptype: 'treeviewdragdrop',
			sortOnDrop: true,
			ddGroup: 'PartTree'
		}
	},
	categoryModel: 'Limas.Entity.PartCategory',
	rootVisible: false,

	initComponent: function () {
		this.store = Ext.create('Limas.Data.store.PartCategoryStore');

		this.callParent();

		this.syncButton = Ext.create('Ext.button.Button', {
			tooltip: i18n('Reveal Category for selected part'),
			iconCls: 'fugue-icon arrow-split-180',
			handler: Ext.bind(function () {
				this.fireEvent('syncCategory');
			}, this),
			disabled: true
		});
		this.toolbar.add(['->', this.syncButton]);
	},
	listeners: {
		foreignModelDrop: function (records, target) {
			for (let i in records) {
				switch (Ext.getClassName(records[i])) {
					case 'Limas.Entity.Part':
						records[i].setCategory(target);
						records[i].save();
						break;
					case 'Limas.Entity.PartCategory':
						records[i].callPutAction('move', {parent: target.getId()}, Ext.bind(function () {
							this.store.load();
						}, this));
						break;
				}
			}
		}
	}
});
