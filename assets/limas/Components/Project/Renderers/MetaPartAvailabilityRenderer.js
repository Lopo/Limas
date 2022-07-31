Ext.define('Limas.Components.ProjectReport.Renderers.MetaPartAvailabilityRenderer', {
	extend: 'Limas.Components.Grid.Renderers.AbstractRenderer',
	alias: 'columnRenderer.projectReportMetaPartAvailability',

	renderer: function (val, q, rec) {
		let part = rec.getPart(),
			total = 0,
			partUnit = '';

		if (part.get('metaPart')) {
			for (let i = 0; i < rec.subParts().getCount(); i++) {
				total += rec.subParts().getAt(i).get('stockLevel');
			}
		} else {
			total = val;
			if (part.getPartUnit()) {
				partUnit = part.getPartUnit().get('shortName');
			}
		}

		if (total < rec.get('quantity')) {
			return '<span style="color: red;">' + total + " " + partUnit + "</span>";
		}
		return '<span style="color: green;">' + total + " " + partUnit + "</span>";
	},
	statics: {
		rendererName: i18n('Project Report Stock Availability Renderer'),
		rendererDescription: i18n('Renders the available stock level which includes the sum of all meta parts'),
		rendererConfigs: {},
		restrictToEntity: ['Limas.Entity.ReportPart']
	}
});
