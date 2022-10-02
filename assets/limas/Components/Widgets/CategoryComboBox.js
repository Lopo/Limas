Ext.define('Limas.CategoryComboBox', {
	extend: 'Limas.Widgets.TreePicker',
	alias: 'widget.CategoryComboBox',

	editable: true,

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
	 * The selected storage location
	 */
	selectedCategory: null,

	enableKeyEvents: true,

	listeners: {
		'specialkey': {
			fn: 'keyHandler',
			scope: 'this'
		}
	},

	triggers: {
		reload: {
			cls: 'x-form-reload-trigger',
			weight: -1,
			handler: function () {
				this.store.load();
			},
			scope: 'this'
		}
	},

	_oldValue: null,

	initComponent: function () {
		this.store = Ext.create('Limas.Data.store.PartCategoryStore');

		this.on('keyup', Ext.bind(this.onFieldChange, this));
		this.on('blur', Ext.bind(this.onBlur, this));

		this.listenersStore = this.store.on({
			scope: this,
			// Workaround to remember the value when loading
			beforeload: function () {
				this._oldValue = this.getValue();
			},
			// Set the old value when load is complete
			load: function () {
				if (this._oldValue !== null) {
					this.setValue(this._oldValue);
				}
			}
		});

		this.callParent();
	},
	onBlur: function () {
		this.applySelection();
		this.validate();
	},
	onFieldChange: function () {
		let newValue = this.inputEl.getValue();

		if (!this.typeAheadTask) {
			this.typeAheadTask = new Ext.util.DelayedTask(this.onTypeAhead, this, [newValue]);
		}

		this.typeAheadTask.delay(this.typeAheadDelay, false, false, [newValue]);
	},
	setValue: function (value) {
		this.textValue = value !== null ? value.get('name') : '';
		this.callParent(arguments);
		this.validate();
	},
	onTypeAhead: function (newValue) {
		if (newValue !== this.textValue) {
			let picker = this.getPicker(),
				node = picker.getStore().findNode('name', newValue, false, false);

			this.expand();
			if (node !== null) {
				picker.getSelectionModel().select(node);
			} else {
				picker.getSelectionModel().deselectAll();
			}

			this.inputEl.focus();

			this.textValue = newValue;
		}
	},
	/**
	 * Handles special keys used in this field
	 *
	 * Enter: Starts the search
	 * Escape: Removes the search and clears the field contents
	 */
	keyHandler: function (field, e) {
		let picker = this.getPicker(),
			currentSelection, index;
		switch (e.getKey()) {
			case e.DOWN:
				currentSelection = picker.getSelectionModel().getSelection();

				if (currentSelection.length === 0) {
					picker.getSelectionModel().select(0);
				} else {
					index = picker.getStore().indexOf(currentSelection[0]) + 1;
					if (index < picker.getStore().count()) {
						picker.getSelectionModel().select(index);
					}
				}
				break;
			case e.UP:
				currentSelection = picker.getSelectionModel().getSelection();
				if (!currentSelection.length) {
					picker.getSelectionModel().select(0);
				} else {
					index = picker.getStore().indexOf(currentSelection[0]) - 1;
					if (index >= 0) {
						picker.getSelectionModel().select(index);
					}
				}
				break;
			case e.ENTER:
				if (!this.isExpanded) {
					this.expand();
					return;
				}
				this.applySelection();
				break;
			case e.TAB:
				this.applySelection();
				break;
		}

		this.inputEl.focus();
	},
	applySelection: function () {
		let currentSelection = this.getPicker().getSelectionModel().getSelection();
		if (currentSelection.length === 1) {
			this.setValue(currentSelection[0]);
		}

		this.collapse();
	},
	getErrors: function (value) {
		let errors = this.callParent(arguments);
		if (!this.inputEl) {
			return errors;
		}

		if (!(this.getValue() instanceof Limas.Entity.PartCategory) ||
			this.inputEl.getValue() !== this.getValue().get('name')) {
			errors.push(i18n('A category must be selected'));
		}

		return errors;
	}
});
