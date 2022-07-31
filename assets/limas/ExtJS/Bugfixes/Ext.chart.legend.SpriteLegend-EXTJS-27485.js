// https://forum.sencha.com/forum/showthread.php?307209-candidate-isXType-is-not-a-function&p=1323505&viewfull=1#post1323505
Ext.define('Ext.overrides.chart.legend.SpriteLegend', {
	override: 'Ext.chart.legend.SpriteLegend',

	isXType: Ext.emptyFn
});

/**
 * @link: https://forum.sencha.com/forum/showthread.php?470876-candidate-isXType-is-not-a-function-for-legend-sprite-item&p=1321660&viewfull=1#post1321660
 * if ComponentQuery runs over spritelegend it crashes
 */
// Ext.define('Ext.override.chart.legend.SpriteLegend', {
// 	override: 'Ext.chart.legend.SpriteLegend',
//
// 	isXType: function (xtype) {
// 		return false;
// 	},
//
// 	getItemId: function () {
// 		return this.itemId || this.id;
// 	}
// });
