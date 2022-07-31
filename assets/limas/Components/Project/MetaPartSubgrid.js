/**
 * This is a workaround to make grid linking possible
 */
Ext.define('Limas.Components.Project.MetaPartSubgrid', {
	extend: 'Ext.grid.Panel',
	xtype: 'gridfoo',

	parentRecord: null,
	setParentRecord: function (v) {
		this.parentRecord = v;
	},
	autoLoad: false,

	defaultListenerScope: true,
	columns: [{
		text: i18n('Use'),
		xtype: 'checkcolumn',
		listeners: {
			checkchange: 'onCheckStateChange'
		},
		dataIndex: 'use'
	}, {
		text: i18n('Part Name'),
		dataIndex: 'name'
	}, {
		text: i18n('Description'),
		dataIndex: 'description'
	}, {
		text: i18n('Production Remarks'),
		dataIndex: 'productionRemarks'
	}, {
		text: i18n('Storage Location'),
		renderer: function (v, m, r) {
			return r.get('storageLocation.name');
		}
	}, {
		text: i18n('Stock Level'),
		dataIndex: 'stockLevel',
		renderer: function (value, metaData, record) {
			return value + ' ' + record.getPartUnit().get('shortName');
		}
	}, {
		text: i18n('Stock to use'),
		dataIndex: 'stockToUse',
		editor: {
			field: {
				xtype: 'numberfield'
			}
		},
		renderer: function (value, metaData, record) {
			if (typeof (value) === 'undefined') {
				value = 0;
			}
			return value + ' ' + record.getPartUnit().get('shortName');
		}
	}],
	bbar: [
		{
			xtype: 'button',
			text: i18n('Apply Parts'),
			disabled: true,
			handler: 'onApplyMetaPartsClick',
			itemId: 'applyPartsButton'
		}
	],
	initComponent: function () {
		this.subGridEditing = Ext.create('Ext.grid.plugin.CellEditing', {
			clicksToEdit: 1,
			listeners: {
				edit: this.onAfterSubGridEdit,
				scope: this
			}
		});

		this.plugins = [this.subGridEditing];

		this.callParent(arguments);
	},
	onApplyMetaPartsClick: function (button) {
		this.convertMetaPartsToParts(button.up('grid').parentRecord);
	},
	/**
	 * Converts meta parts to parts. Iterates over the sub parts and figures out which actual parts to create, then
	 * removes the original meta part.
	 */
	convertMetaPartsToParts: function (record) {
		let missing, projectReportItem, subPart;

		for (let i = 0; i < record.subParts().getCount(); i++) {
			subPart = record.subParts().getAt(i);
			if (subPart.get('use')) {
				missing = subPart.get('stockLevel') - subPart.get('stockToUse');
				if (missing >= 0) {
					missing = 0;
				} else {
					missing = Math.abs(missing);
				}

				projectReportItem = Ext.create('Limas.Entity.ReportPart');
				projectReportItem.setPart(subPart);
				projectReportItem.set('quantity', subPart.get('stockToUse'));
				projectReportItem.setReport(this.up('#projectReportResult').projectReport);

				record.store.add(projectReportItem);
			}
		}

		record.store.remove(record);
	},

	/**
	 * Handles the change of the meta parts subgrid checkbox
	 */
	onCheckStateChange: function (check, rowIndex, checked, record) {
		let grid = check.up('grid');

		if (checked) {
			if (record.get('stockToUse') === 0 || record.get('stockToUse') === undefined) {
				let total = this.getAppliedPartCount(grid),
					missing = grid.parentRecord.get('quantity') - total;

				if (missing <= record.get('stockLevel')) {
					record.set('stockToUse', missing);
				} else {
					record.set('stockToUse', record.get('stockLevel'));
				}
			}
		}

		Ext.defer(this.updateSubGrid, 100, this, [check.up('grid')]);
	},
	onAfterSubGridEdit: function (editor, context) {
		context.record.set('stockToUse', context.value);

		Ext.defer(this.updateSubGrid, 100, this, [context.grid]);
	},
	updateSubGrid: function (grid) {
		if (this.getAppliedPartCount(grid) === grid.parentRecord.get('quantity')) {
			grid.down('#applyPartsButton').enable();
		} else {
			grid.down('#applyPartsButton').disable();
		}
	},
	getAppliedPartCount: function (grid) {
		let subParts = grid.parentRecord.subParts(),
			total = 0;

		for (let i = 0; i < subParts.getCount(); i++) {
			if (subParts.getAt(i).get('use')) {
				if (!isNaN(subParts.getAt(i).get('stockToUse'))) {
					total += subParts.getAt(i).get('stockToUse');
				}
			}
		}
		return total;
	}
});
