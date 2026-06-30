/**
 * Toolbar dropdown hosting actions that operate on the current PartsGrid
 * selection. Disabled when nothing is selected; each menu item performs
 * its own count check against the live selection (selection may have
 * changed between menu show and click). Designed to host future actions
 * (bulk delete, bulk re-category) without churning the host grid wiring.
 */
Ext.define('Limas.Components.Part.BulkActionsButton', {
	extend: 'Ext.button.Button',
	alias: 'widget.PartsBulkActionsButton',

	iconCls: 'fugue-icon ui-check-boxes-list',
	text: i18n('Bulk actions'),
	disabled: true,

	grid: null,

	initComponent: function () {
		this.menu = Ext.create('Ext.menu.Menu', {
			items: [
				{
					text: i18n('Move to storage location…'),
					iconCls: 'fugue-icon truck',
					handler: Ext.bind(this.onMoveClick, this)
				}
			]
		});

		this.callParent(arguments);

		if (this.grid && this.grid.getSelectionModel) {
			this.grid.getSelectionModel().on('selectionchange', this.onSelectionChange, this);
		}
	},

	onSelectionChange: function (sm, selections) {
		this.setDisabled(!selections || selections.length === 0);
	},

	getSelectedIris: function () {
		if (!this.grid || !this.grid.getSelectionModel) {
			return [];
		}
		return this.grid.getSelectionModel().getSelection()
			.map(function (rec) {
				return rec.get('@id');
			})
			.filter(function (iri) {
				return !!iri;
			});
	},

	onMoveClick: function () {
		let iris = this.getSelectedIris();
		if (iris.length === 0) {
			return;
		}

		Ext.create('Limas.Components.Part.BulkMoveDialog', {
			parts: iris,
			onSuccess: Ext.bind(function () {
				if (this.grid && this.grid.store) {
					this.grid.store.reload();
				}
			}, this)
		}).show();
	}
});
