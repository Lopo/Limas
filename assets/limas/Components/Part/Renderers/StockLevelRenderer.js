Ext.define('Limas.Components.Part.Renderers.StockLevelRenderer', {
	extend: 'Limas.Components.Grid.Renderers.AbstractRenderer',

	alias: 'columnRenderer.stockLevel',

	renderer: function (val, q, rec) {
		if (rec.getPartUnit()) {
			return val + ' ' + rec.getPartUnit().get('shortName');
		}
		return val;
	},

	statics: {
		rendererName: i18n('Stock Level Renderer'),
		rendererDescription: i18n('Renders the stock level including the part unit'),
		restrictToEntity: ['Limas.Entity.Part']
	}
});
