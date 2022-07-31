Ext.define('Limas.Components.UserPreferences.Preferences.FormattingConfiguration', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	initComponent: function () {
		this.createWidgets();
		this.loadDefaults();

		this.items = [
			this.priceNumDecimalsField,
			this.useThousandSeparatorCheckbox,
			this.currencySymbolField,
			this.currencyAtEndCheckbox
		];

		this.callParent();
	},
	loadDefaults: function () {
		this.priceNumDecimalsField.setValue(Limas.getApplication().getUserPreference('limas.formatting.currency.numdecimals', 2));
		this.useThousandSeparatorCheckbox.setValue(Limas.getApplication().getUserPreference('limas.formatting.currency.thousandsSeparator', true));
		this.currencyAtEndCheckbox.setValue(Limas.getApplication().getUserPreference('limas.formatting.currency.currencySymbolAtEnd', true));
		this.currencySymbolField.setValue(Limas.getApplication().getUserPreference('limas.formatting.currency.symbol', '€'));
	},

	createWidgets: function () {
		this.priceNumDecimalsField = Ext.create('Ext.form.field.Number', {
			name: 'priceNumDecimalsField',
			fieldLabel: i18n('Decimal precision'),
			labelWidth: 120,
			columnWidth: 0.5,
			minValue: 0,
			maxValue: 4,
			allowDecimals: false
		});

		this.useThousandSeparatorCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Separate thousands'),
			labelWidth: 120,
			hideEmptyLabel: false
		});

		this.currencySymbolField = Ext.create('Ext.form.field.Text', {
			fieldLabel: i18n('Currency Symbol'),
			labelWidth: 120,
			maxLength: 5
		});

		this.currencyAtEndCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Currency Symbol after value'),
			labelWidth: 120,
			hideEmptyLabel: false
		});
	},

	onSave: function () {
		let app = Limas.getApplication();
		app.setUserPreference('limas.formatting.currency.numdecimals', this.priceNumDecimalsField.getValue());
		app.setUserPreference('limas.formatting.currency.thousandsSeparator', this.useThousandSeparatorCheckbox.getValue());
		app.setUserPreference('limas.formatting.currency.symbol', this.currencySymbolField.getValue());
		app.setUserPreference('liams.formatting.currency.currencySymbolAtEnd', this.currencyAtEndCheckbox.getValue());
	},
	statics: {
		iconCls: 'fugue-icon ui-text-field-format',
		title: i18n('Formatting'),
		menuPath: [{iconCls: 'fugue-icon ui-scroll-pane-image', text: i18n('User Interface')}]
	}
});
