Ext.define('Limas.UserGrid', {
	extend: 'Limas.EditorGrid',
	alias: 'widget.UserGrid',
	columns: [
		{
			header: i18n('User'),
			dataIndex: 'username',
			flex: 1
		}, {
			header: i18n('Provider'),
			renderer: function (value, metaData, record) {
				return record.getProvider() !== null ? record.getProvider().get('type') : '';
			},
			flex: 1
		}, {
			header: i18n('Active'),
			xtype: 'booleancolumn',
			dataIndex: 'active',
			trueText: '<span style="vertical-align: top;" class="web-icon accept"/>',
			falseText: '<span style="vertical-align: top;" class="web-icon cancel"/>',
			flex: 0.5
		}
	],
	addButtonText: i18n('Add User'),
	addButtonIconCls: 'fugue-icon user--plus',
	deleteButtonText: i18n('Delete User'),
	deleteButtonIconCls: 'fugue-icon user--minus',
	automaticPageSize: true,

	initComponent: function () {
		this.callParent(arguments);

		this.providerStore = Ext.data.StoreManager.lookup('UserProviderStore');

		this.providerCombo = Ext.create('Ext.form.field.ComboBox', {
			store: this.providerStore,
			displayField: 'type',
			valueField: '@id',
			editable: false,
			forceSelection: true,
			fieldLabel: i18n('Type'),
			emptyText: i18n('-ANY-'),
			listeners: {
				select: 'onProviderSelect',
				scope: this
			}
		});

		this.providerToolbar = Ext.create('Ext.toolbar.Toolbar', {
			dock: 'top',
			enableOverflow: true,
			items: this.providerCombo
		});

		this.filter = Ext.create('Limas.util.Filter', {
			property: 'provider',
			operator: '=',
			value: ''
		});

		this.addDocked(this.providerToolbar);
	},
	onProviderSelect: function (combo, record) {
		this.filter.setValue(record);
		this.store.addFilter(this.filter);
	}
});
