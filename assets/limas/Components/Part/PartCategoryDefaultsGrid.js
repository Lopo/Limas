/**
 * Grid of default parameter templates owned by a single PartCategory. Rendered
 * inside PartCategoryEditorWindow's "Default Parameters" tab.
 *
 * Bound directly to the categoryRecord's `defaultParameters` association
 * collection (which the tree endpoint serializes via the `default` group) —
 * no remote filter needed. Limas's AdvancedSearchFilter doesn't understand
 * `extraParams.category=...`, so a remote GET would have returned every
 * row from every category. Reading via the association sidesteps that.
 *
 * Edits accumulate locally; persistChanges() flushes them (POST/PATCH/DELETE)
 * after the category record has been saved (we need its id before any FK
 * insert).
 */
Ext.define('Limas.Components.Part.PartCategoryDefaultsGrid', {
	extend: 'Ext.grid.Panel',
	alias: 'widget.PartCategoryDefaultsGrid',

	border: false,
	plugins: [{ptype: 'cellediting', clicksToEdit: 1}],

	categoryRecord: null,
	pendingNewRows: null,
	pendingRemovedRows: null,

	initComponent: function () {
		this.pendingNewRows = [];
		this.pendingRemovedRows = [];

		// Placeholder empty store; replaced on bindCategoryRecord with the
		// categoryRecord's defaultParameters() association store.
		this.store = Ext.create('Ext.data.Store', {
			model: 'Limas.Entity.PartCategoryDefaultParameter',
			autoLoad: false,
			autoSync: false
		});

		this.columns = [
			{
				header: i18n('Name'), dataIndex: 'name', flex: 2,
				editor: {xtype: 'textfield', allowBlank: false}
			},
			{
				header: i18n('Description'), dataIndex: 'description', flex: 3,
				editor: {xtype: 'textfield'}
			},
			{
				header: i18n('Value type'), dataIndex: 'valueType', width: 100,
				editor: {
					xtype: 'combo',
					store: ['string', 'numeric'],
					editable: false
				}
			},
			{
				header: i18n('Unit'), dataIndex: 'unit', width: 110,
				renderer: function (v, m, rec) {
					// returnObject combo writes an ExtJS Model into the cell,
					// so v.name is undefined — use the model's getter / get('name')
					if (rec && typeof rec.getUnit === 'function') {
						let unit = rec.getUnit();
						if (unit && typeof unit.get === 'function') {
							return Ext.htmlEncode(unit.get('name') || '');
						}
					}
					if (v && typeof v.get === 'function') {
						return Ext.htmlEncode(v.get('name') || '');
					}
					if (v && typeof v === 'object' && v.name) {
						return Ext.htmlEncode(v.name);
					}
					return '';
				},
				editor: {xtype: 'UnitComboBox', returnObject: true}
			}
		];

		this.addButton = Ext.create('Ext.button.Button', {
			text: i18n('Add'),
			iconCls: 'web-icon add',
			// Stays disabled until a non-phantom category record is bound —
			// panel.disabled doesn't reach docked toolbars, so the button
			// must be muted explicitly
			disabled: true,
			handler: Ext.bind(this.onAdd, this)
		});

		this.deleteButton = Ext.create('Ext.button.Button', {
			text: i18n('Delete'),
			iconCls: 'web-icon delete',
			disabled: true,
			handler: Ext.bind(this.onDelete, this)
		});

		this.dockedItems = [{
			xtype: 'toolbar',
			dock: 'top',
			items: [this.addButton, this.deleteButton]
		}];

		this.callParent();

		this.getSelectionModel().on('selectionchange', function (sm, selections) {
			this.deleteButton.setDisabled(selections.length === 0);
		}, this);
	},

	bindCategoryRecord: function (categoryRecord) {
		this.categoryRecord = categoryRecord;
		this.pendingNewRows = [];
		this.pendingRemovedRows = [];
		// Bind to the association collection so we render only this category's
		// rows; Limas's AdvancedSearchFilter doesn't understand
		// `extraParams.category=...` so a remote filter wouldn't work
		this.bindStore(categoryRecord.defaultParameters());
		this.addButton.setDisabled(false);
	},

	onAdd: function () {
		if (!this.categoryRecord || this.categoryRecord.phantom) {
			return;
		}
		let rec = Ext.create('Limas.Entity.PartCategoryDefaultParameter', {
			name: '',
			description: '',
			valueType: 'string',
			category: this.categoryRecord.get('@id')
		});
		this.store.insert(0, rec);
		this.pendingNewRows.push(rec);
		this.findPlugin('cellediting').startEditByPosition({row: 0, column: 0});
	},

	onDelete: function () {
		let sel = this.getSelectionModel().getSelection();
		sel.forEach(function (rec) {
			if (rec.phantom) {
				let idx = this.pendingNewRows.indexOf(rec);
				if (idx !== -1) {
					this.pendingNewRows.splice(idx, 1);
				}
			} else {
				this.pendingRemovedRows.push(rec);
			}
			this.store.remove(rec);
		}, this);
	},

	/**
	 * Flush local changes:
	 *   - DELETE for previously-saved rows the user removed
	 *   - POST for new rows (need category FK on the body)
	 *   - PATCH for dirty existing rows
	 * Then invoke the callback so the host window can fire 'save' + close
	 */
	persistChanges: function (callback) {
		let dirty = this.store.getUpdatedRecords().filter(function (rec) {
			return !rec.phantom && this.pendingRemovedRows.indexOf(rec) === -1;
		}, this);
		let creates = this.pendingNewRows.filter(function (rec) {
			return rec.get('name') !== '';
		});
		let deletes = this.pendingRemovedRows.slice();
		let total = creates.length + dirty.length + deletes.length;
		if (total === 0) {
			callback();
			return;
		}
		let remaining = total;
		let done = function () {
			if (--remaining === 0) {
				this.pendingNewRows = [];
				this.pendingRemovedRows = [];
				callback();
			}
		}.bind(this);

		// New rows need the category FK on the request body — Hydra writer
		// reads from the model fields, so we set it before save.
		creates.forEach(function (rec) {
			rec.set('category', this.categoryRecord.get('@id'));
			rec.save({success: done, failure: done});
		}, this);
		dirty.forEach(function (rec) {
			rec.save({success: done, failure: done});
		});
		deletes.forEach(function (rec) {
			rec.erase({success: done, failure: done});
		});
	}
});
