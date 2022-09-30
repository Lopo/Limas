Ext.define('Limas.CategoryEditorTree', {
	alias: 'widget.CategoryEditorTree',
	extend: 'Limas.CategoryTree',
	viewConfig: {
		plugins: {
			ptype: 'treeviewdragdrop',
			sortOnDrop: true
		}
	},
	hideHeaders: true,
	categoryModel: null,
	categoryService: null,
	categoryEditActions: true,

	initComponent: function () {
		this.columns = [{
			xtype: 'treecolumn',
			header: 'Name',
			dataIndex: 'name',
			flex: 1
		}];

		if (Limas.getApplication().getUserPreference('limas.categorytree.showdescriptions', false) === true) {
			this.columns.push({
				xtype: 'gridcolumn',
				header: 'Description',
				dataIndex: 'description',
				flex: 0.5
			});
		}
		this.createToolbar();

		this.callParent();

		this.getView().on('drop', Ext.bind(this.onCategoryDrop, this));
		this.getView().on('beforedrop', Ext.bind(this.onBeforeDrop, this));
		this.on('selectionchange', Ext.bind(this.onItemSelect, this));
	},
	onBeforeDrop: function (node, data, overModel, dropPosition, dropHandlers) {
		let draggedRecords = data.records,
			droppedOn = this.getView().getRecord(node);

		for (let draggedRecord in draggedRecords) {
			if (!(draggedRecord instanceof Limas.Data.HydraTreeModel)) {
				// Workaround for EXTJS-13725 where dropping of non-tree-models cause issues
				dropHandlers.cancelDrop();
			}
		}

		this.fireEvent('foreignModelDrop', draggedRecords, droppedOn);
	},
	onItemSelect: function (selected) {
		if (!selected.getCount()) {
			this.toolbarAddButton.disable();
			this.toolbarDeleteButton.disable();
			this.toolbarEditButton.disable();
		}

		this.toolbarAddButton.enable();
		this.toolbarEditButton.enable();
		this.toolbarDeleteButton.enable();

		let record = selected.getSelection()[0];
		if (!(record instanceof Limas.Data.HydraTreeModel)) {
			return;
		}
		if (record.parentNode !== null && record.parentNode.isRoot()) {
			this.toolbarDeleteButton.disable();
		}
		if (record.hasChildNodes()) {
			this.toolbarDeleteButton.disable();
		}
	},
	onCategoryDrop: function (node, data, overModel, dropPosition) {
		let draggedRecord = data.records[0];

		if (draggedRecord instanceof Limas.Data.HydraTreeModel) {
			let targetRecord = (dropPosition === 'after' || dropPosition === 'before')
				? overModel.parentNode
				: overModel
			;

			draggedRecord.callPutAction('move', {
				'parent': targetRecord.getId()
			});
		}
	},
	createToolbar: function () {
		this.toolbarExpandButton = Ext.create('Ext.button.Button', {
			iconCls: 'fugue-icon toggle-expand',
			tooltip: i18n('Expand All'),
			handler: this._onExpandClick,
			scope: this
		});

		this.toolbarCollapseButton = Ext.create('Ext.button.Button', {
			iconCls: 'fugue-icon toggle',
			tooltip: i18n('Collapse All'),
			handler: this._onCollapseClick,
			scope: this
		});

		this.toolbarReloadButton = Ext.create('Ext.button.Button', {
			iconCls: 'x-tbar-loading',
			tooltip: i18n('Reload'),
			handler: this._onReloadClick,
			scope: this
		});

		this.toolbarAddButton = Ext.create('Ext.button.Button', {
			tooltip: i18n('Add Category'),
			handler: Ext.bind(this.showCategoryAddDialog, this),
			iconCls: 'web-icon folder_add',
			disabled: true
		});

		this.toolbarDeleteButton = Ext.create('Ext.button.Button', {
			tooltip: i18n('Delete Category'),
			handler: Ext.bind(this.confirmCategoryDelete, this),
			iconCls: 'web-icon folder_delete',
			disabled: true
		});

		this.toolbarEditButton = Ext.create('Ext.button.Button', {
			tooltip: i18n('Edit Category'),
			handler: Ext.bind(this.showCategoryEditDialog, this),
			iconCls: 'web-icon folder_edit',
			disabled: true
		});

		let actions = [
			this.toolbarExpandButton,
			this.toolbarCollapseButton,
			this.toolbarReloadButton
		];
		if (this.categoryEditActions) {
			actions.push(
				{
					xtype: 'tbseparator'
				},
				this.toolbarAddButton,
				this.toolbarEditButton,
				this.toolbarDeleteButton
			);
		}
		this.toolbar = Ext.create('Ext.toolbar.Toolbar', {
			enableOverflow: true,
			dock: 'top',
			items: actions
		});

		Ext.apply(this, {
			dockedItems: [this.toolbar]
		});
	},
	_onReloadClick: function () {
		this.store.load();
	},
	_onExpandClick: function () {
		this.getRootNode().firstChild.expand(true);
	},
	_onCollapseClick: function () {
		this.getRootNode().firstChild.collapse(true);
	},
	confirmCategoryDelete: function () {
		Ext.Msg.confirm(i18n('Confirm Category Delete'),
			sprintf(i18n('Do you really wish to delete the category %s?'), this.getSelection()[0].get('name')),
			this.onCategoryDelete, this);
	},
	showCategoryAddDialog: function () {
		Ext.create('Limas.CategoryEditorWindow', {
			record: Ext.create(this.categoryModel),
			categoryModel: this.categoryModel,
			parentRecord: this.getSelection()[0],
			listeners: {
				save: Ext.bind(this.onUpdateRecord, this)
			}
		})
			.show();
	},
	showCategoryEditDialog: function () {
		Ext.create('Limas.CategoryEditorWindow', {
			record: this.getSelection()[0],
			parentRecord: null,
			categoryModel: this.categoryModel,
			listeners: {
				save: Ext.bind(this.onUpdateRecord, this)
			}
		})
			.show();
	},
	onUpdateRecord: function () {
		this.store.load();
	},
	onCategoryDelete: function (btn) {
		if (btn === 'yes') {
			this.getSelection()[0].erase();
		}
	}
});
