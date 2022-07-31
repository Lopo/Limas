Ext.define('Limas.SystemInformationGrid', {
	extend: 'Limas.BaseGrid',

	columns: [
		{
			header: 'Name',
			dataIndex: 'name',
			width: 200
		}, {
			header: 'Value',
			dataIndex: 'value',
			renderer: Ext.util.Format.htmlEncode,
			flex: 1
		}, {
			header: 'Category',
			dataIndex: 'category',
			hidden: true
		}
	],

	initComponent: function () {
		this.features = [Ext.create('Ext.grid.feature.Grouping', {
			groupHeaderTpl: '{name}'
		})];

		/* Create the store using an in-memory proxy */
		this.store = Ext.create('Ext.data.Store', {
			model: 'Limas.SystemInformationRecord',
			sorters: ['category', 'name'],
			groupField: 'category',
			listeners: {
				scope: this,
				'load': function (store, records, successful/*, operation, eOpts*/) {
					if (!successful) {
						return;
					}
					store.add({
						category: 'JS',
						name: 'ExtJS Version',
						value: Ext.getVersion().version
					});
				}
			}
		});

		this.refreshButton = Ext.create('Ext.button.Button', {
			handler: function () {
				this.store.load();
			},
			scope: this,
			text: i18n('Refresh')
		});

		this.bottomToolbar = Ext.create('Ext.toolbar.Toolbar', {
			dock: 'bottom',
			ui: 'footer',
			items: [this.refreshButton]
		});

		this.dockedItems = [this.bottomToolbar];

		// Initialize the panel
		this.callParent();

		// Retrieve the system information
		this.store.load();
	},
	statics: {
		iconCls: 'fugue-icon system-monitor',
		title: i18n('System Information'),
		closable: true,
		menuPath: [{text: i18n('View')}]
	}
});
