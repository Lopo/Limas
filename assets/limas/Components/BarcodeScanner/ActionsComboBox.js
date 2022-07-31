Ext.define('Limas.BarcodeScanner.ActionsComboBox', {
	extend: 'Ext.form.field.ComboBox',
	displayField: 'name',
	valueField: 'action',
	queryMode: 'local',
	editable: false,
	emptyText: i18n('Select an action'),
	forceSelection: true,
	xtype: 'barcodescannerActions',
	returnObject: true,
	initComponent: function () {
		this.store = Ext.create('Limas.Data.store.BarcodeScannerActionsStore');
		this.callParent(arguments);
	}
});
