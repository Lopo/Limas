Ext.define('Limas.Components.SystemPreferences.Preferences.RequiredPartFields', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		this.fieldSelector = Ext.create('Limas.Components.Widgets.FieldSelector', {
			height: 150,
			sourceModel: Limas.Entity.Part,
			recurseSubModels: false,
			excludeFields: [
				'@id',
				'name',
				'needsReview',
				'stockLevel',
				'averagePrice',
				'createDate',
				'removals',
				'lowStock',
				'minStockLevel'
			],
			initiallyChecked: Limas.getApplication().getSystemPreference('limas.part.requiredFields', [])
		});

		this.items = [
			{
				xtype: 'fieldcontainer',
				fieldLabel: i18n('Required Fields'),
				items: [
					{
						border: false,
						html: 'The fields <strong>Name</strong>, <strong>Category</strong> and <strong>Storage Location</strong> are always required.',
						style: 'padding-top: 4px; padding-bottom: 5px;'
					},
					this.fieldSelector
				]
			},
			{
				xtype: 'fieldcontainer',
				fieldLabel: i18n('Minimum Numbers'),
				items: [
					{
						fieldLabel: i18n('Distributors'),
						xtype: 'numberfield',
						minValue: 0,
						id: 'requirePartDistributorsAmount'
					},
					{
						fieldLabel: i18n('Manufacturers'),
						xtype: 'numberfield',
						minValue: 0,
						id: 'requirePartManufacturersAmount'
					},
					{
						fieldLabel: i18n('Attachments'),
						xtype: 'numberfield',
						minValue: 0,
						id: 'requirePartAttachmentsAmount'
					}
				]
			}
		];

		this.callParent(arguments);

		this.down('#requirePartDistributorsAmount').setValue(
			Limas.getApplication().getSystemPreference('limas.part.constraints.distributorCount', 0));
		this.down('#requirePartManufacturersAmount').setValue(
			Limas.getApplication().getSystemPreference('limas.part.constraints.manufacturerCount', 0));
		this.down('#requirePartAttachmentsAmount').setValue(
			Limas.getApplication().getSystemPreference('limas.part.constraints.attachmentCount', 0));
	},
	onSave: function () {
		let selection = this.fieldSelector.getChecked(),
			fields = [];

		for (let i = 0; i < selection.length; i++) {
			fields.push(selection[i].data.data.name);
		}

		Limas.getApplication().setSystemPreference('limas.part.requiredFields', fields);
		Limas.getApplication().setSystemPreference('limas.part.constraints.distributorCount',
			this.down('#requirePartDistributorsAmount').getValue());
		Limas.getApplication().setSystemPreference('limas.part.constraints.manufacturerCount',
			this.down('#requirePartManufacturersAmount').getValue());
		Limas.getApplication().setSystemPreference('limas.part.constraints.attachmentCount',
			this.down('#requirePartAttachmentsAmount').getValue());
	},
	statics: {
		iconCls: 'fugue-icon block--plus',
		title: i18n('Part'),
		menuPath: [{text: i18n('Required Fields')}]
	}
});
