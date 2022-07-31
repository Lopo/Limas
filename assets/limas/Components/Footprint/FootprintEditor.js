Ext.define('Limas.FootprintEditor', {
	extend: 'Limas.Editor',
	alias: 'widget.FootprintEditor',
	saveText: i18n('Save Footprint'),
	layout: 'column',
	defaultListenerScope: true,
	syncDirect: true,
	labelWidth: 75,
	initComponent: function () {
		this.on('startEdit', this.onEditStart, this, {delay: 50});

		this.attachmentGrid = Ext.create('Limas.FootprintAttachmentGrid', {
			height: 200,
			width: '100%',
			border: true
		});

		this.items = [{
			columnWidth: 1,
			minWidth: 500,
			layout: 'anchor',
			xtype: 'container',
			margin: '0 5 0 0',
			items: [
				{
					xtype: 'textfield',
					name: 'name',
					labelWidth: 75,
					anchor: '100%',
					fieldLabel: i18n('Name')
				}, {
					labelWidth: 75,
					xtype: 'textarea',
					name: 'description',
					anchor: '100%',
					fieldLabel: i18n('Description')
				}, {
					labelWidth: 75,
					xtype: 'fieldcontainer',
					anchor: '100%',
					fieldLabel: i18n('Attachments'),
					items: this.attachmentGrid
				}
			]
		}, {
			width: 370,
			height: 250,
			xtype: 'fieldcontainer',
			items: {
				xtype: 'remoteimagefield',
				itemId: 'image',
				maxHeight: 256,
				maxWidth: 256,
				listeners: {
					fileUploaded: 'onFileUploaded'
				}
			},
			labelWidth: 75,
			fieldLabel: i18n('Image')
		}];

		this.on('itemSaved', this._onItemSaved, this);
		this.callParent();
	},
	onFileUploaded: function (data) {
		let uploadedFile = Ext.create('Limas.Entity.TempUploadedFile', data);

		if (this.record.getImage() === null) {
			this.record.setImage(data);
		} else {
			this.record.getImage().set('replacement', uploadedFile.getId());
		}

		this.down('#image').setValue(uploadedFile);
	},
	_onItemSaved: function (record) {
		this.attachmentGrid.bindStore(record.attachments());
	},
	onEditStart: function () {
		this.attachmentGrid.bindStore(this.record.attachments());
		this.down('#image').setValue(this.record.getImage());
	}
});
