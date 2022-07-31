/**
 * Searches the configured part field(s). If no record was found, the create part window is being opened.
 */
Ext.define('Limas.BarcodeScanner.Actions.AddPart', {
	extend: 'Limas.BarcodeScanner.Action',

	statics: {
		actionName: i18n('Add Part'),
		actionDescription: i18n('Searches for a part. If the part is not found, a create part window is opened'),

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
									boxLabel: i18n("Search beginning of string (string*)"),
									name: 'searchMode',
									inputValue: "beginning",
									checked: configuration.searchMode === 'beginning'
								}, {
									boxLabel: i18n('Search middle of string (*string*)'),
									name: 'searchMode',
									inputValue: "any",
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
			let defaults = {};
			for (let i = 0; i < this.config.searchFields.length; i++) {
				defaults[this.config.searchFields[i]] = this.data;
			}
			Limas.getApplication().getPartManager().onItemAdd(defaults);
		}
	}
});
