/**
 * Confirm dialog for the Parts grid's "Move to storage location…" bulk
 * action. Lists how many parts are about to be reassigned, lets the user
 * pick the target StorageLocation, then POSTs the whole batch to
 * /api/parts/bulkMove (server commits in one transaction).
 *
 * PK #1193 / #664
 */
Ext.define('Limas.Components.Part.BulkMoveDialog', {
	extend: 'Ext.window.Window',
	alias: 'widget.PartsBulkMoveDialog',

	title: i18n('Move parts to storage location'),
	width: 480,
	modal: true,
	resizable: false,
	closable: true,
	layout: 'fit',

	parts: null,
	onSuccess: null,

	initComponent: function () {
		let count = Ext.isArray(this.parts) ? this.parts.length : 0;

		this.targetCombo = Ext.create('Limas.Widgets.StorageLocationTreeComboBox', {
			fieldLabel: i18n('Target storage location'),
			labelAlign: 'top',
			anchor: '100%',
			allowBlank: false
		});

		this.items = [{
			xtype: 'form',
			border: false,
			bodyPadding: 12,
			items: [
				{
					xtype: 'displayfield',
					value: Ext.String.format(
						i18n('About to reassign <b>{0}</b> selected part(s). Their stock journals, parameters and attachments stay intact — only the storage location is updated.'),
						count
					)
				},
				this.targetCombo
			]
		}];

		this.dockedItems = [{
			xtype: 'toolbar',
			dock: 'bottom',
			ui: 'footer',
			items: [
				'->',
				{text: i18n('Cancel'), iconCls: 'web-icon cancel', handler: Ext.bind(this.close, this)},
				{text: i18n('Move'), iconCls: 'fugue-icon truck', handler: Ext.bind(this.doMove, this)}
			]
		}];

		this.callParent();
	},

	doMove: function () {
		let target = this.targetCombo.getValue();
		if (!target || !this.parts || this.parts.length === 0) {
			Ext.Msg.alert(i18n('Pick a target'), i18n('Select the storage location to move into.'));
			return;
		}

		// targetCombo returns an IRI when wired against the storage location
		// tree store; defensive String() in case a model record slips in.
		let targetIri = (target && target.get) ? target.get('@id') : String(target);

		this.mask(i18n('Moving…'));
		Ext.Ajax.request({
			url: Limas.getBasePath() + '/api/parts/bulkMove',
			method: 'POST',
			jsonData: {
				parts: this.parts,
				storageLocation: targetIri
			},
			success: Ext.bind(function (response) {
				this.unmask();
				let body = {};
				try {
					body = Ext.decode(response.responseText);
				} catch (e) {
				}
				let moved = body.moved || 0;
				let failed = (body.failed || []).length;

				let msg = Ext.String.format(i18n('Moved {0} part(s).'), moved);
				if (failed > 0) {
					msg += ' ' + Ext.String.format(i18n('{0} failed — check console.'), failed);
					Ext.log.warn('BulkMove failures:', body.failed);
				}
				Ext.toast({
					html: msg,
					align: 't',
					autoCloseDelay: failed > 0 ? 8000 : 3000
				});
				if (typeof this.onSuccess === 'function') {
					this.onSuccess(body);
				}
				this.close();
			}, this),
			failure: Ext.bind(function (response) {
				this.unmask();
				let msg = i18n('Move failed.');
				try {
					let body = Ext.decode(response.responseText);
					if (body && (body['hydra:description'] || body.detail)) {
						msg += '\n\n' + (body['hydra:description'] || body.detail);
					}
				} catch (e) {
				}
				Ext.Msg.alert(i18n('Error'), msg);
			}, this)
		});
	}
});
