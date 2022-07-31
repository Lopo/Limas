Ext.define('Limas.Components.ProjectReport.MetaPartRowExpander', {
	extend: 'Ext.grid.plugin.RowWidget',

	ptype: 'metapartrowexpander',

	getHeaderConfig: function () {
		let config = this.callParent(arguments);

		config.renderer = function (v, p, rec) {
			if (rec.get('metaPart')) {
				return '<div class="' + Ext.baseCSSPrefix + 'grid-row-expander" role="presentation" tabIndex="0"></div>';
			}
			return '';
		};

		return config;
	}
});
