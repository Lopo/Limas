// https://forum.sencha.com/forum/showthread.php?259521-Ext.ux.TabCloseOnMiddleClick
Ext.define('Ext.ux.TabCloseOnMiddleClick', {
	alias: 'plugin.TabCloseOnMiddleClick',

	mixins: {
		observable: 'Ext.util.Observable'
	},

	init: function (tabpanel) {
		this.tabPanel = tabpanel;
		this.tabBar = tabpanel.down('tabbar');

		this.mon(this.tabPanel, {
			scope: this,
			afterlayout: this.onAfterLayout,
			single: true
		});
	},

	onAfterLayout: function () {
		this.mon(this.tabBar.el, {
			scope: this,
			mousedown: this.onMouseDown,
			delegate: '.x-tab'
		});
		this.mon(this.tabBar.el, {
			scope: this,
			mouseup: this.onMouseUp,
			delegate: '.x-tab'
		});
	},

	onMouseDown: function (e) {
		e.preventDefault();
	},

	onMouseUp: function (e, target) {
		e.preventDefault();

		if (target && e.browserEvent.button === 1) {
			let item = this.tabBar.getComponent(target.id);
			if (item.closable) {
				item.onCloseClick();
			}
		}
	}
});
