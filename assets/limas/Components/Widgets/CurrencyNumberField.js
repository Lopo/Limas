/**
 * Extends the Ext.ux.NumericField and applies defaults stored within the user preferences
 */
Ext.define('Limas.CurrencyField', {
	extend: 'Ext.ux.NumericField',
	alias: 'widget.CurrencyField',

	initComponent: function () {
		this.decimalPrecision = Limas.getApplication().getUserPreference('limas.formatting.currency.numdecimals', 2);
		this.currencySign = "";
		this.currencyAtEnd = Limas.getApplication().getUserPreference('limas.formatting.currency.currencySymbolAtEnd', true);

		if (Limas.getApplication().getUserPreference('limas.formatting.currency.thousandsSeparator', true) === true) {
			// @todo This is hard-coded for now
			this.thousandSeparator = ',';
		} else {
			this.thousandSeparator = '';
		}

		this.callParent();
	}
});
