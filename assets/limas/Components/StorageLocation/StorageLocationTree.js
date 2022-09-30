Ext.define('Limas.StorageLocationTree', {
	extend: 'Limas.CategoryEditorTree',
	alias: 'widget.StorageLocationTree',
	xtype: 'limas.StorageLocationTree',
	viewConfig: {
		plugins: {
			ptype: 'treeviewdragdrop',
			sortOnDrop: true,
			ddGroup: 'StorageLocationTree'
		}
	},
	folderSort: true,

	categoryModel: 'Limas.Entity.StorageLocationCategory',

	initComponent: function () {
		this.store = Ext.create('Limas.Data.store.StorageLocationCategoryStore');
		this.callParent();
	},
	listeners: {
		'foreignModelDrop': function (records, target) {
			for (let i in records) {
				switch (Ext.getClassName(records[i])) {
					case 'Limas.Entity.StorageLocation':
						records[i].setCategory(target);
						records[i].save({
							success: function () {
								if (records[i].store && records[i].store.reload) {
									records[i].store.reload();
								}
							}
						});
						break;
					case 'Limas.Entity.StorageLocationCategory':
						records[i].callPutAction('move', {parent: target.getId()}, Ext.bind(function () {
							this.store.load();
						}, this));
						break;
				}
			}
		}
	}
});
