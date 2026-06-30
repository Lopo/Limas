Ext.define('Limas.Components.Project.ProjectReportResultGrid', {
	extend: 'Limas.BaseGrid',

	features: [
		{
			ftype: 'summary'
		}
	],

	viewConfig: {
		markDirty: false
	},

	initComponent: function () {
		this.subGrid = {
			xtype: 'gridfoo',
			bind: {
				store: '{record.subParts}',
				parentRecord: '{record}'
			}
		};

		this.columns = [
			{
				header: i18n('Qty'),
				dataIndex: 'quantity',
				width: 100,
				renderers: [{
					rtype: 'projectReportQuantity',
					rendererConfig: {
						quantityField: 'quantity'
					}
				}]
			}, {
				header: i18n('Part Name'),
				renderers: [{
					rtype: 'projectReportMetaPart'
				}],
				flex: 2
			}, {
				header: i18n('Part Description'),
				dataIndex: 'part.description',
				flex: 2
			}, {
				header: i18n('Remarks'),
				dataIndex: 'remarks',
				renderers: [{
					rtype: 'projectReportRemarks'
				}],
				flex: 1
			}, {
				header: i18n('Production Remarks'),
				dataIndex: 'productionRemarks',
				flex: 1
			}, {
				header: i18n('Projects'),
				dataIndex: 'projectNames',
				flex: 1
			}, {
				header: i18n('Storage Location'),
				dataIndex: 'part.storageLocation.name',
				width: 100
			}, {
				header: i18n('Available'),
				dataIndex: 'part.stockLevel',
				renderers: [{
					rtype: 'projectReportMetaPartAvailability'
				}],
				width: 75
			}, {
				header: i18n('Distributor'),
				dataIndex: 'distributor',
				renderers: [{
					rtype: 'objectField',
					rendererConfig: {
						displayField: 'distributor.name'
					}
				}],
				flex: 1,
				editor: {
					xtype: 'DistributorComboBox',
					returnObject: true,
					triggerAction: 'query',
					ignoreQuery: true,
					forceSelection: true,
					editable: false
				}
			}, {
				header: i18n('Distributor Order Number'),
				dataIndex: 'distributorOrderNumber',
				flex: 1,
				editor: {
					xtype: 'textfield'
				}
			}, {
				header: i18n('Item Price'),
				dataIndex: 'part.averagePrice',
				renderers: [{
					rtype: 'currency'
				}],
				width: 100
			}, {
				header: i18n('Sum'),
				dataIndex: 'itemSum',
				renderers: [{
					rtype: 'currency'
				}],
				summaryType: 'sum',
				summaryRenderer: Limas.getApplication().formatCurrency,
				width: 100
			}, {
				header: i18n('Order Amount'),
				// PK #404: was bound to `missing` (raw shortfall) — now to
				// `orderAmount`, the packagingUnit-rounded actual qty you
				// have to buy from the picked distributor. Falls back to
				// `missing` until a distributor is chosen.
				dataIndex: 'orderAmount',
				renderer: function (v, m, rec) {
					return (v !== undefined && v !== null && v !== '') ? v : rec.get('missing');
				},
				width: 100
			}, {
				header: i18n('Sum (Order)'),
				dataIndex: 'orderSum',
				renderers: [{
					rtype: 'currency'
				}],
				summaryType: 'sum',
				summaryRenderer: Limas.getApplication().formatCurrency,
				width: 100
			}
		];

		this.nextMetaPart = Ext.create('Ext.button.Button', {
			text: i18n('Next Meta-Part'),
			iconCls: 'limas-icon bricks_down',
			listeners: {
				click: this.jumpToNextMetaPart,
				scope: this
			}
		});

		this.previousMetaPart = Ext.create('Ext.button.Button', {
			text: i18n('Previous Meta-Part'),
			iconCls: 'limas-icon bricks_up',
			listeners: {
				click: this.jumpToPreviousMetaPart,
				scope: this
			}
		});

		this.removeStockButton = Ext.create('Ext.button.Button', {
			text: i18n('Remove parts from stock'),
			iconCls: 'fugue-icon notification-counter-03',
			listeners: {
				click: this.onStockRemovalClick,
				scope: this
			}
		});

		this.saveReportButton = Ext.create('Ext.button.Button', {
			text: i18n('Save Project Report'),
			iconCls: 'fugue-icon notification-counter-04',
			listeners: {
				click: this.onSaveReportClick,
				scope: this
			}
		});

		this.autoFillButton = Ext.create('Ext.button.Button', {
			text: i18n('Auto-Fill Distributors'),
			iconCls: 'fugue-icon notification-counter-02',
			listeners: {
				click: this.onAutoFillClick,
				scope: this
			}
		});

		this.rowExpander = new Limas.Components.ProjectReport.MetaPartRowExpander({
			widget: this.subGrid
		});

		this.editing = Ext.create('Ext.grid.plugin.CellEditing', {
			clicksToEdit: 1,
			listeners: {
				beforeedit: this.onBeforeEdit,
				edit: this.onEdit,
				scope: this
			}
		});

		this.plugins = [
			this.rowExpander, this.editing
		];

		this.bbar = [
			this.autoFillButton,
			this.removeStockButton,
			this.saveReportButton,
			{xtype: 'tbseparator'},
			this.nextMetaPart,
			this.previousMetaPart,
			{xtype: 'tbseparator'},
			Ext.create('Limas.Exporter.GridExporterButton', {
				itemId: 'export',
				genericExporter: false,
				tooltip: i18n('Export'),
				iconCls: 'fugue-icon application-export'
			}),
			Ext.create('Limas.Components.Grid.GridPresetButton', {
				grid: this
			})
		];

		this.callParent(arguments);
	},
	/**
	 * Called when the distributor field is about to be edited
	 *
	 * Filters the distributor list and show only distributors which are assigned to the particular item
	 */
	onBeforeEdit: function (e, context) {
		if (context.field !== 'distributor') {
			return;
		}

		let distributors = context.record.getPart().distributors(),
			filterIds = [];
		for (let i = 0; i < distributors.count(); i++) {
			if (distributors.getAt(i).getDistributor().get('enabledForReports') === false) {
				continue;
			}
			if (distributors.getAt(i).get('ignoreForReports')) {
				continue;
			}

			filterIds.push(distributors.getAt(i).getDistributor().getId());
		}

		let filter = Ext.create('Limas.util.Filter', {
			property: '@id',
			operator: 'in',
			value: filterIds
		});

		context.column.getEditor().store.clearFilter();
		context.column.getEditor().store.addFilter(filter);
	},
	onSaveReportClick: function () {
		Ext.Msg.prompt(
			i18n('Project Report Name'),
			i18n('Please enter the project report name:'),
			this.doSaveReport,
			this,
			false,
			this.projectReport.get('name')
		);
	},
	doSaveReport: function (button, value) {
		if (button === 'ok') {
			this.projectReport.set('name', value);
			this.projectReportManager.doSaveProjectReport();
		}
	},
	/**
	 * Removes all parts in the project view
	 */
	onStockRemovalClick: function () {
		if (this.hasMetaParts()) {
			Ext.Msg.alert(i18n('Unassigned Meta-Parts'), i18n('You have unassigned meta-parts. In order to remove parts from stock, you need to assign the meta-parts first.'));
			return;
		}

		Ext.Msg.confirm(i18n('Remove parts from stock'), i18n('Do you really want to remove the parts in the project report from the stock?'), this.removeStocks, this);
	},
	jumpToPreviousMetaPart: function () {
		for (let i = this.getSelectedMetaPartStartIndex() - 1; i >= 0; i--) {
			if (this.selectMetaPart(i)) {
				break;
			}
		}
	},
	jumpToNextMetaPart: function () {
		for (let i = this.getSelectedMetaPartStartIndex() + 1; i < this.getStore().getCount(); i++) {
			if (this.selectMetaPart(i)) {
				break;
			}
		}
	},
	selectMetaPart: function (index) {
		let record = this.getStore().getAt(index);
		if (record.get('metaPart')) {
			this.ensureVisible(record);
			this.getSelectionModel().select(record);
			return true;
		}

		return false;
	},
	getSelectedMetaPartStartIndex: function () {
		let selection = this.getSelection();

		if (selection.length === 1) {
			return this.getStore().indexOf(selection[0]);
		}
		return 0;
	},

	removeStocks: function (btn) {
		if (btn === 'yes') {
			let store = this.getStore(),
				removals = [];

			for (let i = 0; i < store.count(); i++) {
				let item = store.getAt(i);

				removals.push({
					part: item.getPart().getId(),
					amount: item.get('quantity'),
					comment: item.getReport().reportProjects().getFieldValues('project.name').join(', '),
					lotNumber: item.projectParts().getFieldValues('lotNumber').join(', '),
					projects: [] // item.getReport().reportProjects()
				});
			}

			Limas.Entity.Part.callPostCollectionAction('massRemoveStock',
				{
					removals: Ext.encode(removals),
					projects: Ext.encode(this.getProjectsToReport())
				},
				function (options, success) {
					if (success) {
						Ext.Msg.alert(
							i18n('Stock Removal Complete'),
							i18n('Removed stock and created a new project run.')
						);
					}
				}
			);
		}
	},
	onEdit: function (editor, context) {
		if (context.field === 'distributor' && context.record.getDistributor() !== null) {
			let partDistributors = context.record.getPart().distributors();

			for (let i = 0; i < partDistributors.count(); i++) {
				if (partDistributors.getAt(i).getDistributor().getId() === context.record.getDistributor().getId()) {
					let pd = partDistributors.getAt(i);
					context.record.set('itemPrice', pd.get('price'));
					context.record.set('distributorOrderNumber', pd.get('orderNumber'));
					this.applyOrderSizing(context.record, pd);
					context.record.set('itemSum', context.record.get('quantity') * context.record.get('itemPrice'));
				}
			}
		}
	},
	/**
	 * Compute the actual to-buy quantity + total order cost from the picked
	 * distributor's packagingUnit. When packagingUnit > 1 you can't buy
	 * exactly `missing`; you're forced up to the next pack boundary, and the
	 * order cost should reflect that (PK #404).
	 */
	applyOrderSizing: function (record, partDistributor) {
		let needed = Math.max(0, parseInt(record.get('missing'), 10) || 0);
		let price = parseFloat(record.get('itemPrice')) || 0;
		let packagingUnit = parseInt(partDistributor.get('packagingUnit'), 10) || 1;
		let orderAmount = needed > 0 ? Math.ceil(needed / packagingUnit) * packagingUnit : 0;
		record.set('orderAmount', orderAmount);
		record.set('orderSum', orderAmount * price);
	},
	onAutoFillClick: function () {
		let partCount = this.getStore().getCount(),
			activeRecord;

		this.projectPartStack = [];

		for (let i = 0; i < partCount; i++) {
			activeRecord = this.getStore().getAt(i);
			this.projectPartStack.push(activeRecord);
		}

		this.processCheapestDistributorStack(this.projectPartStack.length);

		if (this.waitMessage instanceof Ext.window.MessageBox) {
			this.waitMessage.hide();
		}
	},
	processCheapestDistributorStack: function (totalCount) {
		if (this.projectPartStack.length === 0) {
			if (this.waitMessage instanceof Ext.window.MessageBox) {
				this.waitMessage.hide();
			}
			return;
		}
		this.displayWaitWindow(
			i18n('Processing distributors…'),
			(totalCount - this.projectPartStack.length) + ' / ' + totalCount,
			1 / totalCount * (totalCount - this.projectPartStack.length)
		);
		this.processCheapestDistributorForProjectPart(this.projectPartStack.shift());

		Ext.defer(this.processCheapestDistributorStack, 1, this, [totalCount]);
	},
	processCheapestDistributorForProjectPart: function (projectPart) {
		let needed = projectPart.get('missing');
		let cheapestDistributor = this.getCheapestDistributor(projectPart.getPart(), needed);
		if (cheapestDistributor !== null) {
			projectPart.setDistributor(cheapestDistributor.getDistributor());
			projectPart.set('distributorOrderNumber', cheapestDistributor.get('orderNumber'));
			projectPart.set('itemPrice', cheapestDistributor.get('price'));
			this.applyOrderSizing(projectPart, cheapestDistributor);
			projectPart.set('itemSum', projectPart.get('quantity') * projectPart.get('itemPrice'));
		}
	},
	/**
	 * Pick the distributor with the lowest TOTAL order cost for `needed` units —
	 * not the lowest per-unit price. Distributors with a packagingUnit > 1 force
	 * you to over-buy; a cheaper per-unit price can still lose to a slightly
	 * pricier distributor selling in smaller packs. PK #887.
	 */
	getCheapestDistributor: function (part, needed) {
		let cheapestDistributor = null,
			lowestCost = 0,
			firstPositive = true;

		needed = Math.max(1, needed || 1);

		for (let j = 0; j < part.distributors().count(); j++) {
			let activeDistributor = part.distributors().getAt(j);
			if (activeDistributor.getDistributor().get('enabledForReports') === false) {
				continue;
			}
			if (activeDistributor.get('ignoreForReports') === true) {
				continue;
			}

			let price = parseFloat(activeDistributor.get('price'));
			if (price === 0 || isNaN(price)) {
				continue;
			}

			let packagingUnit = parseInt(activeDistributor.get('packagingUnit'), 10) || 1;
			let unitsToBuy = Math.ceil(needed / packagingUnit) * packagingUnit;
			let cost = unitsToBuy * price;

			if (firstPositive || cost < lowestCost) {
				lowestCost = cost;
				cheapestDistributor = activeDistributor;
				firstPositive = false;
			}
		}

		return cheapestDistributor;
	},
	hasMetaParts: function () {
		let record;
		for (let i = 0; i < this.getStore().getCount(); i++) {
			record = this.getStore().getAt(i);
			if (record.get('metaPart')) {
				return true;
			}
		}

		return false;
	},
	displayWaitWindow: function (text, description, value) {
		this.waitMessage = Ext.MessageBox.show({
			msg: text,
			title: i18n('Applying distributors…'),
			progressText: description,
			progress: true,
			width: 300
		});

		this.waitMessage.updateProgress(value);
	},
	setProjectsToReport: function (projects) {
		this.reportedProjects = projects;
	},
	getProjectsToReport: function () {
		return this.reportedProjects;
	}
});
