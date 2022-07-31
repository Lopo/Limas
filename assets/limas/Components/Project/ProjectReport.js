Ext.define('Limas.ProjectReportView', {
	extend: 'Ext.panel.Panel',
	alias: 'widget.ProjectReportView',

	bodyStyle: 'background:#DBDBDB;padding: 5px',
	border: false,

	layout: 'border',

	reportedProjects: [],

	viewModel: {
		data: {
			store: null,
			parentRecord: null
		}
	},

	initComponent: function () {
		this.createStores();

		this.projectList = Ext.create('Limas.Components.Project.ProjectReportList', {
			region: 'north',
			title: i18n('Choose Projects to create a report for'),
			height: 300,
			maxHeight: 500,
			split: true
		});

		this.reportList = Ext.create('Limas.Components.Project.ProjectReportGrid', {
			title: i18n('Previous Project Reports'),
			region: 'center'
		});

		this.reportResult = Ext.create('Limas.Components.Project.ProjectReportResultGrid', {
			store: null,
			itemId: 'projectReportResult',
			projectReportManager: this
		});

		this.emptyReportPartStore = Ext.create('Ext.data.Store', {
			model: 'Limas.Entity.ReportPart'
		});

		this.items = [
			{
				region: 'west',
				layout: 'border',
				collapsible: true,
				split: true,
				minWidth: 300,
				width: 500,
				items: [
					this.reportList,
					this.projectList
				]
			}, {
				region: 'center',
				layout: 'fit',
				title: i18n('Project Report'),
				items: this.reportResult
			}
		];

		this.callParent();

		this.down('#createReportButton').on('click', this.onCreateReportClick, this);
		this.down('#deleteReportButton').on('click', this.onDeleteReportClick, this);
		this.down('#loadReportButton').on('click', this.onLoadReportClick, this);
	},
	onLoadReportClick: function () {
		this.reportResult.getView().mask(i18n('Loading…'));
		let selection = this.reportList.getSelection();
		if (selection.length === 1) {
			this.projectReport = Limas.Entity.Report.load(
				selection[0].getId(),
				{
					success: this.onProjectReportLoaded,
					scope: this
				});
		}
	},
	onCreateReportClick: function () {
		this.reportResult.getView().mask(i18n('Loading…'));
		this.reportResult.setProjectsToReport(this.projectList.getProjectsToReport());

		let projectsToReport = this.projectList.getProjectsToReport();

		this.projectReport = Ext.create('Limas.Entity.Report');

		for (let i = 0; i < projectsToReport.length; i++) {
			this.projectReport.reportProjects().add(
				Ext.create('Limas.Entity.ReportProject', {
					project: projectsToReport[i].project,
					quantity: projectsToReport[i].quantity
				}));
		}

		this.doSaveProjectReport();
	},
	onDeleteReportClick: function () {
		let selection = this.reportList.getSelection();
		if (selection.length === 1) {
			Ext.Msg.confirm(
				i18n('Delete Report'),
				sprintf(i18n('Do you really wish to delete the report %s %s?'),
					selection[0].get('name'),
					selection[0].get('createDateTime')
				),
				this.deleteReport,
				this
			);
		}
	},
	deleteReport: function (btn) {
		if (btn === 'yes') {
			this.reportResult.setProjectsToReport([]);
			this.reportResult.setStore(new Ext.data.Store());

			let selection = this.reportList.getSelection();
			if (selection.length === 1) {
				selection[0].erase();
			}
		}
	},
	doSaveProjectReport: function () {
		this.reportResult.getView().mask(i18n('Saving…'));
		this.reportResult.reconfigure(this.emptyReportPartStore);
		this.projectReport.save({
			success: this.onProjectReportSave,
			scope: this
		});
	},
	onProjectReportSave: function () {
		this.projectReport.load({
			success: this.onProjectReportLoaded,
			scope: this
		});

		this.reportList.getStore().reload();
	},
	onProjectReportLoaded: function () {
		this.reportResult.reconfigure(this.projectReport.reportParts());
		this.reportResult.projectReport = this.projectReport;
		this.reportResult.getView().unmask();
	},
	createStores: function () {
		this.projectReportStore = Ext.create('Ext.data.Store', {
			model: 'Limas.Entity.ReportPart',
			pageSize: -1,
			proxy: {
				type: 'Hydra',
				url: '/api/project_reports'
			}
		});
	},
	statics: {
		iconCls: 'fugue-icon drill',
		title: i18n('Project Reports'),
		closable: true,
		menuPath: [{text: i18n('View')}]
	}
});
