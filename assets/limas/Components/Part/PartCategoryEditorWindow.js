/**
 * PartCategory-specific editor window. Extends the generic CategoryEditorWindow
 * with a second tab carrying the per-category default parameter templates.
 * Templates pre-populate new Parts created in this (or any child) category.
 *
 * PartKeepr issues #777 / #366 / #54
 */
Ext.define('Limas.PartCategoryEditorWindow', {
	extend: 'Ext.window.Window',
	alias: 'widget.PartCategoryEditorWindow',

	border: false,
	width: 700,
	height: 520,
	categoryModel: null,
	layout: 'fit',

	initComponent: function () {
		this.formPanel = Ext.create('Limas.CategoryEditorForm', {
			title: i18n('General')
		});

		this.inheritedGrid = Ext.create('Limas.Components.Part.PartCategoryInheritedDefaultsGrid');
		this.defaultsGrid = Ext.create('Limas.Components.Part.PartCategoryDefaultsGrid', {
			flex: 1,
			title: i18n('Own templates')
		});

		this.defaultsTab = Ext.create('Ext.panel.Panel', {
			title: i18n('Default Parameters'),
			layout: {type: 'vbox', align: 'stretch'},
			disabled: true,
			items: [this.inheritedGrid, this.defaultsGrid]
		});

		this.tabPanel = Ext.create('Ext.tab.Panel', {
			items: [this.formPanel, this.defaultsTab]
		});

		this.items = [this.tabPanel];

		this.buttons = [
			{text: i18n('Save'), handler: Ext.bind(this.onSave, this)},
			{text: i18n('Cancel'), handler: Ext.bind(this.onCancel, this)}
		];

		this.callParent();

		if (!this.record.phantom) {
			this.setTitle(i18n('Edit Category'));
			this.defaultsTab.setDisabled(false);
		} else {
			this.record.set('parent', this.parentRecord.getId());
			this.setTitle(i18n('Add Category'));
			// Defaults can be added after first save (need category id)
		}

		this.formPanel.loadRecord(this.record);

		if (!this.record.phantom) {
			this.defaultsGrid.bindCategoryRecord(this.record);
			this.inheritedGrid.bindCategoryRecord(this.record);
		}

		this.formPanel.down('textfield[name=name]').on('keypress', this.onEnter, this);
		this.formPanel.down('htmleditor[name=description]').on('keypress', this.onEnter, this);

		this.on('show', Ext.bind(this._onShow, this));
	},

	onEnter: function (field, e) {
		if (e.getKey() === e.ENTER && this.tabPanel.getActiveTab() === this.formPanel) {
			this.onSave();
		}
	},

	_onShow: function () {
		this.formPanel.items.first().focus();
	},

	onSave: function () {
		this.formPanel.updateRecord(this.record);

		let wasPhantom = this.record.phantom;
		this.record.save({
			success: Ext.bind(function (response) {
				if (wasPhantom) {
					// First save of a new category: switch the window into
					// edit-mode (defaults tab becomes editable, title flips)
					// and keep it open so the user can add parameters right
					// away — closing via the X button is the cancel path
					this.setTitle(i18n('Edit Category'));
					this.defaultsTab.setDisabled(false);
					this.defaultsGrid.bindCategoryRecord(this.record);
					this.inheritedGrid.bindCategoryRecord(this.record);
					this.tabPanel.setActiveTab(this.defaultsTab);
					this.fireEvent('save', response);
					return;
				}
				this.defaultsGrid.persistChanges(Ext.bind(function () {
					this.fireEvent('save', response);
					this.destroy();
				}, this));
			}, this)
		});
	},

	onCancel: function () {
		this.destroy();
	}
});
