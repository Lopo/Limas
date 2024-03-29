/**
 * @todo Document the editor system a bit better
 * The part manager encapsulates the category tree, the part display grid and the part detail view
 */
Ext.define('Limas.PartManager', {
	extend: 'Ext.panel.Panel',
	alias: 'widget.PartManager',
	layout: 'border',
	id: 'limas-partmanager',
	border: false,
	padding: 5,
	dragAndDrop: true,

	/**
	 * Defines if the border layout should be compact or regular
	 *
	 * Compact style stacks the tree panel and the part detail panel on top of each other to save space, which is a bit
	 * odd in terms of usability. Regular style means that the layout will be Category Tree->Part List->Part details.
	 *
	 * @var boolean True if compact layout should be used, false otherwise
	 */
	compactLayout: false,

	selectedCategory: null,

	initComponent: function () {
		/**
		 * Create the store with the default sorter 'name ASC'
		 */
		this.createStore({
			model: 'Limas.Entity.Part',
			groupField: 'categoryPath',
			sorters: [
				{
					property: 'category.categoryPath',
					direction: 'ASC'
				},
				{
					property: 'name',
					direction: 'ASC'
				}
			]
		});

		let treeConfig = {
			region: 'west',
			ddGroup: 'CategoryTree'
		};
		if (this.compactLayout) {
			treeConfig.region = 'center';
		} else {
			treeConfig.floatable = false;
			treeConfig.split = true;
			treeConfig.width = 300; // @todo Make this configurable
			treeConfig.title = i18n('Categories');
			treeConfig.collapsible = true; // We want to collapse the tree panel on small screens
		}

		// Create the tree
		this.tree = Ext.create('Limas.PartCategoryTree', treeConfig);

		// Trigger a grid reload on category change
		this.tree.on('itemclick', this.onCategoryClick, this);

		// Create the detail panel
		this.detail = Ext.create('Limas.PartDisplay', {title: i18n('Part Details')});
		this.detail.on('editPart', this.onEditPart, this);

		let gridConfig = {
			title: i18n('Parts List'),
			region: 'center',
			layout: 'fit',
			store: this.getStore(),
			itemId: 'partsGrid'
		};

		if (this.dragAndDrop) {
			gridConfig.viewConfig = {
				plugins: {
					ddGroup: 'PartTree',
					ptype: 'gridviewdragdrop',
					enableDrop: false
				}
			};
			gridConfig.enableDragDrop = true;
		}

		// Create the grid
		this.grid = Ext.create('Limas.PartsGrid', gridConfig);
		this.grid.on('editPart', this.onEditPart, this);

		// Create the grid listeners
		this.grid.on('itemSelect', this.onItemSelect, this);
		this.grid.on('itemDeselect', this.onItemSelect, this);
		this.grid.on('itemAdd', this.onItemAdd, this);
		this.grid.on('itemDelete', this.onItemDelete, this);
		this.grid.on('addMetaPart', this.onAddMetaPart, this);
		this.grid.on('duplicateItemWithBasicData', this.onDuplicateItemWithBasicData, this);
		this.grid.on('duplicateItemWithAllData', this.onDuplicateItemWithAllData, this);
		this.tree.on('syncCategory', this.onSyncCategory, this);

		// Create the stock level panel
		this.stockLevel = Ext.create('Limas.PartStockHistory', {title: 'Stock History'});

		let detailPanelConfig = {
			title: i18n('Part Details'),
			collapsed: true,
			collapsible: true,
			region: 'east',
			floatable: false,
			titleCollapse: true,
			split: true,
			animCollapse: false,
			items: [this.detail, this.stockLevel]
		};

		if (this.compactLayout) {
			detailPanelConfig.height = 300;
			detailPanelConfig.region = 'south';
		} else {
			detailPanelConfig.width = 300;
		}

		this.detailPanel = Ext.create('Ext.tab.Panel', detailPanelConfig);

		this.filterPanel = Ext.create('Limas.PartFilterPanel', {
			title: i18n('Filter'),
			region: 'south',
			height: 225,
			animCollapse: false,
			floatable: false,
			titleCollapse: true,
			split: true,
			collapsed: true,
			collapsible: true,
			store: this.store,
			partManager: this
		});

		this.thumbnailViewTpl = new Ext.XTemplate(
			'<tpl for=".">',
			'<div class="dataview-multisort-item iclogo"><img src="{[values["@id"]]}/getImage?maxWidth=100&maxHeight=100"/></div>',
			'</tpl>');

		this.thumbnailView = Ext.create('Ext.view.View', {
			tpl: this.thumbnailViewTpl,
			componentCls: 'manufacturer-ic-logos',
			itemSelector: 'div.dataview-multisort-item',
			store: {
				model: Limas.Entity.PartAttachment
			}
		});

		this.thumbnailView.on('selectionchange', function (selModel, selection) {
			let parts = [];
			for (let i = 0; i < selection.length; i++) {
				parts.push(selection[i].get('part'));
			}
			this.grid.getSelectionModel().select(parts);
		}, this);

		this.grid.store.on('update', function (store, record) {
			if (this.detail.record !== null && this.detail.record.getId() == record.getId()) {
				this.detail.setValues(record);
			}
		}, this);

		this.grid.store.on('load', function () {
			this.thumbnailView.getStore().removeAll();

			let data = this.grid.store.getData(),
				j, attachments, attachment;

			for (let i = 0; i < data.getCount(); i++) {
				attachments = data.getAt(i).attachments().getData();

				for (j = 0; j < attachments.getCount(); j++) {
					attachment = attachments.getAt(j);
					if (attachment.get("isImage")) {
						this.thumbnailView.getStore().add({
							'@id': attachment.get('@id'),
							'part': data.getAt(i)
						});
					}
				}
			}

			let t = new Ext.Template(i18n('Displaying {0} image(s) from {1} part(s)')),
				q = t.apply([this.thumbnailView.getStore().getCount(), this.grid.store.getCount()]);
			this.down('#thumbnailViewStatusMessage').setText(q);
		}, this);

		this.thumbnailViewToolbar = Ext.create('Ext.toolbar.Paging', {
			store: this.grid.store,
			enableOverflow: true,
			dock: 'bottom',
			displayInfo: false,
			items: [
				{xtype: 'tbfill'}, {
					xtype: 'tbtext',
					itemId: 'thumbnailViewStatusMessage'
				}
			]
		});

		this.thumbnailPanel = Ext.create('Ext.panel.Panel', {
			title: i18n('Thumbnail View'),
			scrollable: true,
			bbar: this.thumbnailViewToolbar,
			items: this.thumbnailView
		});

		this.tabPanel = Ext.create('Ext.tab.Panel', {
			region: 'center',
			items: [this.grid, this.thumbnailPanel]
		});

		this.thumbnailView.on('render', function () {
			this.loadMask = Ext.create('Ext.LoadMask', {
				store: this.grid.store,
				target: this.thumbnailPanel
			});

			this.thumbnailViewToolbar.onLoad();
		}, this);

		if (this.compactLayout) {
			// Create two border layouts: One for the center panel and one for the left panel. Each border layout
			// has two columns each, containing Categories+Part Details and Part List+Part Filter Panel.
			this.items = [
				{
					layout: 'border',
					border: false,
					region: 'west',
					animCollapse: false,
					width: 300,
					split: true,
					title: i18n('Categories / Part Details'),
					titleCollapse: true,
					collapsed: false,
					collapsible: true,
					items: [this.tree, this.detailPanel]
				}, {
					layout: 'border',
					border: false,
					region: 'center',
					items: [this.tabPanel, this.filterPanel]
				}
			];
		} else {
			// The regular 3-column layout. The tree, then the part list+part filter, then the part details.
			this.items = [
				this.tree, {
					layout: 'border',
					border: false,
					region: 'center',
					items: [this.tabPanel, this.filterPanel]
				}, this.detailPanel
			];
		}

		this.callParent();
	},
	/**
	 * Applies the category filter to the store when a category is selected
	 *
	 * @param {Ext.tree.View} tree The tree view
	 * @param {Ext.data.Model} record the selected record
	 */
	onCategoryClick: function (tree, record) {
		this.selectedCategory = record;

		let filter = Ext.create('Limas.util.Filter', {
			id: 'categoryFilter',
			property: 'category',
			operator: 'IN',
			value: this.getChildrenIds(record)
		});

		if (record.get('description') && record.get('description').trim() !== '') {
			this.grid.setNotification(record.get('description'));
		} else {
			this.grid.removeNotification();
		}

		if (record.parentNode.isRoot()) {
			// Workaround for big installations: Passing all child categories for the root node to the filter exceeds
			// the HTTP URI length. See https://github.com/partkeepr/PartKeepr/issues/473
			this.store.removeFilter(filter);
		} else {
			this.store.addFilter(filter);
		}
	},
	getSelectedCategory: function () {
		return this.selectedCategory;
	},
	/**
	 * Returns the ID for this node and all child nodes
	 *
	 * @param {Ext.data.Model} node The node
	 * @return Array
	 */
	getChildrenIds: function (node) {
		let childNodes = [node];
		if (node.hasChildNodes()) {
			for (let i = 0; i < node.childNodes.length; i++) {
				childNodes = childNodes.concat(this.getChildrenIds(node.childNodes[i]));
			}
		}
		return childNodes;
	},
	/**
	 * Called when the sync button was clicked. Highlights the category of the selected part for a short time. We can't
	 * select the category as this would affect the parts grid.
	 */
	onSyncCategory: function () {
		let r = this.grid.getSelectionModel().getSelection();
		if (r.length !== 1) {
			return;
		}

		let rootNode = this.tree.getRootNode(),
			cat = r[0].getCategory().getId(),
			node = rootNode.findChild('@id', cat, true);

		if (node) {
			this.tree.getView().ensureVisible(node);
			this.tree.getView().focusNode(node);
		}
	},
	/**
	 * Called when the delete button was clicked
	 *
	 * Prompts the user if he really wishes to delete the part. If yes, it calls deletePart.
	 */
	onItemDelete: function () {
		let r = this.grid.getSelectionModel().getLastSelected();
		Ext.Msg.confirm(i18n('Delete Part'), sprintf(i18n("Do you really wish to delete the part %s?"), r.get('name')),
			this.deletePart, this);
	},
	/**
	 * Creates a duplicate with the basic data only from the selected item. Loads the selected part and calls
	 * createPartDuplicate after the part was loaded.
	 */
	onDuplicateItemWithBasicData: function () {
		let r = this.grid.getSelectionModel().getLastSelected();
		this.loadPart(r.getId(), Ext.bind(this.createPartDuplicate, this));
	},
	/**
	 * Creates a full duplicate from the selected item. Loads the selected part and calls createPartDuplicate
	 * after the part was loaded.
	 */
	onDuplicateItemWithAllData: function () {
		let r = this.grid.getSelectionModel().getLastSelected();
		this.loadPart(r.getId(), Ext.bind(this.createFullPartDuplicate, this));
	},
	/**
	 * Creates a part duplicate from the given record and opens the editor window
	 * @param rec The record to duplicate
	 */
	createPartDuplicate: function (rec) {
		let data = rec.getData(),
			associationData = rec.getAssociationData(),
			newItem = Ext.create('Limas.Entity.Part');
		newItem.set(data);
		newItem.setAssociationData({
			category: associationData.category,
			partUnit: associationData.partUnit,
			storageLocation: associationData.storageLocation,
			footprint: associationData.footprint
		});

		let j = Ext.create('Limas.PartEditorWindow', {
			partMode: 'create'
		});

		j.editor.on('partSaved', this.onNewPartSaved, this);
		j.editor.editItem(newItem);
		j.show();
	},
	onAddMetaPart: function () {
		let defaults = {},
			j = Ext.create('Limas.Components.Part.Editor.MetaPartEditorWindow', {}),
			defaultPartUnit = Ext.data.StoreManager.lookup('PartMeasurementUnitStore').findRecord('default', true);

		Ext.apply(defaults, {metaPart: true});

		let record = Ext.create('Limas.Entity.Part', {
			metaPart: true
		});
		record.setCategory(this.getSelectedCategory() !== null ? this.getSelectedCategory() : this.tree.getRootNode().firstChild);
		record.setPartUnit(defaultPartUnit);

		j.editor.editItem(record);
		j.editor.on('partSaved', this.onNewPartSaved, this);
		j.show();
	},
	/**
	 * Creates a part duplicate from the given record and opens the editor window
	 * @param rec The record to duplicate
	 */
	createFullPartDuplicate: function (rec) {
		let data = rec.getData(),
			newItem = Ext.create('Limas.Entity.Part');
		newItem.set(data);
		newItem.setAssociationData(rec.getAssociationData());

		let j = Ext.create('Limas.PartEditorWindow', {
			partMode: 'create'
		});
		j.editor.on('partSaved', this.onNewPartSaved, this);
		j.editor.editItem(newItem);
		j.show();
	},
	/**
	 * Deletes the selected part
	 *
	 * @param {String} btn The clicked button in the message box window
	 * @todo We use the current selection of the grid. If for some reason the selection changes during the user is prompted,
	 * we delete the wrong part. Fix that to pass the selected item to the onItemDelete then to this function.
	 */
	deletePart: function (btn) {
		let r = this.grid.getSelectionModel().getLastSelected();

		if (btn == 'yes') {
			this.detailPanel.collapse();
			this.detail.clear();
			r.erase();
		}
	},
	/**
	 * Creates a new, empty part editor window
	 */
	onItemAdd: function (defaults) {
		let j = Ext.create('Limas.PartEditorWindow', {
				partMode: 'create'
			}),
			defaultPartUnit = Ext.data.StoreManager.lookup('PartMeasurementUnitStore').findRecord('default', true);

		Ext.apply(defaults, {});

		let record = Ext.create('Limas.Entity.Part', defaults);
		record.setCategory(this.getSelectedCategory() !== null ? this.getSelectedCategory() : this.tree.getRootNode().firstChild);
		record.setPartUnit(defaultPartUnit);

		j.editor.editItem(record);
		j.editor.on('partSaved', this.onNewPartSaved, this);
		j.show();

		return j;
	},
	/**
	 * Called when a part was edited. Refreshes the grid.
	 */
	onEditPart: function (part) {
		let editorWindow = Ext.create(part.get('metaPart') === true ? 'Limas.Components.Part.Editor.MetaPartEditorWindow' : 'Limas.PartEditorWindow');
		editorWindow.editor.on('partSaved', this.onPartSaved, this);
		editorWindow.editor.editItem(part);
		editorWindow.show();
	},
	onNewPartSaved: function () {
		this.grid.getStore().reload();
	},
	onPartSaved: function (record) {
		this.detail.setValues(record);
	},
	/**
	 * Called when a part was selected in the grid. Displays the details for this part.
	 */
	onItemSelect: function () {
		if (this.grid.getSelection().length > 1) {
			this.detailPanel.collapse();
			this.tree.syncButton.disable();
		} else {
			if (this.grid.getSelection().length === 1) {
				let r = this.grid.getSelection()[0];

				this.detailPanel.setActiveTab(this.detail);
				this.detail.setValues(r);
				this.detailPanel.expand();
				this.stockLevel.part = r.getId();

				this.tree.syncButton.enable();
			} else {
				this.tree.syncButton.disable();
			}
		}
	},
	/**
	 * Triggers loading of a part
	 * @param {Integer} id The ID of the part to load
	 * @param {Function} handler The callback to call when the part was loaded
	 */
	loadPart: function (id, handler) {
		// @todo we have this method duplicated in PartEditor
		Limas.Entity.Part.load(id, {
			scope: this,
			success: handler
		});
	},
	createStore: function (config) {
		this.store = Ext.create('Limas.Data.Store.PartStore');
	},
	getStore: function () {
		return this.store;
	},
	statics: {
		formatParameter: function (partParameter) {
			let minSiPrefix = '', siPrefix = '', maxSiPrefix = '', unit = '', minValue = '', maxValue = '', value = '',
				minMaxCombined = '';

			if (partParameter.get('valueType') === 'string') {
				return partParameter.get('stringValue');
			}

			if (partParameter.getUnit() instanceof Limas.Entity.Unit) {
				unit = partParameter.getUnit().get('symbol');
			}

			if (partParameter.getMinSiPrefix() instanceof Limas.Entity.SiPrefix) {
				minSiPrefix = partParameter.getMinSiPrefix().get('symbol');
			}

			if (partParameter.getSiPrefix() instanceof Limas.Entity.SiPrefix) {
				siPrefix = partParameter.getSiPrefix().get('symbol');
			}

			if (partParameter.getMaxSiPrefix() instanceof Limas.Entity.SiPrefix) {
				maxSiPrefix = partParameter.getMaxSiPrefix().get('symbol');
			}

			if (partParameter.get('value') !== null && partParameter.get('value') !== '') {
				value = partParameter.get('value');
			}

			if (partParameter.get('minValue') !== null && partParameter.get('minValue') !== '') {
				minValue = partParameter.get('minValue');
			}

			if (partParameter.get('maxValue') !== null && partParameter.get('maxValue') !== '') {
				maxValue = partParameter.get('maxValue');
			}

			if (minValue !== '' && maxValue !== '') {
				minMaxCombined = minValue + ' ' + minSiPrefix + '…' + maxValue + ' ' + maxSiPrefix + unit;
			} else {
				if (minValue !== '') {
					minMaxCombined = i18n('Min.') + minValue + ' ' + minSiPrefix + unit;
				}

				if (maxValue !== '') {
					minMaxCombined = i18n('Max.') + maxValue + ' ' + maxSiPrefix + unit;
				}
			}

			if (value !== '') {
				if (minMaxCombined !== '') {
					return value + ' ' + siPrefix + unit + ' (' + minMaxCombined + ')';
				}
				return value + ' ' + siPrefix + unit;
			}
			return minMaxCombined;
		}
	}
});
