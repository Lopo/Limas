Ext.define('Limas.StorageLocationNavigation', {
	extend: 'Ext.panel.Panel',

	layout: 'border',

	/**
	 * @var {Ext.data.Store}
	 */
	store: null,
	verticalLayout: false,
	dragAndDrop: true,
	categoryEditActions: true,
	itemEditActions: true,
	editItemAsObject: false,

	initComponent: function () {
		let gridConfig = {
			xtype: 'limas.StorageLocationGrid',
			resizable: true,
			split: true,
			titleProperty: 'name',
			store: this.store
		};

		if (this.verticalLayout) {
			gridConfig.region = 'east';
			gridConfig.width = '75%';
		} else {
			gridConfig.region = 'south';
			gridConfig.height = '50%';
		}

		if (this.dragAndDrop) {
			gridConfig.viewConfig = {
				plugins: {
					ddGroup: 'StorageLocationTree',
					ptype: 'gridviewdragdrop',
					enableDrop: false
				}
			};

			gridConfig.enableDragDrop = true;
		}

		gridConfig.enableEditing = this.itemEditActions;
		gridConfig.editItemAsObject = this.editItemAsObject;

		this.items = [
			{
				xtype: 'limas.StorageLocationTree',
				region: 'center',
				categoryEditActions: this.categoryEditActions
			},
			gridConfig
		];

		this.callParent(arguments);

		this.getTree().on('itemclick', this.onCategoryClick, this);
		this.getGrid().on('storageLocationMultiAdd', this.onMultiAddStorageLocation, this);
		this.getGrid().on('itemAdd', this.onAddStorageLocation, this);
		this.getGrid().on('itemDelete', function (id) {
			this.fireEvent('itemDelete', id);
		}, this);
		this.getGrid().on('itemEdit', function (id) {
			this.fireEvent("itemEdit", id);
		}, this);
	},
	getGrid: function () {
		return this.down('limas\\.StorageLocationGrid');
	},
	getTree: function () {
		return this.down('limas\\.StorageLocationTree');
	},
	setSearchValue: function (val) {
		let searchField = this.getGrid().searchField;
		searchField.setValue(val);
		searchField.startSearch();
	},
	/**
	 * Applies the category filter to the store when a category is selected
	 *
	 * @param {Ext.tree.View} tree The tree view
	 * @param {Ext.data.Model} record the selected record
	 */
	onCategoryClick: function (tree, record) {
		this.setCategoryFilter(record);
	},
	setCategoryFilter: function (record) {
		let filter = Ext.create('Limas.util.Filter', {
			property: 'category',
			operator: 'IN',
			value: this.getChildrenIds(record)
		});

		this.store.addFilter(filter);
	},
	/**
	 * Returns the ID for this node and all child nodes
	 *
	 * @param {Ext.data.Model} The node
	 * @return Array
	 */
	getChildrenIds: function (node) {
		let childNodes = [node.getId()];
		if (node.hasChildNodes()) {
			for (let i = 0; i < node.childNodes.length; i++) {
				childNodes = childNodes.concat(this.getChildrenIds(node.childNodes[i]));
			}
		}
		return childNodes;
	},
	/**
	 * Called when a storage location is about to be added. This prepares the to-be-edited record with the proper category id.
	 */
	onAddStorageLocation: function () {
		let selection = this.getTree().getSelection(),
			category;
		if (selection.length === 0) {
			category = this.getTree().getRootNode().firstChild.getId();
		} else {
			let item = selection.shift();
			category = item.getId();
		}
		this.fireEvent('itemAdd', {
			category: category
		});
	},
	/**
	 * Called when a storage location is about to be added. This prepares the to-be-edited record with the proper category id.
	 */
	onMultiAddStorageLocation: function () {
		let selection = this.getTree().getSelection(),
			category;
		if (selection.length === 0) {
			category = this.getTree().getRootNode().firstChild.getId();
		} else {
			let item = selection.shift();
			category = item.getId();
		}

		Ext.create('Limas.StorageLocationMultiCreateWindow', {
			category: category,
			listeners: {
				destroy: {
					fn: this.onMultiCreateWindowDestroy,
					scope: this
				}
			}
		})
			.show();

	},
	/**
	 * Reloads the store after the multi-create window was closed
	 */
	onMultiCreateWindowDestroy: function () {
		this.store.load();
	},
	/**
	 * Triggers a reload of the store when an edited record affects the store
	 */
	syncChanges: function () {
		this.getGrid().getStore().load();
	},
	/**
	 * Returns the selection model of the storage location grid
	 * @return {Ext.selection.Model} The selection model
	 */
	getSelectionModel: function () {
		return this.getGrid().getSelectionModel();
	}
});
