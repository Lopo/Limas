Ext.define('Limas.Components.ProjectReport.Renderers.RemarksRenderer', {
	extend: 'Limas.Components.Grid.Renderers.AbstractRenderer',

	alias: 'columnRenderer.projectReportRemarks',

	renderer: function (value, metaData, record, rowIndex, colIndex, store, view, renderObj) {
		return renderObj.getProjectParts(record);
	},
	getProjectParts: function (rec) {
		let report = rec.getReport(),
			j, project, projectPart, projectPartQuantities = [];

		for (let i = 0; i < report.reportProjects().getCount(); i++) {
			project = report.reportProjects().getAt(i).getProject();
			for (j = 0; j < project.parts().getCount(); j++) {
				projectPart = project.parts().getAt(j);
				if (projectPart.getPart().getId() === rec.getPart().getId()) {
					if (projectPart.get("remarks") !== '' && projectPart.get('remarks') !== null) {
						projectPartQuantities.push(project.get('name') + ': ' + projectPart.get('remarks'));
					}
				}
			}
		}

		return projectPartQuantities.join('&#013;&#010;');
	},

	statics: {
		rendererName: i18n('Project Report Remark Renderer'),
		rendererDescription: i18n('Renders the remarks field'),
		restrictToEntity: ['Limas.Entity.ProjectReport']
	}
});
