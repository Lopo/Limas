/**
 * Confirm-and-pick-target dialog for merging the currently-edited Manufacturer
 * into another. Hits POST /api/manufacturers/{id}/merge with the target IRI.
 * Backend reassigns every PartManufacturer + cached aliases, drops the source.
 */
Ext.define('Limas.ManufacturerMergeDialog', {
	extend: 'Ext.window.Window',
	alias: 'widget.ManufacturerMergeDialog',

	title: i18n('Merge manufacturer'),
	width: 460,
	modal: true,
	resizable: false,
	closable: true,
	layout: 'fit',

	sourceRecord: null,
	onSuccess: null,

	initComponent: function () {
		let sourceName = this.sourceRecord ? this.sourceRecord.get('name') : '';

		this.targetCombo = Ext.create('Limas.ManufacturerComboBox', {
			fieldLabel: i18n('Merge into'),
			labelAlign: 'top',
			returnObject: true,
			allowBlank: false,
			anchor: '100%'
		});

		this.items = [{
			xtype: 'form',
			border: false,
			bodyPadding: 12,
			items: [
				{
					xtype: 'displayfield',
					value: Ext.String.format(
						i18n('All parts currently sourced from <b>{0}</b> will be reassigned to the picked manufacturer. The name <b>{0}</b> is recorded as a verified alias of the target so future imports route automatically. <b>This deletes <i>{0}</i>.</b>'),
						Ext.htmlEncode(sourceName)
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
				{
					text: i18n('Cancel'),
					iconCls: 'web-icon cancel',
					handler: Ext.bind(this.close, this)
				},
				{
					text: i18n('Merge'),
					iconCls: 'web-icon arrow_merge',
					handler: Ext.bind(this.doMerge, this)
				}
			]
		}];

		this.callParent();
	},

	doMerge: function () {
		let target = this.targetCombo.getSelection();
		if (!target) {
			Ext.Msg.alert(i18n('Pick a target'), i18n('Select the manufacturer to merge into.'));
			return;
		}
		if (target.get('@id') === this.sourceRecord.get('@id')) {
			Ext.Msg.alert(i18n('Same manufacturer'), i18n('Cannot merge a manufacturer into itself.'));
			return;
		}

		let sourceIri = this.sourceRecord.get('@id');
		let url = Limas.getBasePath() + sourceIri + '/merge';

		Ext.Ajax.request({
			url: url,
			method: 'POST',
			jsonData: {target: target.get('@id')},
			success: Ext.bind(function () {
				Ext.toast({
					html: Ext.String.format(
						i18n('Merged into {0}.'),
						Ext.htmlEncode(target.get('name'))
					),
					align: 't'
				});
				Ext.getStore('ManufacturerStore') && Ext.getStore('ManufacturerStore').reload();
				Ext.getStore('ManufacturerAliasStore') && Ext.getStore('ManufacturerAliasStore').reload();
				if (typeof this.onSuccess === 'function') {
					this.onSuccess();
				}
				this.close();
			}, this),
			failure: Ext.bind(function (response) {
				let msg = i18n('Merge failed.');
				try {
					let body = Ext.decode(response.responseText);
					if (body && body['hydra:description']) {
						msg += '\n\n' + body['hydra:description'];
					} else if (body && body.detail) {
						msg += '\n\n' + body.detail;
					}
				} catch (e) {
					// fall through with generic message
				}
				Ext.Msg.alert(i18n('Error'), msg);
			}, this)
		});
	}
});
