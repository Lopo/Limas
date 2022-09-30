Ext.define('Limas.FootprintTree', {
	extend: 'Limas.CategoryEditorTree',
	alias: 'widget.FootprintTree',
	xtype: 'limas.FootprintTree',
	viewConfig: {
		plugins: {
			ptype: 'treeviewdragdrop',
			sortOnDrop: true,
			ddGroup: 'FootprintCategoryTree'
		}
	},
	folderSort: true,

	categoryModel: 'Limas.Entity.FootprintCategory',

	initComponent: function () {
		this.store = Ext.create('Limas.Data.store.FootprintCategoryStore');
		this.callParent();
	},

	listeners: {
		'foreignModelDrop': function (records, target) {
			for (let i in records) {
				switch (Ext.getClassName(records[i])) {
					case 'Limas.Entity.Footprint':
						records[i].setCategory(target);
						records[i].save({
							success: function () {
								if (records[i].store && records[i].store.reload) {
									records[i].store.reload();
								}
							}
						});
						break;
					case 'Limas.Entity.FootprintCategory':
						records[i].callPutAction('move', {parent: target.getId()}, Ext.bind(function () {
							this.store.load();
						}, this));
						break;
				}
			}
		}
	}
});
