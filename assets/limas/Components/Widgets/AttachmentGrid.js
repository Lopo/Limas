Ext.define('Limas.AttachmentGrid', {
	extend: 'Limas.BaseGrid',
	alias: 'widget.AttachmentGrid',
	border: false,
	model: null,
	selModel: {
		selType: 'rowmodel',
		mode: 'MULTI'
	},
	initComponent: function () {
		this.store = Ext.create('Ext.data.Store', {
			model: this.model,
			proxy: {
				type: 'memory',
				reader: {
					type: 'json'
				}
			}
		});

		this.editing = Ext.create('Ext.grid.plugin.CellEditing', {
			clicksToEdit: 1
		});

		this.plugins = [this.editing];

		this.deleteButton = Ext.create('Ext.button.Button', {
			text: i18n('Delete'),
			disabled: true,
			itemId: 'delete',
			scope: this,
			iconCls: 'web-icon delete',
			handler: this.onDeleteClick
		});

		this.viewButton = Ext.create('Ext.button.Button', {
			text: i18n('View'),
			handler: this.onViewClick,
			scope: this,
			iconCls: 'web-icon zoom',
			disabled: true
		});

		this.openUrlButton = Ext.create('Ext.button.Button', {
			text: i18n('Open URL'),
			tooltip: i18n('Open the original source URL in a new tab — used for attachments that have not been downloaded yet (cron will retry).'),
			handler: this.onOpenUrlClick,
			scope: this,
			iconCls: 'fugue-icon globe-network',
			hidden: true
		});

		this.webcamButton = Ext.create('Ext.button.Button', {
			text: i18n('Take image'),
			handler: this.onWebcamClick,
			scope: this,
			iconCls: 'fugue-icon webcam'
		});

		this.dockedItems = [
			{
				xtype: 'toolbar',
				items: [
					{
						text: i18n('Add'),
						scope: this,
						iconCls: 'web-icon attach',
						handler: this.onAddClick
					},
					this.webcamButton,
					this.viewButton,
					this.deleteButton,
					this.openUrlButton
				]
			}
		];

		this.columns = [
			{
				dataIndex: 'extension',
				width: 30,
				renderer: function (value, metadata, record) {
					// URL-only attachments have no file blob — server would
					// 404 on /getMimeTypeIcon. Show a link icon + tooltip.
					if (record.get('downloaded') === false) {
						metadata.tdAttr = 'data-qtip="' + Ext.htmlEncode(i18n('Not downloaded — link only')) + '"';
						return '<span class="fugue-icon globe-network" style="display:inline-block;width:16px;height:16px;background-position:0 0;"></span>';
					}
					return '<img src="' + record.getId() + '/getMimeTypeIcon"/>';
				}
			},
			{
				header: i18n('Filename'),
				dataIndex: 'originalFilename',
				width: 200,
				renderer: function (value, metadata, record) {
					if (record.get('downloaded') === false) {
						metadata.tdCls = 'limas-attachment-pending limas-text-muted';
						metadata.style = 'font-style:italic;';
					}
					return Ext.htmlEncode(value || '');
				}
			},
			{
				header: i18n('Size'),
				dataIndex: 'size',
				width: 80,
				renderer: function (value, metadata, record) {
					if (record.get('downloaded') === false) {
						return '<span class="limas-text-muted" style="font-style:italic;">' + i18n('pending') + '</span>';
					}
					return Limas.bytesToSize(value);
				}
			},
			{
				header: i18n('Description'),
				dataIndex: 'description',
				flex: 0.4,
				editor: {
					xtype: 'textfield',
					allowBlank: true
				}
			}
		];

		this.callParent();

		this.getSelectionModel().on('selectionchange', this.onSelectChange, this);
		this.on('itemdblclick', this.onDoubleClick, this);
	},
	onWebcamClick: function () {
		if (Ext.isIE) {
			Ext.MessageBox.alert(i18n('Webcam not supported'), i18n('Internet Explorer does not support HTML5 webcams'));
			return;
		}

		let wp = Ext.create('Limas.WebcamPanel');
		wp.on('fileUploaded', this.onFileUploaded, this);

		let j = Ext.create('Ext.window.Window', {
			title: i18n('Take Webcam Photo'),
			layout: 'fit',
			items: [
				wp
			]
		});

		wp.on('fileUploaded', function () {
			j.close();
		});

		j.show();
	},
	onDoubleClick: function (view, record) {
		if (!record) return;
		// URL-only rows can't be embedded (server would 404 on /getFile).
		// Treat the double-click as "open the source URL" for them.
		if (record.get('downloaded') === false) {
			this.openSourceUrl(record);
			return;
		}
		this.viewAttachment(record);
	},
	onAddClick: function () {
		let j = Ext.create('Limas.FileUploadDialog');
		j.on('fileUploaded', this.onFileUploaded, this);
		j.show();
	},
	onFileUploaded: function (response) {
		this.editing.cancelEdit();
		this.store.add(response);
	},
	onDeleteClick: function () {
		let selection = this.getView().getSelectionModel().getSelection();
		selection.forEach((el) => Ext.Ajax.request({
			url: el.getId(),
			method: 'DELETE',
		}));
		this.store.remove(selection);
	},
	onSelectChange: function (selModel, selections) {
		this.deleteButton.setDisabled(selections.length === 0);
		// View only makes sense for fully-downloaded files; URL-only rows have
		// no blob to embed. The dedicated "Open URL" button covers them.
		let allDownloaded = selections.length > 0 && selections.every(r => r.get('downloaded') !== false);
		let anyUrlOnly = selections.some(r => r.get('downloaded') === false);
		this.viewButton.setDisabled(!allDownloaded);
		this.openUrlButton.setHidden(!anyUrlOnly);
		// Post-CAS the attachment serialises with `sourceUrls: string[]`
		// (Blob can have N provenance URLs). Open URL acts on the first
		// entry; the multi-source UX shows the rest as a tooltip / chip
		// strip in the detail view.
		let urls = selections.length === 1 ? (selections[0].get('sourceUrls') || []) : [];
		this.openUrlButton.setDisabled(urls.length === 0);
	},
	onViewClick: function () {
		let selection = this.getView().getSelectionModel().getSelection()[0];
		if (selection && selection.get('downloaded') !== false) {
			this.viewAttachment(selection);
		}
	},
	onOpenUrlClick: function () {
		let selection = this.getView().getSelectionModel().getSelection()[0];
		if (selection) {
			this.openSourceUrl(selection);
		}
	},
	openSourceUrl: function (record) {
		// Pick the first URL from the array. If we ever want a picker
		// (Farnell vs DigiKey for the same datasheet) we'd surface it
		// here — for now the most-recent BlobSource is good enough.
		let urls = record.get('sourceUrls') || [];
		let url = urls[0];
		if (!url) {
			Ext.Msg.alert(i18n('No source URL'), i18n('This attachment has no source URL to open.'));
			return;
		}
		window.open(url, '_blank', 'noopener,noreferrer');
	},
	viewAttachment: function (record) {
		let mySrc = record.getId() + '/getFile';
		new Ext.Window({
			title: i18n('Display File'),
			width: 640,
			height: 600,
			maximizable: true,
			constrain: true,
			layout: 'fit',
			items: [
				{
					xtype: 'component',
					autoEl: {
						tag: 'iframe',
						src: mySrc
					}
				}
			]
		})
			.show();
	}
});
