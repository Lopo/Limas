Ext.define('Limas.Components.Part.Renderers.PartParameterRenderer', {
	extend: 'Limas.Components.Grid.Renderers.AbstractRenderer',
	alias: 'columnRenderer.partParameter',

	renderer: function (value, metaData, record, rowIndex, colIndex, store, view, renderObj) {
		let partParameterName = renderObj.getRendererConfigItem(renderObj, 'parameterName', false);

		for (let i = 0; i < renderObj.getPartParameters(record).getCount(); i++) {
			if (renderObj.getPartParameters(record).getAt(i).get('name') === partParameterName) {
				return Limas.PartManager.formatParameter(renderObj.getPartParameters(record).getAt(i));
			}
		}

		return '';
	},
	getPartParameters: function (record) {
		return record.parameters();
	},

	statics: {
		rendererName: i18n('Part Parameter Renderer'),
		rendererDescription: i18n('Renders a specific part parameter'),
		rendererConfigs: {
			parameterName: {
				type: 'partParameter',
				title: i18n('Part Parameter Name')
			}
		},
		restrictToEntity: ['Limas.Entity.Part']
	}
});
