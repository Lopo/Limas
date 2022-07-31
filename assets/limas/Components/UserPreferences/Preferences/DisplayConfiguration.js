Ext.define('Limas.Components.UserPreferences.Preferences.DisplayConfiguration', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		this.showDescriptionsCheckbox = Ext.create('Ext.form.field.Checkbox', {
			labelWidth: 120,
			hideEmptyLabel: false,
			boxLabel: i18n('Show category descriptions (requires reload)')
		});

		this.showDescriptionsCheckbox.setValue(Limas.getApplication().getUserPreference('limas.categorytree.showdescriptions') !== false);

		this.compactLayout = Ext.create('Ext.form.field.Radio', {
			boxLabel: i18n('Compact Layout') + '<br/> <span class="partkeepr-part-manager-compact"/>',
			name: 'rb',
			inputValue: 'compact'
		});

		this.standardLayout = Ext.create('Ext.form.field.Radio', {
			boxLabel: i18n('Standard Layout') + '<br/> <span class="partkeepr-part-manager-standard"/>',
			name: 'rb',
			inputValue: 'standard'
		});

		if (Limas.getApplication().getUserPreference('limas.partmanager.compactlayout', false) === true) {
			this.compactLayout.setValue(true);
		} else {
			this.standardLayout.setValue(true);
		}

		this.compactLayoutChooser = Ext.create('Ext.form.RadioGroup', {
			fieldLabel: i18n('Part Manager Layout'),
			labelWidth: 120,
			columns: 2,
			width: 400,
			vertical: true,
			items: [
				this.compactLayout,
				this.standardLayout
			]
		});

		this.items = [this.showDescriptionsCheckbox, this.compactLayoutChooser];

		this.callParent();
	},
	onSave: function () {
		Limas.getApplication().setUserPreference('limas.categorytree.showdescriptions', this.showDescriptionsCheckbox.getValue());

		let layout = this.compactLayoutChooser.getValue(),
			compactLayout = false;

		if (layout.rb === 'compact') {
			compactLayout = true;
		}

		let oldCompactLayout = Limas.getApplication().getUserPreference('limas.partmanager.compactlayout', false);
		Limas.getApplication().setUserPreference('limas.partmanager.compactlayout', compactLayout);

		if (oldCompactLayout !== compactLayout) {
			Limas.getApplication().recreatePartManager();
		}
	},
	statics: {
		iconCls: 'fugue-icon monitor',
		title: i18n('Display'),
		menuPath: [{iconCls: 'fugue-icon ui-scroll-pane-image', text: i18n('User Interface')}]
	}
});
