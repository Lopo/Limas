/**
 * <p>The EditorComponent encapsulates an editing workflow. In general, we have four actions for each object: create,
 * update, delete, view. These actions stay exactly the same for each distinct object.</p>
 * <p>The EditorComponent is a border layout, which has a navigation and an editor area.</p>
 * @todo Document the editor system a bit better
 */
Ext.define('Limas.EditorComponent', {
	extend: 'Ext.panel.Panel',
	alias: 'widget.EditorComponent',

	/**
	 * Misc layout settings
	 */
	layout: 'border',
	padding: 5,
	border: false,

	/**
	 * Specifies the class name of the navigation. The navigation is placed in the "west" region and needs to fire
	 * the event "itemSelect". The component listens on that event and creates an editor based on the selected record.
	 */
	navigationClass: null,
	/**
	 * Width of the navigation (west region) in pixels. Subclasses with
	 * grid-heavy navigation (many columns, filters, bulk actions —
	 * ParameterAliasEditorComponent) override this to claim most of the
	 * window; form-light editors leave the default.
	 */
	navigationWidth: 300,
	/**
	 * Specifies the class name of the editor
	 */
	editorClass: null,
	/**
	 * Contains the store for the item overview
	 */
	store: null,
	/**
	 * Contains the associated model to load a record for
	 */
	model: null,
	/**
	 * Defines the store to use. Defaults to {Ext.data.Store}
	 */
	storeType: 'Ext.data.Store',

	/**
	 * Some default text messages. Can be overridden by sub classes.
	 */
	deleteMessage: i18n('Do you really wish to delete the item %s?'),
	deleteTitle: i18n('Delete Item'),
	newItemText: i18n('New Item'),

	/**
	 * @var {string} The record field to read the title property from
	 */
	titleProperty: 'name',

	initComponent: function () {
		this.navigation = Ext.create(this.navigationClass, {
			region: 'west',
			width: this.navigationWidth,
			split: true,
			store: this.store,
			titleProperty: this.titleProperty
		});

		this.navigation.on('itemAdd', this.newRecord, this);
		this.navigation.on('itemDelete', this.confirmDelete, this);
		this.navigation.on('itemEdit', this.startEdit, this);

		this.editorTabPanel = Ext.create('Ext.tab.Panel', {
			region: 'center',
			layout: 'fit',
			plugins: [
				Ext.create('Ext.ux.TabCloseMenu'),
				Ext.create('Ext.ux.TabCloseOnMiddleClick')
			]
		});

		this.items = [this.navigation, this.editorTabPanel];

		this.callParent();
	},
	/**
	 * Creates a new record. Creates a new instance of the editor.
	 */
	newRecord: function (defaults) {
		Ext.apply(defaults, {});

		let editor = this.createEditor(this.newItemText);
		editor.newItem(defaults);
		this.editorTabPanel.add(editor).show();
	},
	/**
	 * Instructs the component to edit a new record
	 * @param {Record} record The record to edit
	 */
	startEdit: function (id) {
		/* Search for an open editor for the current record. If we already have an editor, show the editor instead
		 of loading a new record.
		 */
		let editor = this.findEditor(id);
		if (editor !== null) {
			editor.show();
			return;
		}

		// Still here? OK, we don't have an editor open. Create a new one
		let model = Ext.ClassManager.get(this.model);

		model.load(id, {
			scope: this,
			success: function (record, operation) {
				editor = this.createEditor(record.get(this.titleProperty));
				editor.editItem(record);
				this.editorTabPanel.add(editor).show();
			}
		});
	},
	findEditor: function (id) {
		for (let i = 0; i < this.editorTabPanel.items.getCount(); i++) {
			if (this.editorTabPanel.items.getAt(i).getRecordId() == id) {
				return this.editorTabPanel.items.getAt(i);
			}
		}

		return null;
	},
	createEditor: function (title) {
		let editor = Ext.create(this.editorClass, {
			store: this.store,
			title: title,
			model: this.model,
			closable: true,
			titleProperty: this.titleProperty,
			listeners: {
				editorClose: Ext.bind(function (m) {
					this.editorTabPanel.remove(m);
				}, this)
			}
		});

		editor.on('itemSaved', this.onItemSaved, this);
		return editor;
	},
	confirmDelete: function () {
		let selection = this.navigation.getSelectionModel().getSelection();
		if (!selection || selection.length === 0) {
			return;
		}

		let prompt;
		if (selection.length === 1) {
			prompt = sprintf(this.deleteMessage, selection[0].get(this.titleProperty));
		} else {
			prompt = sprintf(i18n('Delete %d selected items?'), selection.length);
		}

		Ext.Msg.confirm(
			this.deleteTitle,
			prompt,
			function (but) {
				if (but === 'yes') {
					this.deleteRecords(selection);
				}
			}, this);
	},
	deleteRecords: function (records) {
		// Most admin entities (Manufacturer/Distributor/Footprint/Unit/…)
		// have foreign keys WITHOUT ON DELETE clauses — MySQL defaults to
		// RESTRICT, so trying to delete one that's still referenced by a
		// Part/PartParameter/etc. throws an FK violation. We hit the IRI
		// directly via Ext.Ajax instead of r.erase() to bypass HydraProxy's
		// global exception listener (it pops an ExceptionWindow per
		// failure, which would spam the user with N popups on a bulk
		// delete). Failures are summarised in a single toast instead.
		let total = records.length;
		let deleted = 0;
		let failed = 0;
		let failedNames = [];
		let remaining = total;

		let done = function () {
			if (--remaining > 0) return;
			this.store.load();
			if (failed === 0) {
				if (total > 1) {
					Ext.toast({html: Ext.String.format(i18n('Deleted {0} items.'), deleted), align: 't', autoCloseDelay: 3000});
				}
				return;
			}
			let msg = Ext.String.format(i18n('Deleted {0}, {1} failed.'), deleted, failed);
			if (failedNames.length > 0) {
				msg += '<br><span class="limas-text-muted">' + Ext.htmlEncode(failedNames.slice(0, 5).join(', '));
				if (failedNames.length > 5) msg += ' …';
				msg += '</span><br>' + i18n('(still referenced elsewhere)');
			}
			Ext.toast({html: msg, align: 't', autoCloseDelay: 8000});
		}.bind(this);

		records.forEach(function (r) {
			let editor = this.findEditor(r.getId());
			if (editor !== null) {
				this.editorTabPanel.remove(editor);
			}
			let iri = r.getId();
			Ext.Ajax.request({
				url: Limas.getBasePath() + iri,
				method: 'DELETE',
				headers: Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders(),
				success: function () {
					deleted++;
					done();
				},
				failure: Ext.bind(function () {
					failed++;
					failedNames.push(r.get(this.titleProperty) || ('#' + iri));
					done();
				}, this)
			});
		}, this);
	},
	// Kept as a thin compat wrapper — older callers may invoke it directly.
	deleteRecord: function (r) {
		this.deleteRecords([r]);
	},
	// Creates a store. To be called from child's initComponent
	createStore: function (config) {
		Ext.applyIf(config, {
			autoLoad: true,
			model: this.model,
			autoSync: false, // Do not change. If true, new (empty) records would be immediately committed to the database.
			remoteFilter: true,
			remoteSort: true,
			pageSize: 15
		});

		this.store = Ext.create(this.storeType, config);

		// Workaround for bug http://www.sencha.com/forum/showthread.php?133767-Store.sync()-does-not-update-dirty-flag&p=607093#post607093
		this.store.on('write', function (store, operation) {
			if (operation.wasSuccessful()) {
				Ext.each(operation.records, function (record) {
					if (record.dirty) {
						record.commit();
					}
				});
			}
		});
	},
	getStore: function () {
		return this.store;
	},
	onItemSaved: function (record) {
		this.navigation.syncChanges(record);
	}
});
