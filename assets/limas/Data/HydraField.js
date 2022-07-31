/**
 * Adds the config field "byReference" to a field.
 *
 * byReference tells the system not to serialize the whole item but only its reference.
 */
Ext.define('Limas.data.HydraField', {
	override: 'Ext.data.field.Field',

	byReference: false,

	constructor: function (config) {
		this.byReference = config.byReference ? config.byReference : false;
		this.callParent(arguments);
	}
});
