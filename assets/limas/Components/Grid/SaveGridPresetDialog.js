/**
 * Modal name prompt for capturing the current grid state as a new GridPreset.
 * Caller hands in the grid; the dialog reads ColumnConfigurator-style column
 * data + active store filter state, POSTs the row, and reloads the preset
 * menu's store on success.
 */
Ext.define('Limas.Components.Grid.SaveGridPresetDialog', {
	extend: 'Ext.window.Window',
	alias: 'widget.SaveGridPresetDialog',

	title: i18n('Save grid preset'),
	width: 420,
	modal: true,
	resizable: false,
	closable: true,
	layout: 'fit',

	grid: null,
	presetStore: null,

	initComponent: function () {
		this.nameField = Ext.create('Ext.form.field.Text', {
			fieldLabel: i18n('Preset name'),
			labelAlign: 'top',
			anchor: '100%',
			allowBlank: false
		});
		this.defaultCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Mark as default for this grid'),
			margin: '8 0 0 0'
		});

		this.items = [{
			xtype: 'form',
			border: false,
			bodyPadding: 12,
			items: [
				{
					xtype: 'displayfield',
					value: '<i class="limas-text-muted">' + i18n('Captures the current column layout and active filters. Re-apply later from the preset menu.') + '</i>'
				},
				this.nameField,
				this.defaultCheckbox
			]
		}];

		this.dockedItems = [{
			xtype: 'toolbar',
			dock: 'bottom',
			ui: 'footer',
			items: [
				'->',
				{text: i18n('Cancel'), iconCls: 'web-icon cancel', handler: Ext.bind(this.close, this)},
				{text: i18n('Save'), iconCls: 'fugue-icon disk', handler: Ext.bind(this.onSave, this)}
			]
		}];

		this.callParent();
	},

	onSave: function () {
		let name = (this.nameField.getValue() || '').trim();
		if (name === '') {
			this.nameField.markInvalid(i18n('Required'));
			return;
		}

		let configuration = Limas.Components.Grid.GridPresetState.capture(this.grid);
		let rec = Ext.create('Limas.Entity.GridPreset', {
			name: name,
			grid: this.grid.$className,
			configuration: Ext.encode(configuration),
			gridDefault: this.defaultCheckbox.getValue()
		});
		rec.save({
			success: Ext.bind(function () {
				Ext.toast({
					html: '<b>' + Ext.htmlEncode(name) + '</b> ' + i18n('saved.'),
					align: 't',
					autoCloseDelay: 3000
				});
				if (this.presetStore) {
					this.presetStore.load();
				}
				this.close();
			}, this),
			failure: Ext.bind(function (response) {
				let msg = i18n('Could not save the preset.');
				try {
					let body = Ext.decode(response.responseText);
					if (body && body['hydra:description']) msg += '\n\n' + body['hydra:description'];
				} catch (e) {
				}
				Ext.Msg.alert(i18n('Error'), msg);
			}, this)
		});
	}
});
