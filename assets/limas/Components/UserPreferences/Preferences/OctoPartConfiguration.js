Ext.define('Limas.Components.UserPreferences.Preferences.OctoPartConfiguration', {
	extend: 'Limas.Components.Preferences.PreferenceEditor',

	items: [{
		xtype: 'checkbox',
		plugins: [{
			ptype: 'preference',
			preferenceKey: 'limas.octopart.importDistributors',
			preferenceScope: 'user',
			preferenceDefault: true
		}],
		itemId: 'importDistributors',
		boxLabel: i18n('Distributors')
	}, {
		xtype: 'checkbox',
		plugins: [{
			ptype: 'preference',
			preferenceKey: 'limas.octopart.importParameters',
			preferenceScope: 'user',
			preferenceDefault: true
		}],
		itemId: 'importParameters',
		boxLabel: i18n('Parameters')
	}, {
		xtype: 'checkbox',
		plugins: [{
			ptype: 'preference',
			preferenceKey: 'limas.octopart.importDatasheets',
			preferenceScope: 'user',
			preferenceDefault: true
		}],
		itemId: 'importDatasheets',
		boxLabel: i18n('Datasheets')
	}, {
		xtype: 'checkbox',
		plugins: [{
			ptype: 'preference',
			preferenceKey: 'limas.octopart.importCADModels',
			preferenceScope: 'user',
			preferenceDefault: true
		}],
		itemId: 'importCADModels',
		boxLabel: i18n('CAD Models')
	}, {
		xtype: 'checkbox',
		plugins: [{
			ptype: 'preference',
			preferenceKey: 'limas.octopart.importComplianceDocuments',
			preferenceScope: 'user',
			preferenceDefault: true
		}],
		itemId: 'importComplianceDocuments',
		boxLabel: i18n('Compliance Documents')
	}, {
		xtype: 'checkbox',
		plugins: [{
			ptype: 'preference',
			preferenceKey: 'limas.octopart.importReferenceDesigns',
			preferenceScope: 'user',
			preferenceDefault: true
		}],
		itemId: 'importReferenceDesigns',
		boxLabel: i18n('Reference Designs')
	}, {
		xtype: 'checkbox',
		plugins: [{
			ptype: 'preference',
			preferenceKey: 'limas.octopart.importImages',
			preferenceScope: 'user',
			preferenceDefault: true
		}],
		itemId: 'importImages',
		boxLabel: i18n('Import Images')
	}],
	initComponent: function () {
		this.callParent();
	},
	onSave: function () {
		let item, preference, items = this.query();

		for (let i = 0; i < items.length; i++) {
			item = items[i];
			if (!item instanceof Ext.Component) {
				continue;
			}

			preference = item.getPlugin('preference');

			if (preference instanceof Limas.Components.Widgets.PreferencePlugin) {
				preference.savePreference();
			}
		}
	},
	statics: {
		iconCls: 'partkeepr-icon octopart',
		title: i18n('Octopart'),
		menuPath: [{iconCls: 'fugue-icon ui-scroll-pane-image', text: i18n('User Interface')}]
	}
});
