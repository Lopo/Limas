Ext.define('Limas.FootprintNavigation', {
	extend: 'Ext.panel.Panel',

	layout: 'border',

	/**
	 * @var {Ext.data.Store}
	 */
	store: null,

	initComponent: function () {
		this.items = [
			{
				xtype: 'limas.FootprintTree',
				region: 'center',
				rootVisible: false
			}, {
				xtype: 'limas.FootprintGrid',
				resizable: true,
				split: true,
				store: this.store,
				region: 'south',
				height: '50%',
				titleProperty: "name",
				viewConfig: {
					plugins: {
						ddGroup: 'FootprintCategoryTree',
						ptype: 'gridviewdragdrop',
						enableDrop: false
					}
				},
				enableDragDrop: true,
			}
		];

		this.callParent(arguments);

		this.down('limas\\.FootprintTree').on('itemclick', this.onCategoryClick, this);
		this.down('limas\\.FootprintGrid').on('itemAdd', this.onAddFootprint, this);
		this.down('limas\\.FootprintGrid').on('itemDelete', function (id) {
				this.fireEvent("itemDelete", id);
			}, this
		);
		this.down('limas\\.FootprintGrid').on('itemEdit', function (id) {
				this.fireEvent('itemEdit', id);
			}, this
		);
	},
	/**
	 * Applies the category filter to the store when a category is selected
	 *
	 * @param {Ext.tree.View} tree The tree view
	 * @param {Ext.data.Model} record the selected record
	 */
	onCategoryClick: function (tree, record) {
		this.store.addFilter(Ext.create('Limas.util.Filter', {
			property: 'category',
			operator: 'IN',
			value: this.getChildrenIds(record)
		}));
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
	 * Called when a footprint is about to be added. This prepares the to-be-edited record with the proper category id.
	 */
	onAddFootprint: function () {
		let selection = this.down('limas\\.FootprintTree').getSelection(),
			category;
		if (selection.length === 0) {
			category = this.down('limas\\.FootprintTree').getRootNode().firstChild.getId();
		} else {
			category = selection.shift().getId();
		}

		this.fireEvent('itemAdd', {
			category: category
		});
	},
	/**
	 * Triggers a reload of the store when an edited record affects the store
	 */
	syncChanges: function () {
		this.down('limas\\.FootprintGrid').getStore().load();
	},
	/**
	 * Returns the selection model of the footprint grid
	 * @return {Ext.selection.Model} The selection model
	 */
	getSelectionModel: function () {
		'use strict';
		return this.down('limas\\.FootprintGrid').getSelectionModel();
	}
});
