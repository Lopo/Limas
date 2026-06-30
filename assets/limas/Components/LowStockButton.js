/**
 * Top-bar badge showing how many Parts are currently below their minStockLevel
 * (Part.lowStock = true). Hidden when count is zero. Click opens the Parts
 * Manager and applies a `lowStock = true` filter so the user can act on them
 * directly.
 *
 * Refreshes whenever the user lands on the menu bar after a stock change —
 * Limas.Application broadcasts a `partStockChanged` event after Add/Remove
 * Stock dialogs commit; we listen on the global window object via a small
 * helper hook. As a fallback we poll every 5 minutes so a multi-tab edit
 * eventually shows up.
 */
Ext.define('Limas.LowStockButton', {
	extend: 'Ext.button.Button',
	alias: 'widget.LowStockButton',

	tooltip: i18n('Parts below minimum stock level'),
	iconCls: 'fugue-icon exclamation--frame',
	hidden: true,
	cls: 'limas-lowstock-button',

	initComponent: function () {
		this.callParent(arguments);

		this.setHandler(Ext.bind(this.onClick, this));
		this.refresh();
		// Fallback poll — 5 min is fine because the canonical refresh trigger
		// is the partStockChanged hook fired by AddRemoveStock dialogs.
		this.pollTask = Ext.TaskManager.start({
			run: this.refresh,
			scope: this,
			interval: 5 * 60 * 1000
		});

		Ext.GlobalEvents.on('partStockChanged', this.refresh, this);
	},

	/**
	 * Direct Ajax for the count — going via an Ext store + HydraProxy's
	 * filter handshake was returning the unfiltered total (13 instead of
	 * the actual 1), so we send the AdvancedSearchFilter-shaped `filter`
	 * query param ourselves and read `hydra:totalItems` off the bare
	 * collection response
	 */
	refresh: function () {
		let filter = Ext.encode([{property: 'lowStock', operator: '=', value: true}]);
		Ext.Ajax.request({
			url: Limas.getBasePath() + '/api/parts?itemsPerPage=1&filter=' + encodeURIComponent(filter),
			method: 'GET',
			success: Ext.bind(function (response) {
				let total = 0;
				try {
					let body = Ext.decode(response.responseText);
					total = body['hydra:totalItems'] || 0;
				} catch (e) {
					return;
				}
				if (total > 0) {
					// Bare number reads as ambiguous next to the logo — add
					// the "low" suffix so the chip is self-explanatory.
					this.setText(total + ' ' + i18n('low'));
					this.show();
				} else {
					this.hide();
				}
			}, this)
		});
	},

	onClick: function () {
		let partManager = Limas.getApplication() && Limas.getApplication().getPartManager ? Limas.getApplication().getPartManager() : null;
		if (!partManager) {
			return;
		}
		let grid = partManager.grid;
		if (!grid || !grid.store) {
			return;
		}
		// Replace whatever filters are active with a single lowStock=true so the
		// user sees only what they need to reorder. Bypass the Limas search
		// path because this is a deliberate scope shift.
		grid.store.setFilters([
			Ext.create('Limas.util.Filter', {
				property: 'lowStock',
				operator: '=',
				value: true
			})
		]);
	},

	onDestroy: function () {
		if (this.pollTask) {
			Ext.TaskManager.stop(this.pollTask);
		}
		Ext.GlobalEvents.un('partStockChanged', this.refresh, this);
		this.callParent(arguments);
	}
});
