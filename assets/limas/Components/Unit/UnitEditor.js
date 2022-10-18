Ext.define('Limas.UnitEditor', {
	extend: 'Limas.Editor',
	alias: 'widget.UnitEditor',
	saveText: i18n('Save Unit'),
	initComponent: function () {
		let sm = Ext.create('Ext.selection.CheckboxModel', {
			checkOnly: true
		});

		this.gridPanel = Ext.create('Limas.BaseGrid', {
			store: Ext.data.StoreManager.lookup('SiPrefixStore'),
			selModel: sm,
			columnLines: true,
			columns: [
				{text: i18n('Prefix'), dataIndex: 'prefix', width: 60},
				{text: i18n('Symbol'), dataIndex: 'symbol', width: 60},
				{
					text: i18n('Power'), dataIndex: 'exponent', flex: 1, renderer: function (value, m, rec) {
						return rec.get('base') + "<sup>" + value + "</sup>";
					}
				}
			]
		});

		let container = Ext.create('Ext.form.FieldContainer', {
			fieldLabel: i18n('Allowed SI-Prefixes'),
			labelWidth: 150,
			items: this.gridPanel
		});

		this.items = [{
			xtype: 'textfield',
			name: 'name',
			fieldLabel: i18n('Unit Name')
		}, {
			xtype: 'textfield',
			name: 'symbol',
			fieldLabel: i18n('Symbol')
		},
			container];

		this.callParent();

		this.on('startEdit', this.onStartEdit, this);
		this.on('itemSave', this.onItemSave, this);
	},
	onStartEdit: function () {
		let records = this.record.prefixes().getRange(),
			toSelect = [],
			pfxStore = Ext.data.StoreManager.lookup('SiPrefixStore');

		for (let i = 0; i < records.length; i++) {
			toSelect.push(pfxStore.getById(records[i].getId()));
		}

		this.gridPanel.getSelectionModel().select(toSelect);
	},
	onItemSave: function () {
		let selection = this.gridPanel.getSelectionModel().getSelection();

		this.record.prefixes().removeAll(true);

		for (let i = 0; i < selection.length; i++) {
			this.record.prefixes().add(selection[i]);
		}
	}
});
