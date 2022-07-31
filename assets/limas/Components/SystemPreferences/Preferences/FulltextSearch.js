Ext.define('Limas.Components.SystemPreferences.Preferences.FulltextSearch', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		this.fieldSelector = Ext.create('Limas.Components.Widgets.FieldSelector', {
			height: 300,
			sourceModel: Limas.Entity.Part,
			initiallyChecked: Limas.getApplication().getSystemPreference('limas.part.search.fields',
				['name', 'description', 'comment', 'internalPartNumber'])
		});

		this.items = [
			{
				xtype: 'fieldcontainer',
				fieldLabel: i18n('Search Mode'),
				defaultType: 'radiofield',
				defaults: {
					flex: 1
				},
				layout: 'vbox',
				items: [
					{
						boxLabel: i18n('Use entered text as-is'),
						name: 'splitMode',
						inputValue: 'full',
						id: 'searchModeFull'
					}, {
						boxLabel: i18n('Separate search terms'),
						name: 'splitMode',
						inputValue: 'split',
						id: 'searchModeSplit'
					}
				]
			},
			{
				xtype: 'fieldcontainer',
				fieldLabel: i18n('Search Fields'),
				items: [
					{
						style: 'padding-top: 4px; padding-bottom: 5px;',
						html: i18n('Select all fields which are searched when entering a search term in the upper-right search field within the part manager'),
						border: false
					}, this.fieldSelector
				]
			}
		];

		this.callParent(arguments);

		if (Limas.getApplication().getSystemPreference('limas.part.search.split', true)) {
			this.down('#searchModeFull').setValue(false);
			this.down('#searchModeSplit').setValue(true);
		} else {
			this.down('#searchModeFull').setValue(true);
			this.down('#searchModeSplit').setValue(false);
		}
	},
	onSave: function () {
		let selection = this.fieldSelector.getChecked(),
			fields = [];

		for (let i = 0; i < selection.length; i++) {
			fields.push(selection[i].data.data.name);
		}

		Limas.getApplication().setSystemPreference('limas.part.search.fields', fields);

		if (this.down("#searchModeFull").getValue()) {
			Limas.getApplication().setSystemPreference('limas.part.search.split', false);
		} else {
			Limas.getApplication().setSystemPreference('limas.part.search.split', true);
		}
	},
	statics: {
		iconCls: 'fugue-icon magnifier-medium',
		title: i18n('Fulltext Search'),
		menuPath: [{text: i18n('Search')}]
	}
});
