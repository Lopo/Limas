/**
 * <p>The PartEditor provides an editing form for a part. It contains multiple tabs, one for each nested record.</p>
 */
Ext.define('Limas.PartEditor', {
	extend: 'Limas.Editor',

	model: 'Limas.Entity.Part',
	bodyPadding: '0 0 0 0',
	border: false,
	layout: 'fit',
	editAfterSave: false,

	initComponent: function () {
		// Defines the overall height of all fields, used to calculate the anchoring for the description field
		let overallHeight = (this.partMode === 'create') ? 320 : 265;

		this.nameField = Ext.create('Ext.form.field.Text', {
			name: 'name',
			fieldLabel: i18n('Name'),
			allowBlank: false,
			labelWidth: 150
		});

		this.storageLocationComboBox = Ext.create('Limas.StorageLocationPicker',
			{
				fieldLabel: i18n('Storage Location'),
				name: 'storageLocation',
				allowBlank: false,
				labelWidth: 150
			});

		this.footprintNone = Ext.create('Ext.form.field.Radio', {
			boxLabel: i18n('None'),
			name: 'footprint_mode',
			value: 'unset',
			width: 70,
			listeners: {
				scope: this,
				change: function (field, newValue) {
					if (newValue === true) {
						this.footprintComboBox.clearValue();
					}
				}
			}
		});

		this.footprintSet = Ext.create('Ext.form.field.Radio', {
			name: 'footprint_mode',
			width: 20,
			value: 'set'
		});

		/*
		 * Creates the footprint combo box. We listen for the "change" event, because we need to set the footprint
		 * comboboxes to the right state, depending on the selection. Another way would be to patch the combobox
		 * to support "null" values, however, this is a major change within ExtJS and probably not supported for
		 * updates of ExtJS.
		 */
		this.footprintComboBox = Ext.create('Limas.FootprintComboBox', {
			name: 'footprint',
			returnObject: true,
			flex: 1,
			listeners: {
				scope: this,
				change: function (field, newValue) {
					if (typeof (newValue) === 'object') {
						this.footprintSet.setValue(true);
					}
				}
			}
		});

		let basicEditorFields = [
			this.nameField,
			{
				xtype: 'textfield',
				fieldLabel: i18n('Description'),
				allowBlank: this.isOptional('description'),
				name: 'description'
			}, {
				layout: 'column',
				xtype: 'fieldcontainer',
				margin: {
					bottom: '0 5px 5px 0'
				},
				border: false,
				items: [
					{
						xtype: 'numberfield',
						fieldLabel: i18n('Minimum Stock'),
						allowDecimals: false,
						allowBlank: false,
						labelWidth: 150,
						name: 'minStockLevel',
						value: 0,
						columnWidth: 0.5,
						minValue: 0
					}, {
						padding: '0 0 0 5px',
						xtype: 'PartUnitComboBox',
						fieldLabel: i18n('Measurement Unit'),
						labelWidth: 120,
						columnWidth: 0.5,
						returnObject: true,
						name: 'partUnit'
					}
				]
			}, {
				xtype: 'CategoryComboBox',
				fieldLabel: i18n('Category'),
				name: 'category',
				displayField: 'name',
				returnObject: true
			},
			this.storageLocationComboBox,
			{
				xtype: 'fieldcontainer',
				layout: 'hbox',
				fieldLabel: i18n('Footprint'),
				defaults: {
					hideLabel: true
				},
				items: [
					this.footprintNone,
					this.footprintSet,
					this.footprintComboBox
				]
			}, {
				xtype: 'textarea',
				fieldLabel: i18n('Comment'),
				name: 'comment',
				allowBlank: this.isOptional('comment'),
				anchor: '100% ' + (-overallHeight).toString()
			},
			{
				xtype: 'textfield',
				fieldLabel: i18n('Production Remarks'),
				name: 'productionRemarks',
				allowBlank: this.isOptional('productionRemarks'),
			}, {
				xtype: 'fieldcontainer',
				layout: 'hbox',
				fieldLabel: i18n('Status'),
				defaults: {
					hideLabel: true
				},
				items: [
					{
						xtype: 'textfield',
						fieldLabel: i18n('Status'),
						flex: 1,
						allowBlank: this.isOptional('status'),
						name: 'status'
					}, {
						xtype: 'checkbox',
						hideEmptyLabel: false,
						fieldLabel: '',
						boxLabel: i18n('Needs Review'),
						name: 'needsReview'
					}
				]
			}, {
				xtype: 'textfield',
				fieldLabel: i18n('Condition'),
				name: 'partCondition',
				allowBlank: this.isOptional('partCondition'),
			}, {
				xtype: 'fieldcontainer',
				layout: 'hbox',
				items: [
					{
						xtype: 'textfield',
						labelWidth: 150,
						fieldLabel: i18n('Internal Part Number'),
						name: 'internalPartNumber',
						allowBlank: this.isOptional('internalPartNumber'),
						flex: 1
					}, {
						xtype: 'displayfield',
						qtip: i18n('The first number is the ID in decimal, the second number is the ID in base36'),
						fieldLabel: i18n('Internal ID'),
						listeners: {
							render: function (c) {
								Ext.QuickTips.register({
									target: c.getEl(),
									text: c.qtip
								});
							}
						},
						itemId: 'idField',
						name: '@id',
						fieldStyle: {
							color: 'blue',
							'text-decoration': 'underline',
						},
						renderer: function (value) {
							let values = value.split('/'),
								idstr = values[values.length - 1],
								idint = parseInt(idstr);
							return idstr + ' (#' + idint.toString(36) + ')';
						}
					}
				]
			}
		];

		this.partDistributorGrid = Ext.create('Limas.PartDistributorGrid', {
			title: i18n('Distributors'),
			iconCls: 'web-icon lorry',
			layout: 'fit'
		});

		this.partManufacturerGrid = Ext.create('Limas.PartManufacturerGrid', {
			title: i18n('Manufacturers'),
			iconCls: 'fugue-icon building',
			layout: 'fit'
		});

		this.partParameterGrid = Ext.create('Limas.PartParameterGrid', {
			title: i18n('Part Parameters'),
			iconCls: 'fugue-icon table',
			layout: 'fit'
		});

		this.partAttachmentGrid = Ext.create('Limas.PartAttachmentGrid', {
			title: i18n('Attachments'),
			iconCls: 'web-icon attach',
			layout: 'fit'
		});

		// Adds stock level fields for new items
		if (this.partMode && this.partMode === 'create') {
			this.initialStockLevel = Ext.create('Ext.form.field.Number', {
				fieldLabel: i18n('Initial Stock Level'),
				name: 'initialStockLevel',
				labelWidth: 150,
				columnWidth: 0.5
			});

			this.initialStockLevelUser = Ext.create('Limas.UserComboBox', {
				fieldLabel: i18n('Stock User'),
				name: 'initialStockLevelUser',
				columnWidth: 0.5,
				returnObject: true
			});

			basicEditorFields.push({
				xtype: 'container',
				layout: 'column',
				border: false,
				items: [
					this.initialStockLevel,
					this.initialStockLevelUser
				]
			});

			this.initialStockLevelPrice = Ext.create('Limas.CurrencyField', {
				fieldLabel: i18n('Price'),
				labelWidth: 150,
				columnWidth: 0.5,
				name: 'initialStockLevelPrice'
			});

			this.initialStockLevelPricePerItem = Ext.create('Ext.form.field.Checkbox', {
				boxLabel: i18n('Per Item'),
				columnWidth: 0.5,
				name: 'initialStockLevelPricePerItem'
			});

			basicEditorFields.push({
				xtype: 'container',
				layout: 'column',
				border: false,
				items: [
					this.initialStockLevelPrice,
					this.initialStockLevelPricePerItem
				]
			});
		}

		// Create a tab panel of all fields
		this.items = {
			xtype: 'tabpanel',
			border: false,
			items: [
				{
					iconCls: 'web-icon brick',
					ui: 'default-framed',
					xtype: 'panel',
					autoScroll: false,
					layout: 'anchor',
					defaults: {
						anchor: '100%',
						labelWidth: 150
					},
					title: i18n('Basic Data'),
					items: basicEditorFields
				},
				this.partDistributorGrid,
				this.partManufacturerGrid,
				this.partParameterGrid,
				this.partAttachmentGrid
			]
		};

		this.on('startEdit', this.onEditStart, this, {delay: 200});
		this.on('itemSaved', this._onItemSaved, this);

		this.callParent();

		this.on('itemSave', this.onItemSave, this);
		this.down('#idField').on('beforedestroy', this.onBeforeDestroy, this.down('#idField'));
	},
	/**
	 * Unregisters the quick tip immediately prior destroying
	 */
	onBeforeDestroy: function (field) {
		Ext.QuickTips.unregister(field.getEl());
	},
	/**
	 * Cleans up the record prior saving
	 */
	onItemSave: function () {
		let removeRecords = [], j, errors = [],
			minDistributorCount = Limas.getApplication().getSystemPreference('limas.part.constraints.distributorCount', 0),
			minManufacturerCount = Limas.getApplication().getSystemPreference('limas.part.constraints.manufacturerCount', 0),
			minAttachmentCount = Limas.getApplication().getSystemPreference('limas.part.constraints.attachmentCount', 0);

		/**
		 * Iterate through all records and check if a valid distributor ID is assigned. If not, the record is removed
		 * as it is assumed that the record is invalid and being removed.
		 */
		for (j = 0; j < this.record.distributors().getCount(); j++) {
			if (this.record.distributors().getAt(j).getDistributor() === null) {
				removeRecords.push(this.record.distributors().getAt(j));
			}
		}

		if (removeRecords.length > 0) {
			this.record.distributors().remove(removeRecords);
		}

		if (this.record.distributors().getCount() < minDistributorCount) {
			errors.push(Ext.String.format(i18n('The number of distributors must be greater than {0}'), minDistributorCount));
		}

		removeRecords = [];

		/**
		 * Iterate through all records and check if a valid manufacturer ID is assigned. If not, the record is removed
		 * as it is assumed that the record is invalid and being removed.
		 */
		/*for (j = 0; j < this.record.manufacturers().getCount(); j++) {
			if (this.record.manufacturers().getAt(j).getManufacturer() === null) {
				removeRecords.push(this.record.manufacturers().getAt(j));
			}
		}*/

		if (removeRecords.length > 0) {
			this.record.manufacturers().remove(removeRecords);
		}

		if (this.record.manufacturers().getCount() < minManufacturerCount) {
			errors.push(Ext.String.format(i18n('The number of manufacturers must be greater than {0}'), minManufacturerCount));
		}

		if (this.record.attachments().getCount() < minAttachmentCount) {
			errors.push(Ext.String.format(i18n('The number of attachments must be greater than {0}'), minAttachmentCount));
		}

		// Force footprint to be "null" when the checkbox is checked
		if (this.footprintNone.getValue() === true) {
			this.record.setFootprint(null);
		}

		if (this.initialStockLevel) {
			let initialStockLevel = this.initialStockLevel.getValue();
			if (this.record.phantom && initialStockLevel > 0) {
				let stockLevel = Ext.create('Limas.Entity.StockEntry');
				stockLevel.set('stockLevel', initialStockLevel);
				stockLevel.setUser(this.initialStockLevelUser.getValue());

				if (this.initialStockLevelPricePerItem.getValue() === false) {
					stockLevel.set('price', this.initialStockLevelPrice.getValue() / initialStockLevel);
				} else {
					stockLevel.set('price', this.initialStockLevelPrice.getValue());
				}

				this.record.stockLevels().add(stockLevel);
			}
		}

		if (errors.length > 0) {
			Ext.Msg.alert(i18n('Error'), errors.join('<br/>'));
			return false;
		}
	},
	onEditStart: function () {
		this.bindChildStores();
		this.nameField.focus();

		// Re-trigger validation because of asynchronous loading of the storage location field,
		// which would be marked invalid because validation happens immediately, but after loading
		// the storage locations, the field is valid, but not re-validated.

		// This workaround is done twice; once after the store is loaded and once when we start editing,
		// because we don't know which event will come first
		this.getForm().isValid();

		if (this.record.getFootprint() !== null) {
			this.footprintSet.setValue(true);
		} else {
			this.footprintNone.setValue(true);
		}
	},
	_onItemSaved: function () {
		this.fireEvent('partSaved', this.record);

		if (this.keepOpenCheckbox.getValue() !== true && this.createCopyCheckbox.getValue() !== true) {
			this.fireEvent('editorClose', this);
		} else {
			let newItem, data;

			if (this.partMode === 'create') {
				if (this.copyPartDataCheckbox.getValue() === true) {
					data = this.record.getData();
					delete data['@id'];

					newItem = Ext.create('Limas.Entity.Part');
					newItem.set(data);
					newItem.setAssociationData(this.record.getAssociationData());
					newItem.stockLevels().removeAll();
					newItem.set('stockLevel', 0);
					this.editItem(newItem);
				} else {
					newItem = Ext.create('Limas.Entity.Part');
					newItem.setPartUnit(Limas.getApplication().getDefaultPartUnit());
					newItem.setCategory(this.record.getCategory());
					this.editItem(newItem);
				}
			} else {
				data = this.record.getData();
				delete data['@id'];

				newItem = Ext.create('Limas.Entity.Part');
				newItem.set(data);
				newItem.setAssociationData(this.record.getAssociationData());
				this.editItem(newItem);
			}
		}
	},
	bindChildStores: function () {
		this.partDistributorGrid.bindStore(this.record.distributors());
		this.partManufacturerGrid.bindStore(this.record.manufacturers());
		this.partAttachmentGrid.bindStore(this.record.attachments());
		this.partParameterGrid.bindStore(this.record.parameters());
	},
	onCancelEdit: function () {
		this.record.distributors().rejectChanges();
		this.record.manufacturers().rejectChanges();
		this.record.attachments().rejectChanges();
		this.record.parameters().rejectChanges();
		this.callParent(arguments);
	},
	setTitle: function (title) {
		let tmpTitle = this.record.phantom
			? i18n('Add Part')
			: i18n('Edit Part');

		if (title !== '') {
			tmpTitle += ': ' + title;
		}

		this.fireEvent('_titleChange', tmpTitle);
	},
	isOptional: function (field) {
		return !Ext.Array.contains(Limas.getApplication().getSystemPreference('limas.part.requiredFields', []), field);
	}
});
