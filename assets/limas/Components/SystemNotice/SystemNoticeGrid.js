Ext.define('Limas.SystemNoticeGrid', {
	extend: 'Limas.EditorGrid',
	alias: 'widget.SystemNoticeGrid',
	columns: [
		{header: i18n('Name'), dataIndex: 'title', flex: 1}
	],
	enableTopToolbar: false
});
