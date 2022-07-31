Ext.define('Limas.BarcodeScanner.Actions.AddRemoveStock', {
	extend: 'Limas.BarcodeScanner.Action',

	statics: {
		actionName: i18n('Add/Remove Stock'),
		actionDescription: i18n('Searches for a part and then allows the user to increase/decrease the stock level'),
		configure: function (configuration) {
			configuration = Ext.applyIf(configuration, {
				searchFields: [],
				searchMode: 'fixed'
			});

			let modelFieldSelector = Ext.create({
				xtype: 'modelFieldSelector',
				id: 'searchPartFieldSelector',
				border: false,
				sourceModel: Limas.Entity.Part,
				initiallyChecked: configuration.searchFields,
				flex: 1
			});

			let saveButton = Ext.create('Ext.button.Button', {
				text: i18n('OK'),
				iconCls: 'fugue-icon disk',
			});

			let cancelButton = Ext.create('Ext.button.Button', {
				text: i18n('Cancel'),
				iconCls: 'web-icon cancel'
			});

			let bottomToolbar = Ext.create('Ext.toolbar.Toolbar', {
				enableOverflow: true,
				margin: '10px',
				defaults: {minWidth: 100},
				dock: 'bottom',
				ui: 'footer',
				items: [saveButton, cancelButton]
			});

			let window = Ext.create('Ext.window.Window', {
					title: i18n('Add/Remove Stock Configuration'),
					height: 400,
					modal: true,
					width: 600,
					layout: {
						type: 'vbox',
						pack: 'start',
						align: 'stretch'
					},
					items: [
						{
							html: i18n('Select the field(s) to be searched'),
							border: false,
							bodyStyle: 'padding: 5px; background:transparent;',
						},
						modelFieldSelector,
						{
							xtype: 'radiogroup',
							layout: 'vbox',
							itemId: 'searchMode',
							items: [
								{
									boxLabel: i18n('Search string as-is'),
									name: 'searchMode',
									inputValue: 'fixed',
									checked: configuration.searchMode === 'fixed'
								},
								{
									boxLabel: i18n('Search beginning of string (string*)'),
									name: 'searchMode',
									inputValue: 'beginning',
									checked: configuration.searchMode === 'beginning'
								}, {
									boxLabel: i18n('Search middle of string (*string*)'),
									name: 'searchMode',
									inputValue: 'any',
									checked: configuration.searchMode === 'any'
								}
							]
						}
					],
					dockedItems: bottomToolbar
				}
			).show();

			saveButton.setHandler(function () {
				let selection = modelFieldSelector.getChecked(),
					fields = [];
				for (let i = 0; i < selection.length; i++) {
					fields.push(selection[i].data.data.name);
				}
				configuration.searchFields = fields;
				configuration.searchMode = this.down('#searchMode').getValue().searchMode;
				this.close();
			}, window);

			cancelButton.setHandler(function () {
				this.close();
			}, window);
		}
	},

	execute: function () {
		this.searchStore = Ext.create('Ext.data.Store', {
			model: 'Limas.Entity.Part',
			autoLoad: false,
			autoSync: false,
			remoteFilter: true,
			remoteSort: true
		});

		let subFilters = [],
			searchValue;

		switch (this.config.searchMode) {
			case 'beginning':
				searchValue = this.data + '%';
				break;
			case 'any':
				searchValue = '%' + this.data + '%';
				break;
			default:
				searchValue = this.data;
				break;
		}

		for (let i = 0; i < this.config.searchFields.length; i++) {
			subFilters.push(Ext.create('Limas.util.Filter', {
				property: this.config.searchFields[i],
				operator: 'LIKE',
				value: searchValue
			}));
		}

		this.filter = Ext.create('Limas.util.Filter', {
			type: 'OR',
			subfilters: subFilters
		});

		this.searchStore.on('load', this.onDataLoaded, this);
		this.searchStore.addFilter(this.filter, true);
		this.searchStore.load({start: 0});
	},
	onDataLoaded: function () {
		if (this.searchStore.getCount() === 0) {
			return;
		}

		if (this.searchStore.getCount() > 1) {
			let columns = [{header: 'Name', dataIndex: 'name', flex: 1}];
			for (let i = 0; i < this.config.searchFields.length; i++) {
				columns.push({
					header: this.config.searchFields[i],
					dataIndex: this.config.searchFields[i]
				});
			}

			this.window = Ext.create('Ext.window.Window', {
				itemId: 'window',
				listeners: {
					show: function () {
						Ext.defer(function () {
							this.down('#grid').getView().focusRow(0);
						}, 50, this);
						this.down('#grid').getSelectionModel().selectRange(0, 0);
					}
				},
				title: i18n('Multiple Parts found'),
				width: 800,
				height: 400,
				layout: 'fit',
				buttons: [
					{
						text: i18n('OK'),
						handler: function (btn) {
							let sel = btn.up('#window').down('#grid').getSelection();
							if (sel.length === 1) {
								btn.up('#window').fireEvent('recordSelected', sel[0]);
								btn.up('#window').close();
							}
						}
					}, {
						text: i18n('Cancel'),
						handler: function (btn) {
							btn.up('#window').close();
						}
					}
				],
				items: {
					xtype: 'grid',
					store: this.searchStore,
					columns: columns,
					itemId: 'grid',
					listeners: {
						rowdblclick: function (grid, record) {
							grid.up('#window').fireEvent('recordSelected', record);
							grid.up('#window').close();
						},
						rowkeydown: function (grid, record, tr, rowIndex, e) {
							if (e.event.code === 'Escape') {
								grid.up('#window').close();
							}
							if (e.event.code === 'Enter') {
								grid.up('#window').fireEvent('recordSelected', record);
								grid.up('#window').close();
							}
						}
					}
				}
			});

			this.window.on('recordSelected', this.combinedAddRemoveStockWindow, this);
			this.window.show();
		} else {
			this.combinedAddRemoveStockWindow(this.searchStore.getAt(0));
		}
	},
	combinedAddRemoveStockWindow: function (record) {
		Ext.create('Limas.Components.Part.AddRemoveStockWindow', {record: record})
			.show();
	}
});
