Ext.define('Limas.PartEditorWindow', {
	extend: 'Ext.window.Window',

	/* Constrain the window to fit the viewport */
	constrainHeader: true,

	/* Fit the editor within the window */
	layout: 'fit',

	width: 750,
	minWidth: 600,
	minHeight: 435,
	height: 435,

	saveText: i18n('Save'),
	cancelText: i18n('Cancel'),

	/* Default edit mode. If mode = "create", we show additional fields */
	partMode: 'edit',
	title: i18n('Add Part'),

	saveButtonReenableTask: null,

	initComponent: function () {
		this.editor = Ext.create('Limas.PartEditor', {
			border: false,
			partMode: this.partMode,
			enableButtons: false
		});

		/* If the edit mode is "create", we need to enlarge the window a bit to fit the fields without scrolling */
		if (this.partMode && this.partMode === 'create') {
			this.height = 500;
			this.minHeight = 500;
		}

		this.items = [this.editor];

		/**
		 * We need a delay, since if others are listening for "editorClose", the dialog plus the record could be destroyed
		 * before any following listeners have a chance to receive the record, resulting in strange problems.
		 */
		this.editor.on('editorClose', function () {
			this.close();
		}, this, {delay: 200});

		this.editor.on('_titleChange', function (val) {
			this.setTitle(val);
		}, this);
		this.editor.on('itemSaved', this.onItemSaved, this);

		this.octoPartButton = Ext.create('Ext.button.Button', {
			text: i18n('Octopart…'),
			iconCls: 'partkeepr-icon octopart',
			handler: Ext.bind(this.onOctoPartClick, this)
		});

		this.saveButton = Ext.create('Ext.button.Button', {
			text: this.saveText,
			iconCls: 'fugue-icon disk',
			handler: Ext.bind(this.onItemSave, this)
		});

		this.cancelButton = Ext.create('Ext.button.Button', {
			text: this.cancelText,
			iconCls: 'web-icon cancel',
			handler: Ext.bind(this.onCancelEdit, this)
		});

		this.bottomToolbar = Ext.create('Ext.toolbar.Toolbar', {
			enableOverflow: true,
			defaults: {minWidth: 100},
			dock: 'bottom',
			ui: 'footer',
			pack: 'start',
			items: [this.saveButton, this.cancelButton, this.octoPartButton]
		});

		this.dockedItems = [this.bottomToolbar];

		this.keepOpenCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Create blank item after save')
		});

		this.createCopyCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Create Copy after save')
		});

		this.copyPartDataCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Takeover all data'),
			disabled: true
		});

		if (this.partMode === 'create') {
			this.bottomToolbar.add(this.keepOpenCheckbox);
			this.bottomToolbar.add(this.copyPartDataCheckbox);
		} else {
			this.bottomToolbar.add(this.createCopyCheckbox);
		}

		this.keepOpenCheckbox.on('change', this.onKeepOpenCheckboxClick, this);

		this.editor.keepOpenCheckbox = this.keepOpenCheckbox;
		this.editor.copyPartDataCheckbox = this.copyPartDataCheckbox;
		this.editor.createCopyCheckbox = this.createCopyCheckbox;

		this.callParent();
	},
	onCancelEdit: function () {
		this.editor.onCancelEdit();
	},
	onKeepOpenCheckboxClick: function (field, value) {
		if (value) {
			this.copyPartDataCheckbox.enable();
		} else {
			this.copyPartDataCheckbox.disable();
		}
	},
	onOctoPartClick: function () {
		if (Limas.isOctoPartAvailable()) {
			this.editor.getForm().updateRecord();
			this.octoPartQueryWindow = Ext.create('Limas.Components.OctoPart.SearchWindow');
			this.octoPartQueryWindow.show();
			this.octoPartQueryWindow.setPart(this.editor.record);
			let name = this.editor.nameField.getValue();
			if (name.trim() !== '') {
				this.octoPartQueryWindow.startSearch(name);
			}
			this.octoPartQueryWindow.on('refreshData', this.onRefreshData, this);
		} else {
			Ext.MessageBox.alert(i18n('Octopart is not configured'), i18n('Your administrator needs to configure NEXAR_ID+NEXAR_SECRET for Octopart/Nexar in .env.local file'));
		}
	},
	onRefreshData: function () {
		this.editor.getForm().loadRecord(this.editor.record);
		this.octoPartQueryWindow.destroy();
	},
	onItemSave: function () {
		if (!this.editor.getForm().isValid()) {
			return;
		}

		// Disable the save button to indicate progress
		this.saveButton.disable();

		// Sanity: If the save process fails, re-enable the button after 30 seconds
		if (this.saveButtonReenableTask === null) {
			this.saveButtonReenableTask = new Ext.util.DelayedTask(function () {
				this.saveButton.enable();
			}, this);
			this.on('destroy', function () {
				this.saveButtonReenableTask.cancel();
			}, this);
		}
		this.saveButtonReenableTask.delay(30000);

		if (!this.editor._onItemSave()) {
			this.saveButton.enable();
		}
	},
	onItemSaved: function () {
		this.saveButton.enable();
	}
});
