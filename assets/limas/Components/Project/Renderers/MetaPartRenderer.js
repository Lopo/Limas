Ext.define('Limas.Components.ProjectReport.Renderers.MetaPartRenderer', {
	extend: 'Limas.Components.Grid.Renderers.AbstractRenderer',

	alias: 'columnRenderer.projectReportMetaPart',

	renderer: function (val, q, rec) {
		let part = rec.getPart();
		if (part !== null) {
			return '<span class="web-icon brick' + (part.get('metaPart') ? 's' : '') + '"></span> ' + Ext.util.Format.htmlEncode(part.get('name'));
		}
	},
	statics: {
		rendererName: i18n('Project Report MetaPart Renderer'),
		rendererDescription: i18n('Renders a specific icon if the part is a meta part'),
		restrictToEntity: ['Limas.Entity.ReportPart']
	}
});
