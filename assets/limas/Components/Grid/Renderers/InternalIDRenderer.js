Ext.define('Limas.Components.Grid.Renderers.InternalIDRenderer', {
	extend: 'Limas.Components.Grid.Renderers.AbstractRenderer',
	alias: 'columnRenderer.internalID',

	renderer: function (value) {
		let values = value.split('/'),
			idstr = values[values.length - 1],
			idint = parseInt(idstr);

		return idstr + ' (#' + idint.toString(36) + ')';
	},

	statics: {
		rendererName: i18n('ID Renderer'),
		rendererDescription: i18n('Renders an ID in both base36 as well as integer format')
	}
});
