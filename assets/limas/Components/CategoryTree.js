Ext.define('Limas.CategoryTree', {
	alias: 'widget.CategoryTree',
	extend: 'Ext.tree.Panel',
	categoryModel: null,
	viewConfig: {
		animate: false
	},
	loaded: false,
	rootVisible: false,

	_loaded: false,
	listeners: {
		itemexpand: {
			fn: function () {
				this.save2localStorage();
			}
		},
		itemcollapse: {
			fn: function () {
				this.save2localStorage();
			}
		},
		itemmove: {
			fn: function () {
				this.save2localStorage();
			}
		},
		load: {
			fn: function (me, records, successful) {
				if (!successful) {
					return;
				}
				let json = window.localStorage.getItem('limas/CategoryTree/collect/' + this.categoryModel);
				if (json !== null) {
					let cols = JSON.parse(json);
					this.store.getRoot().cascade(function (node) {
						if (node.isRoot() || node.isLeaf()) {
							return;
						}
						if (cols.includes(node.data.id)) {
							node.expand();
						} else {
							node.collapse();
						}
					});
				}
				this._loaded = true;
			}
		}
	},
	save2localStorage: function () {
		if (!this._loaded || this.categoryModel === null) {
			return;
		}
		window.localStorage.setItem('limas/CategoryTree/collect/' + this.categoryModel, JSON.stringify(this.store.collect('id')));
	}
});
