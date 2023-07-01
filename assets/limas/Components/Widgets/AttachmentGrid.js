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
					this.deleteButton
				]
			}
		];

		this.columns = [
			{
				dataIndex: 'extension',
				width: 30,
				renderer: function (value, metadata, record) {
					return '<img src="' + record.getId() + '/getMimeTypeIcon"/>';
				}
			},
			{
				header: i18n('Filename'),
				dataIndex: 'originalFilename',
				width: 200
			},
			{
				header: i18n('Size'),
				dataIndex: 'size',
				width: 80,
				renderer: Limas.bytesToSize
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
		if (record) {
			this.viewAttachment(record);
		}
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
		if (selection[0]) {
			Ext.Ajax.request({
				url: selection[0].getId(),
				method: 'DELETE'
			});
		}
		this.store.remove(selection);
	},
	onSelectChange: function (selModel, selections) {
		this.deleteButton.setDisabled(selections.length === 0);
		this.viewButton.setDisabled(selections.length === 0);
	},
	onViewClick: function () {
		let selection = this.getView().getSelectionModel().getSelection()[0];
		if (selection) {
			this.viewAttachment(selection);
		}
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
