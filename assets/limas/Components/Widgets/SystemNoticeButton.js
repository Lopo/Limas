Ext.define('Limas.SystemNoticeButton', {
	extend: 'Limas.FadingButton',
	iconCls: 'fugue-icon service-bell',
	tooltip: i18n('Unacknowledged System Notices'),

	initComponent: function () {
		this.callParent();

		this.on('show', this.startFading, this);
		this.on('hide', this.stopFading, this);
		this.on('click', this.onClick, this);
	},
	onClick: function () {
		Limas.getApplication().openAppItem('Limas.SystemNoticeEditorComponent');
	}
});
