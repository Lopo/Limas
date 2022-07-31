Ext.define('Limas.Widgets.EntityQueryPanel', {
	extend: 'Ext.panel.Panel',
	layout: 'border',
	items: [
		{
			title: i18n('Results'),
			xtype: 'grid',
			region: 'center',
			itemId: 'grid',
		}, {
			title: i18n('Available fields'),
			xtype: 'treepanel',
			region: 'east',
			width: 265,
			itemId: 'fieldTree',
			plugins: {
				ptype: 'cellediting',
				clicksToEdit: 1,
				pluginId: "cellediting",
			},
			split: true,
			viewConfig: {
				markDirty: false
			},
			store: {
				folderSort: true,
				sorters: [
					{
						property: 'text',
						direction: 'ASC'
					}
				]
			},
			columns: [
				{
					xtype: 'treecolumn', //this is so we know which column will show the tree
					text: i18n('Field'),
					dataIndex: 'text',
					flex: 3,
				}, {
					text: i18n('Index'),
					flex: 1,
					align: 'center',
					dataIndex: 'entityIndex',
					format: '0',
					editor: {
						xtype: 'numberfield',
						minValue: 0,
						maxValue: 99
					},
					renderer: function (val, md, record) {
						if (record.get('data') instanceof Object
							&& typeof (record.get('data').type) !== 'undefined'
							&& record.get('data').type !== 'onetomany'
						) {
							return '';
						}
						return record.get('entityIndex');
					}
				}
			],
			useArrows: true
		}
	],
	/**
	 * @var {Array} Contains the models already in the field tree
	 */
	visitedModels: [],
	/**
	 * @var {Array} All configured columns
	 */
	columns: [],
	/**
	 * @var {Ext.data.Store} The store
	 */
	store: null,

	initComponent: function () {
		this.callParent(arguments);
		this.visitedModels = [];

		let rootNode = this.down('#fieldTree').getRootNode();
		this.down('#fieldTree').on('itemdblclick', this.onTreeDblClick, this);
		rootNode.set('text', this.model.getName());

		let treeMaker = Ext.create('Limas.ModelTreeMaker.ModelTreeMaker');
		treeMaker.addIgnoreField('@id');
		treeMaker.make(rootNode, this.model, "", Ext.bind(this.appendFieldData, this), ['entityIndex']);

		rootNode.expand();
		rootNode.expand();

		this.store = Ext.create('Ext.data.Store', {
			model: this.model.getName(),
			autoLoad: true
		});

		this.down('#fieldTree').addDocked({
			xtype: 'toolbar',
			items: [
				{
					xtype: 'button',
					iconCls: 'web-icon add',
					handler: 'onAddColumn',
					scope: this
				},
				{
					xtype: 'button',
					iconCls: 'web-icon delete',
					handler: 'onRemoveColumn',
					scope: this
				}
			]
		});

		this.down('#fieldTree').getPlugin('cellediting').on('beforeedit', this.onBeforeEdit, this);
		this.down('#grid').addDocked(this.bottomToolbar);

		this.down('#grid').reconfigure(this.store, this.columns);
	},
	onBeforeEdit: function (editor, context) {
		if (context.record.get('data') !== null && context.record.get('data').type !== 'onetomany') {
			return false;
		}
	},
	/**
	 * @param {Ext.data.field.Field} The model
	 */
	appendFieldData: function (field, node) {
		node.set('entityIndex', 0);
	},
	/**
	 * Returns the parameters for the query string
	 * @return {Object} An object containing all parameters
	 */
	getParams: function () {
		let i, originalColumns, columns = [];
		originalColumns = this.down('#grid').getColumns();
		for (i = 0; i < originalColumns.length; i++) {
			columns.push(originalColumns[i].dataIndex);
		}

		return {
			itemsPerPage: 9999999,
			columns: Ext.encode(columns)
		};
	},
	/**
	 * Event handler for the add button
	 */
	onAddColumn: function () {
		if (!this.down('#fieldTree').getSelectionModel().hasSelection()) {
			return;
		}

		let record = this.down('#fieldTree').getSelectionModel().getSelection()[0];
		this.addColumn(record);
	},
	/**
	 * Event handler for the remove button
	 */
	onRemoveColumn: function () {
		if (!this.down('#fieldTree').getSelectionModel().hasSelection()) {
			return;
		}

		let record = this.down('#fieldTree').getSelectionModel().getSelection()[0];
		this.removeColumn(record);
	},
	/**
	 * Adds a specific column to the grid. Must be a record and has the "data" property defined.
	 *
	 * @param {Ext.data.Model} The record to process
	 */
	addColumn: function (record) {
		let columns,
			fieldPath = this.getFieldPath(record).join('.');

		if (this.hasColumn(fieldPath) || record.get('data').name === undefined) {
			return;
		}
		if (record.get('data').type !== 'field') {
			return;
		}

		columns = this.down('#grid').getColumns();

		this.syncColumns();

		this.columns.push({
			dataIndex: fieldPath,
			text: fieldPath,
			renderer: this.columnRenderer,
			scope: this.down('#grid')
		});

		this.down('#grid').reconfigure(this.store, this.columns);
	},
	getFieldPath: function (record) {
		let fieldPath = [];

		if (record.parentNode !== null && !record.parentNode.isRoot()) {
			fieldPath = this.getFieldPath(record.parentNode);
		}

		if (typeof (record.get('data')) === 'object' && record.get('data').type === 'onetomany') {
			fieldPath.push(record.get('text') + '[' + record.get('entityIndex') + ']');
		} else {
			fieldPath.push(record.get('text'));
		}

		return fieldPath;
	},
	/**
	 * Removes a specific column to the grid. Must be a record and has the "data" property defined.
	 *
	 * @param {Ext.data.Model} The record to process
	 */
	removeColumn: function (record) {
		let fieldPath = this.getFieldPath(record).join('.');

		if (!this.hasColumn(fieldPath) || record.get('data').name === undefined) {
			return;
		}

		this.syncColumns();

		for (let i = 0; i < this.columns.length; i++) {
			if (this.columns[i].dataIndex === fieldPath) {
				Ext.Array.removeAt(this.columns, i);
			}
		}
		this.down('#grid').reconfigure(this.store, this.columns);

	},
	/**
	 * Syncronizes the internal columns storage with the grid. The reason it is done that way is because we can't
	 * operate on the return value of getColumns() directly, as these are instanciated objects which get removed
	 * during a reconfigure operation.
	 */
	syncColumns: function () {
		let columns, i;
		this.columns = [];

		columns = this.down('#grid').getColumns();

		for (i = 0; i < columns.length; i++) {
			this.columns.push({
				dataIndex: columns[i].dataIndex,
				text: columns[i].text,
				renderer: this.columnRenderer,
				scope: this.down('#grid')
			});
		}
	},
	columnRenderer: function (value, metadata, record, rowIndex, colIndex) {
		let index = this.getColumns()[colIndex].dataIndex;
		return record.get(index);
	},
	/**
	 * Returns if a specific column exists in the grid.Must be a record and has the "data" property defined.
	 *
	 * @param {Ext.data.Model} The record to process
	 * @return {Boolean} true if the column exist, false otherwise
	 */
	hasColumn: function (name) {
		let columns = this.down('#grid').getColumns();
		for (let i = 0; i < columns.length; i++) {
			if (columns[i].dataIndex === name) {
				return true;
			}
		}

		return false;
	},
	/**
	 * Handles the double click on the tree. Adds the item if it doesn't exist, or remove it otherwise
	 *
	 * @param {Ext.tree.Tree} The tree panel
	 * @param {Ext.data.Model} The double clicked record
	 */
	onTreeDblClick: function (tree, record) {
		let fieldPath = this.getFieldPath(record).join('.');
		if (this.hasColumn(fieldPath)) {
			this.removeColumn(record);
		} else {
			this.addColumn(record);
		}
	}
});
