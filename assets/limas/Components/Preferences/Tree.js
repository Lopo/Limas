Ext.define('Limas.Components.Preferences.Tree', {
	extend: 'Ext.tree.Panel',
	width: 400,
	rootVisible: false,
	initComponent: function (config) {
		let menu = {
				root: {
					expanded: true,
					children: []
				}
			},
			target;

		for (let menuItemIterator = 0; menuItemIterator < this.menuItems.length; menuItemIterator++) {
			target = Ext.ClassManager.get(this.menuItems[menuItemIterator]);
			if (!target) {
				console.log('Error: ' + this.menuItems[menuItemIterator] + ' not found!');
			}
			if (!target.menuPath) {
				console.log('Error: ' + this.menuItems[menuItemIterator] + ' has no menuPath defined!');
			}

			this.createMenu(target, Ext.clone(target.menuPath), menu.root);
		}

		this.store = Ext.create('Ext.data.TreeStore', menu);

		this.callParent(this, config);
	},

	createMenu: function (target, menuPath, root) {
		let item = menuPath.shift();

		if (item === undefined) {
			root.children.push({
				text: target.title,
				iconCls: target.iconCls,
				expanded: true,
				target: target,
				leaf: true
			});
			return root;
		}

		let foundItem = false;

		for (var i = 0; i < root.children.length; i++) {
			if (root.children[i].text === item.text) {
				Ext.applyIf(root.children[i], item);
				foundItem = i;
			}
		}

		if (foundItem === false) {
			let newItem = {children: [], expanded: true};
			Ext.applyIf(newItem, item);

			root.children.push(this.createMenu(target, menuPath, newItem));
		} else {
			this.createMenu(target, menuPath, root.children[foundItem]);
		}

		return root;
	}
});
