// https://forum.sencha.com/forum/showthread.php?471298-Undocumented-breaking-change-responseText-undefined&p=1326447&viewfull=1#post1326447
Ext.define(null, {
	override: 'Ext.data.proxy.Proxy',

	config: {
		allowResponseType: true, // <-- could set to false to force this globally
	},

	updateReader: function (reader) {
		this.callParent([reader]);

		if (!this.getAllowResponseType()) {
			delete this.responseType;
		}
	}
});
