Ext.define('Limas.Widgets.Components.OperatorComboBox', {
	extend: 'Ext.form.field.ComboBox',
	xtype: 'OperatorComboBox',

	displayField: 'operator',
	emptyText: i18n('Select an operator'),
	editable: false,
	forceSelection: true,
	valueField: 'operator',
	returnObject: true,

	tpl: Ext.create('Ext.XTemplate',
		'<ul class="x-list-plain"><tpl for=".">',
		'<li role="option" class="x-boundlist-item">',
		'<span style="display: inline-block; width: 20px; text-align: center; ">{symbol}</span> <small>{description}</small>',
		'</li>',
		'</tpl></ul>'
	),

	displayTpl: Ext.create('Ext.XTemplate',
		'<tpl for=".">',
		'{symbol}',
		'</tpl>'
	),

	initComponent: function () {
		this.callParent(arguments);
		this.setStore(Ext.create('Limas.Data.store.OperatorStore'));
		this.on('afterrender', function () {
			this.inputEl.on('keydown', this.onOperatorKey, this);
		}, this);
	},
	/**
	 * Keyboard shortcut: pressing `=`, `<` or `>` selects that operator
	 * directly (the common ones). Uses the typed character (layout-aware) and
	 * an exact match on the operator value, so `>` picks `>` — not `>=`. Other
	 * operators are still picked from the list. No-op if the char isn't an
	 * operator or was filtered out for the current value type.
	 */
	onOperatorKey: function (e) {
		let ch = e.browserEvent && e.browserEvent.key;
		if (ch !== '=' && ch !== '<' && ch !== '>') {
			return;
		}
		let rec = this.getStore().findRecord('operator', ch, 0, false, true, true);
		if (rec) {
			this.setValue(ch);
			this.collapse();
			e.stopEvent();
		}
	}
});
