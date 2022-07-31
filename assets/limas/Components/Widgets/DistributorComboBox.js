Ext.define('Limas.DistributorComboBox', {
	extend: 'Limas.ReloadableComboBox',
	alias: 'widget.DistributorComboBox',
	ignoreQuery: false,
	initComponent: function () {
		this.store = Limas.getApplication().getDistributorStore();
		this.callParent();
	},
	onTriggerClick: function () {
		if (!this.ignoreQuery) {
			this.callParent();
		} else {
			let me = this;
			if (!me.readOnly && !me.disabled) {
				if (me.isExpanded) {
					me.collapse();
				} else {
					me.onFocus({});
					me.expand();
				}
				me.inputEl.focus();
			}
		}
	}
});
