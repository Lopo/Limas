Ext.define('Limas.Components.Grid.Renderers.CurrencyRenderer', {
	extend: 'Limas.Components.Grid.Renderers.AbstractRenderer',
	alias: 'columnRenderer.currency',

	renderer: function (value) {
		return Limas.getApplication().formatCurrency(value);
	},

	statics: {
		rendererName: i18n('Currency Renderer'),
		rendererDescription: i18n('Renders a value with the system defined currency')
	}
});
