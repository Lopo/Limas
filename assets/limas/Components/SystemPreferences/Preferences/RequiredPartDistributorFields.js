Ext.define('Limas.Components.SystemPreferences.Preferences.RequiredPartDistributorFields', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		this.fieldSelector = Ext.create('Limas.Components.Widgets.FieldSelector', {
			height: 300,
			sourceModel: Limas.Entity.PartDistributor,
			recurseSubModels: false,
			excludeFields: [
				'@id'
			],
			initiallyChecked: Limas.getApplication().getSystemPreference('limas.partDistributor.requiredFields', [])
		});

		this.items = [
			{
				xtype: 'fieldcontainer',
				fieldLabel: i18n('Required Fields'),
				items: [
					{
						border: false,
						html: 'The field <strong>Distributor</strong> is always required.',
						style: 'padding-top: 4px; padding-bottom: 5px;'
					},
					this.fieldSelector
				]
			}
		];

		this.callParent(arguments);
	},
	onSave: function () {
		let selection = this.fieldSelector.getChecked(),
			fields = [];

		for (let i = 0; i < selection.length; i++) {
			fields.push(selection[i].data.data.name);
		}

		Limas.getApplication().setSystemPreference('limas.partDistributor.requiredFields', fields);
	},
	statics: {
		iconCls: 'fugue-icon block--plus',
		title: i18n('Part Distributor'),
		menuPath: [{text: i18n('Required Fields')}]
	}
});
