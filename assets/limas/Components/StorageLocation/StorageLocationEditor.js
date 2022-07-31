Ext.define('Limas.StorageLocationEditor', {
	extend: 'Limas.Editor',
	alias: 'widget.StorageLocationEditor',
	saveText: i18n('Save Storage Location'),

	layout: 'column',
	defaultListenerScope: true,

	initComponent: function () {
		let config = {};

		Ext.Object.merge(config, {
			autoLoad: false,
			model: 'Limas.Entity.Part',
			autoSync: false, // Do not change. If true, new (empty) records would be immediately commited to the database.
			remoteFilter: true,
			remoteSort: true,
			pageSize: 15
		});

		this.store = Ext.create('Ext.data.Store', config);

		this.bottomToolbar = Ext.create('Limas.PagingToolbar', {
			store: this.store,
			enableOverflow: true,
			dock: 'bottom',
			displayInfo: false
		});

		this.gridPanel = Ext.create('Limas.BaseGrid', {
			store: this.store,
			columnLines: true,
			dockedItems: [this.bottomToolbar],
			columns: [
				{
					header: i18n('Name'),
					dataIndex: 'name',
					flex: 1,
					minWidth: 200,
					renderer: Ext.util.Format.htmlEncode
				},
				{
					header: i18n('Qty'),
					width: 50,
					dataIndex: 'stockLevel'
				}
			]
		});

		this.gridPanel.on('itemdblclick', this.onDoubleClick, this);

		let container = Ext.create('Ext.form.FieldContainer', {
			fieldLabel: i18n('Contained Parts'),
			labelWidth: 110,
			layout: 'fit',
			height: 246,
			itemId: 'containedParts',
			items: this.gridPanel
		});

		this.items = [
			{
				columnWidth: 1,
				minWidth: 500,
				layout: 'anchor',
				xtype: 'container',
				margin: '0 5 0 0',
				items: [
					{
						xtype: 'textfield',
						name: 'name',
						anchor: '100%',
						labelWidth: 110,
						fieldLabel: i18n('Storage Location')
					},
					container
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
			}
		];

		this.on('startEdit', this.onStartEdit, this);
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
	onStartEdit: function () {
		if (!this.record.phantom) {
			this.down('#containedParts').setVisible(true);
			let filter = Ext.create('Limas.util.Filter', {
				property: 'storageLocation',
				operator: '=',
				value: this.record.getId()
			});

			this.store.addFilter(filter);
			this.store.load();
		} else {
			this.down('#containedParts').setVisible(false);
		}

		this.down('#image').setValue(this.record.getImage());
	},
	onDoubleClick: function (view, record) {
		if (record) {
			this.onEditPart(record);
		}
	},
	onEditPart: function (part) {
		let editorWindow = part.get('metaPart') === true
			? Ext.create('Limas.Components.Part.Editor.MetaPartEditorWindow')
			: Ext.create('Limas.PartEditorWindow');
		editorWindow.on('partSaved', this.onPartSaved, this);
		editorWindow.editor.editItem(part);
		editorWindow.show();
	},
	onPartSaved: function () {
		this.grid.getStore().reload();
	}
});
