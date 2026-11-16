Ext.define('Limas.SystemNoticeEditor', {
	extend: 'Limas.Editor',
	alias: 'widget.SystemNoticeEditor',

	saveText: i18n('Save System Notice'),
	defaults: {
		anchor: '100%',
		labelWidth: 110
	},
	layout: {
		type: 'vbox',
		align: 'stretch',
		pack: 'start'
	},
	enableButtons: false,

	initComponent: function () {
		// Acknowledging hides the notice for everyone, so it is admin-only
		this.acknowledgeButton = Ext.create('Ext.button.Button', {
			text: i18n('Acknowledge Notice'),
			iconCls: 'web-icon accept',
			disabled: !Limas.getApplication().isAdmin(),
			tooltip: Limas.getApplication().isAdmin() ? null : i18n('Only an administrator can acknowledge notices')
		});

		this.acknowledgeButton.on('click', this.onAcknowledgeClick, this);

		this.bottomToolbar = Ext.create('Ext.toolbar.Toolbar', {
			enableOverflow: true,
			margin: '10px',
			defaults: {minWidth: 100},
			dock: 'bottom',
			ui: 'footer',
			items: [this.acknowledgeButton]
		});

		this.dockedItems = new Array(this.bottomToolbar);

		this.items = [
			{
				xtype: 'textfield',
				readOnly: true,
				name: 'title',
				fieldLabel: i18n('Title')
			}, {
				xtype: 'textarea',
				readOnly: true,
				flex: 1,
				name: 'description',
				fieldLabel: i18n('Description')
			}, {
				xtype: 'datefield',
				readOnly: true,
				hideTrigger: true,
				name: 'date',
				fieldLabel: i18n('Date')
			}
		];

		this.callParent();
	},
	onAcknowledgeClick: function () {
		this.record.callPutAction('acknowledge', [], Ext.bind(this.onAcknowledged, this));
	},
	onAcknowledged: function () {
		this.fireEvent('editorClose', this);
		this.store.load();
	}
});
