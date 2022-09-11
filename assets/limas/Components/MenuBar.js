Ext.define('Limas.MenuBar', {
	extend: 'Ext.toolbar.Toolbar',
	alias: 'widget.MenuBar',

	baseCls: Ext.baseCSSPrefix + 'toolbar mainMenu',

	menu: {
		text: 'Root',
		menu: []
	},

	createMenu: function (target, menuPath, root) {
		let item = menuPath.shift(), newItem;

		if (item === undefined) {
			newItem = {text: target.title, iconCls: target.iconCls, target: target};
			root.menu.push(newItem);
			return root;
		}

		let foundItem = false;
		for (let i = 0; i < root.menu.length; i++) {
			if (root.menu[i].text === item.text) {
				Ext.applyIf(root.menu[i], item);
				foundItem = i;
			}
		}

		if (foundItem === false) {
			newItem = {menu: []};
			Ext.applyIf(newItem, item);

			root.menu.push(this.createMenu(target, menuPath, newItem));
		} else {
			this.createMenu(target, menuPath, root.menu[foundItem]);
		}

		return root;
	},
	initComponent: function () {
		let target, menuItemIterator;

		this.ui = 'mainmenu';

		let menuItems = [
			// System Menu
			'Limas.Components.UserPreferences.Panel',
			'Limas.Components.SystemPreferences.Panel',
			'Limas.Actions.LogoutAction',

			// Edit Menu
			'Limas.ProjectEditorComponent',
			'Limas.FootprintEditorComponent',
			'Limas.ManufacturerEditorComponent',
			'Limas.StorageLocationEditorComponent',
			'Limas.DistributorEditorComponent',
			'Limas.UserEditorComponent',
			'Limas.PartMeasurementUnitEditorComponent',
			'Limas.UnitEditorComponent',
			'Limas.BatchJobEditorComponent',

			// View Menu
			'Limas.SummaryStatisticsPanel',
			'Limas.StatisticsChartPanel',
			'Limas.SystemInformationGrid',
			'Limas.ProjectReportView',
			'Limas.ProjectRunEditorComponent',
			'Limas.SystemNoticeEditorComponent',
			'Limas.StockHistoryGrid',
			'Limas.ThemeTester'
		];

		this.menu.menu.push({xtype: 'tbspacer'});

		for (menuItemIterator = 0; menuItemIterator < menuItems.length; menuItemIterator++) {
			target = Ext.ClassManager.get(menuItems[menuItemIterator]);
			if (!target) {
				Ext.raise('Error: ' + menuItems[menuItemIterator] + ' not found!');
			}
			if (!target.menuPath) {
				Ext.raise('Error: ' + menuItems[menuItemIterator] + ' has no menuPath defined!');
			}
			this.createMenu(target, target.menuPath, this.menu);
		}

		this.themesMenu = [];
		this.themesMenu.push({
			text: 'Warning: Theme support is a beta-feature!',
			disabled: true
		});

		let checked;
		for (let i in window.themes) {
			checked = window.theme === i;
			this.themesMenu.push({
				text: window.themes[i].themeName,
				theme: i,
				group: 'theme',
				checked: checked
			});
		}

		this.menu.menu.push({text: i18n('Theme'), type: 'themes', menu: this.themesMenu});
		this.menu.menu.push({xtype: 'tbspacer', width: 50});

		this.menu.menu.push({xtype: 'tbfill'});
		this.menu.menu.push({xtype: 'button', iconCls: 'partkeeprLogo'});
		this.menu.menu.push({xtype: 'tbspacer', width: 10});

		this.items = this.menu.menu;

		this.callParent();
	},
	selectTheme: function (theme) {
		let j, menuItem;

		for (let i = 0; i < this.items.getCount(); i++) {
			if (this.items.getAt(i).type === 'themes') {
				for (j = 0; j < this.items.getAt(i).menu.items.getCount(); j++) {
					menuItem = this.items.getAt(i).menu.items.getAt(j);
					if (menuItem.theme === theme) {
						menuItem.setChecked(true);
					}
				}
			}
		}
	}
});
