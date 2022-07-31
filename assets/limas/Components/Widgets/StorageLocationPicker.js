Ext.define('Limas.StorageLocationPicker', {
	extend: 'Ext.form.field.Picker',
	alias: 'widget.StorageLocationPicker',

	/**
	 * @cfg {Number} typeAheadDelay
	 * The length of time in milliseconds to wait until the typeahead function is called
	 */
	typeAheadDelay: 250,
	/**
	 * @var {Ext.util.DelayedTask} typeAheadTask
	 * The internal task for the typeAhead delay
	 */
	typeAheadTask: null,
	/**
	 * @var {Limas.Entity.StorageLocation} selectedStorageLocation
	 */
	selectedStorageLocation: null,

	textValue: '',

	enableKeyEvents: true,

	listeners: {
		specialkey: {
			fn: 'keyHandler',
			scope: 'this'
		}
	},

	initComponent: function () {
		this.store = Ext.create('Ext.data.Store', {
			model: 'Limas.Entity.StorageLocation',
			autoLoad: true,
			remoteFilter: true,
			remoteSort: true,
			sorters: [
				{
					property: 'category.categoryPath',
					direction: 'ASC'
				}, {
					property: 'name',
					direction: 'ASC'
				}
			],
			groupField: 'categoryPath'
		});

		this.on('keyup', Ext.bind(this.onFieldChange, this));
		this.on('blur', Ext.bind(this.onBlur, this));

		this.callParent();
	},
	onFieldChange: function (field, e) {
		let newValue = this.inputEl.getValue();

		if (!this.typeAheadTask) {
			this.typeAheadTask = new Ext.util.DelayedTask(this.onTypeAhead, this, [newValue]);
		}

		this.typeAheadTask.delay(this.typeAheadDelay, false, false, [newValue]);
	},
	/**
	 * Handles special keys used in this field
	 *
	 * Enter: Starts the search
	 * Escape: Removes the search and clears the field contents
	 */
	keyHandler: function (field, e) {
		let grid = this.getPicker().getGrid();

		switch (e.getKey()) {
			case e.DOWN:
				var currentSelection = grid.getSelectionModel().getSelection();
				if (currentSelection.length === 0) {
					grid.getSelectionModel().select(0);
				} else {
					let index = grid.getStore().indexOf(currentSelection[0]) + 1;
					if (index < grid.getStore().count()) {
						grid.getSelectionModel().select(index);
						grid.getView().focusRow(grid.getStore().getAt(index));
					}
				}
				break;
			case e.UP:
				var currentSelection = grid.getSelectionModel().getSelection();
				if (currentSelection.length === 0) {
					grid.getSelectionModel().select(grid.getStore().count());
				} else {
					let index = grid.getStore().indexOf(currentSelection[0]) - 1;
					if (index >= 0) {
						grid.getSelectionModel().select(index);
						grid.getView().focusRow(grid.getStore().getAt(index));
					}
				}
				break;
			case e.ENTER:
				if (!this.isExpanded) {
					this.expand();
					return;
				}
				this.applyGridSelection(grid);
				break;
			case e.TAB:
				this.applyGridSelection(grid);
				break;
		}
	},
	applyGridSelection: function (grid) {
		let currentSelection = grid.getSelectionModel().getSelection();
		if (currentSelection.length === 1) {
			this.setValue(currentSelection[0]);
		}

		this.collapse();
	},
	getValue: function () {
		return this.selectedStorageLocation;
	},
	onTypeAhead: function (newValue) {
		let picker = this.getPicker();

		if (picker.getTree().getStore().isLoading()) {
			Ext.defer(this.onTypeAhead, 200, this, [newValue]);
			return;
		}

		if (newValue !== this.textValue) {
			picker.setCategoryFilter(picker.getTree().getRootNode().firstChild);
			picker.getTree().getSelectionModel().select(picker.getTree().getRootNode().firstChild);
			picker.setSearchValue(newValue);
			picker.getGrid().getSelectionModel().deselectAll();
			this.expand();
			this.textValue = newValue;
		}
	},
	onBlur: function () {
		let picker = this.getPicker();
		if (picker.getGrid().getStore().count() === 1) {
			this.setValue(picker.getGrid().getStore().getAt(0));
		}

		this.validate();
	},
	setValue: function (value) {
		if (value === null || !(value instanceof Limas.Entity.StorageLocation)) {
			return;
		}
		this.selectedStorageLocation = value;
		this.textValue = value.get('name');
		Limas.StorageLocationPicker.superclass.setValue.call(this, value.get('name'));
		this.validate();
	},
	getErrors: function (value) {
		let errors = this.callParent(arguments);

		if (!this.inputEl) {
			return errors;
		}

		if (!(this.selectedStorageLocation instanceof Limas.Entity.StorageLocation) ||
			this.inputEl.getValue() !== this.selectedStorageLocation.get('name')) {
			errors.push(i18n('An existing storage location must be selected'));
		}

		return errors;
	},
	/**
	 * Creates and returns the tree panel to be used as this field's picker
	 */
	createPicker: function () {
		let me = this,
			picker = new Limas.StorageLocationNavigation({
				store: me.store,
				floating: true,
				minHeight: me.minPickerHeight,
				maxHeight: me.maxPickerHeight,
				manageHeight: false,
				shadow: false,
				height: 250,
				verticalLayout: true,
				dragAndDrop: false,
				categoryEditActions: false,
				itemEditActions: false,
				editItemAsObject: true,
				listeners: {
					itemEdit: function (v) {
						this.setValue(v);
						this.collapse();
					},
					scope: this
				}
			});

		return picker;
	}
});
