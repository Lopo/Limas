Ext.define('Limas.Components.SystemPreferences.Preferences.BarcodeScannerConfiguration', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		let modifierItems = [
			{
				xtype: 'checkbox',
				boxLabel: "Ctrl",
				itemId: 'barcodeScannerModifierCtrl'
			},
			{
				xtype: 'checkbox',
				boxLabel: 'Shift',
				itemId: 'barcodeScannerModifierShift'
			},
			{
				xtype: 'checkbox',
				boxLabel: 'Alt',
				itemId: 'barcodeScannerModifierAlt'
			}
		];

		this.barcodeScannerActionsStore = Ext.create('Ext.data.Store', {
			fields: ['code', 'action', 'configuration'],
			data: []
		});

		this.deleteButton = Ext.create('Ext.button.Button', {
			text: i18n('Delete'),
			tooltip: i18n('Delete'),
			iconCls: 'web-icon delete',
			handler: Ext.bind(function () {
				this.barcodeScannerActionsStore.remove(
					this.barcodeScannerActionsGrid.getSelectionModel().getSelection());
			}, this),
			disabled: true
		});

		this.addButton = Ext.create('Ext.button.Button', {
			text: i18n('Add'),
			tooltip: i18n('Add'),
			iconCls: 'web-icon add',
			handler: Ext.bind(function () {
				this.editing.startEdit(this.barcodeScannerActionsStore.add({})[0], 0);
			}, this)
		});

		this.topToolbar = Ext.create('Ext.toolbar.Toolbar', {
			dock: 'top',
			enableOverflow: true,
			items: [this.addButton, this.deleteButton]
		});

		this.editing = Ext.create('Ext.grid.plugin.RowEditing', {
			clicksToEdit: 1
		});

		this.barcodeScannerActionsGrid = Ext.create({
			height: 200,
			plugins: [this.editing],
			xtype: 'grid',
			dockedItems: [this.topToolbar],
			itemId: 'barcodeScannerActionsGrid',
			store: this.barcodeScannerActionsStore,
			columns: [
				{
					text: i18n('Code'), dataIndex: 'code', flex: 1,
					editor: {
						xtype: 'textfield'
					}
				},
				{
					text: i18n('Action'), dataIndex: 'action', flex: 1,
					renderer: function (v) {
						if (v instanceof Ext.data.Model) {
							return v.get('name');
						}
						return i18n('No action selected');
					},
					editor: {
						xtype: 'barcodescannerActions'
					}
				}, {
					text: i18n('Description'),
					flex: 3,
					renderer: function (v, m, record) {
						if (record.get('action') instanceof Ext.data.Model) {
							return record.get('action').get('description');
						}
						return '';
					}
				},
				{
					xtype: 'actioncolumn',
					items: [
						{
							iconCls: 'fugue-icon pencil',
							tooltip: i18n("Configure"),
							handler: function (view, rowIndex, colIndex, item, e, record) {
								let config = record.get('configuration');
								if (typeof config === 'undefined') {
									config = {};
									record.set('configuration', config);
								}

								if (record.get('action') instanceof Ext.data.Model) {
									Ext.ClassManager.get(record.get('action').get('action')).configure(config);
								}
							}
						}
					]
				}
			]
		});

		this.barcodeScannerActionsGrid.getSelectionModel().on('select', this._onItemSelect, this);
		this.barcodeScannerActionsGrid.getSelectionModel().on('deselect', this._onItemDeselect, this);

		this.items = [
			{
				fieldLabel: i18n('Modifier'),
				xtype: 'checkboxgroup',
				layout: {
					type: 'vbox',
					align: 'left'
				},
				items: modifierItems
			},
			{
				fieldLabel: i18n('Key'),
				xtype: 'textfield',
				itemId: 'barcodeScannerKey',
				minLength: 1,
				maxLength: 1,
				enforceMaxLength: true
			}, {
				fieldLabel: i18n('Timeout (ms)'),
				xtype: 'numberfield',
				minValue: 100,
				maxValue: 3000,
				itemId: 'barcodeScannerTimeout'

			}, {
				boxLabel: i18n('Use enter/return to indicate scan input end'),
				xtype: 'checkbox',
				hideEmptyLabel: false,
				itemId: 'barcodeScannerEnter'
			}, {
				xtype: 'fieldcontainer',
				fieldLabel: i18n('Actions'),
				items: [this.barcodeScannerActionsGrid]
			}
		];

		this.callParent(arguments);

		this.down('#barcodeScannerKey').setValue(
			Limas.getApplication().getSystemPreference('limas.barcodeScanner.key', ''));
		this.down('#barcodeScannerModifierAlt').setValue(
			Limas.getApplication().getSystemPreference('limas.barcodeScanner.modifierAlt', false));
		this.down('#barcodeScannerModifierShift').setValue(
			Limas.getApplication().getSystemPreference('limas.barcodeScanner.modifierShift', false));
		this.down('#barcodeScannerModifierCtrl').setValue(
			Limas.getApplication().getSystemPreference('limas.barcodeScanner.modifierCtrl', false));
		this.down('#barcodeScannerEnter').setValue(
			Limas.getApplication().getSystemPreference('limas.barcodeScanner.enter', true));
		this.down('#barcodeScannerTimeout').setValue(
			Limas.getApplication().getSystemPreference('limas.barcodeScanner.timeout', 500));

		let actions = Limas.getApplication().getSystemPreference('limas.barcodeScanner.actions', []),
			actionStore = Ext.create('Limas.Data.store.BarcodeScannerActionsStore');

		for (let i = 0; i < actions.length; i++) {
			let item = actions[i];
			this.barcodeScannerActionsStore.add({
				code: item.code,
				action: actionStore.findRecord('action', item.action),
				configuration: item.config
			});
		}
	},
	onSave: function () {
		Limas.getApplication().setSystemPreference('limas.barcodeScanner.key',
			this.down('#barcodeScannerKey').getValue());
		Limas.getApplication().setSystemPreference('limas.barcodeScanner.modifierAlt',
			this.down('#barcodeScannerModifierAlt').getValue());
		Limas.getApplication().setSystemPreference('limas.barcodeScanner.modifierShift',
			this.down('#barcodeScannerModifierShift').getValue());
		Limas.getApplication().setSystemPreference('limas.barcodeScanner.modifierCtrl',
			this.down('#barcodeScannerModifierCtrl').getValue());
		Limas.getApplication().setSystemPreference('limas.barcodeScanner.enter',
			this.down('#barcodeScannerEnter').getValue());
		Limas.getApplication().setSystemPreference('limas.barcodeScanner.timeout',
			this.down('#barcodeScannerTimeout').getValue());

		let data = this.down('#barcodeScannerActionsGrid').getStore().getData(),
			actions = [];

		for (let i = 0; i < data.length; i++) {
			let item = data.getAt(i);
			actions.push({
				code: item.get('code'),
				action: item.get('action').get('action'),
				config: item.get('configuration')
			});
		}

		Limas.getApplication().setSystemPreference('limas.barcodeScanner.actions', actions);
		Limas.getApplication().getBarcodeScannerManager().registerBarcodeScannerHotkey();
	},
	_onItemSelect: function (selectionModel, record) {
		this._updateDeleteButton(selectionModel, record);
		this.fireEvent('itemSelect', record);
	},
	_onItemDeselect: function (selectionModel, record) {
		this._updateDeleteButton(selectionModel, record);
		this.fireEvent('itemDeselect', record);
	},
	/**
	 * Called when an item was selected. Enables/disables the delete button.
	 */
	_updateDeleteButton: function () {
		/* Right now, we support delete on a single record only */
		if (this.barcodeScannerActionsGrid.getSelectionModel().getCount() === 1) {
			this.deleteButton.enable();
		} else {
			this.deleteButton.disable();
		}
	},
	statics: {
		iconCls: 'fugue-icon barcode',
		title: i18n('Barcode Scanner Configuration'),
		menuPath: []
	}
});
