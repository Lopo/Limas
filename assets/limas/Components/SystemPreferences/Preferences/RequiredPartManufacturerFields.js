Ext.define('Limas.Components.SystemPreferences.Preferences.RequiredPartManufacturerFields', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		this.fieldSelector = Ext.create('Limas.Components.Widgets.FieldSelector', {
			height: 300,
			sourceModel: Limas.Entity.PartManufacturer,
			recurseSubModels: false,
			excludeFields: [
				'@id'
			],
			initiallyChecked: Limas.getApplication().getSystemPreference('limas.partManufacturer.requiredFields', [])
		});

		this.items = [
			{
				xtype: 'fieldcontainer',
				fieldLabel: i18n('Required Fields'),
				items: [
					{
						border: false,
						html: 'The field <strong>Manufacturer</strong> is always required.',
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

		Limas.getApplication().setSystemPreference('limas.partManufacturer.requiredFields', fields);
	},
	statics: {
		iconCls: 'fugue-icon block--plus',
		title: i18n('Part Manufacturer'),
		menuPath: [{text: i18n('Required Fields')}]
	}
});
