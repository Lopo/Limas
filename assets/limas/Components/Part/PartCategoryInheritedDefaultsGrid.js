/**
 * Read-only summary of parameter templates inherited from ancestor categories.
 * Rendered above the editable own-templates grid so admins can see what would
 * be duplicated or overridden by adding the same name here.
 *
 * Backed by GET /api/part_categories/{id}/inherited_defaults — walks the
 * ancestor chain (excluding the category itself) and includes an `origin`
 * field with the source category's name.
 */
Ext.define('Limas.Components.Part.PartCategoryInheritedDefaultsGrid', {
	extend: 'Ext.grid.Panel',
	alias: 'widget.PartCategoryInheritedDefaultsGrid',

	border: false,
	title: i18n('Inherited from parent categories'),
	bodyStyle: 'background-color: var(--limas-bg-muted, #fafafa);',
	hidden: true, // shown only when ancestor templates exist

	initComponent: function () {
		this.store = Ext.create('Ext.data.Store', {
			fields: ['name', 'description', 'valueType', 'unit', 'origin'],
			autoLoad: false
		});

		this.columns = [
			{header: i18n('Name'), dataIndex: 'name', flex: 2},
			{header: i18n('Description'), dataIndex: 'description', flex: 3},
			{header: i18n('Value type'), dataIndex: 'valueType', width: 100},
			{
				header: i18n('Unit'), dataIndex: 'unit', width: 110,
				renderer: function (v) {
					if (v && typeof v === 'object' && v.name) {
						return Ext.htmlEncode(v.name);
					}
					return '';
				}
			},
			{
				header: i18n('From'), dataIndex: 'origin', flex: 1,
				renderer: function (v) {
					return '<span class="limas-text-muted">' + Ext.htmlEncode(v || '') + '</span>';
				}
			}
		];

		this.callParent();
	},

	bindCategoryRecord: function (categoryRecord) {
		if (!categoryRecord || categoryRecord.phantom) {
			this.store.removeAll();
			this.setHidden(true);
			return;
		}
		let iri = categoryRecord.get('@id');
		Ext.Ajax.request({
			url: Limas.getBasePath() + iri + '/inherited_defaults',
			method: 'GET',
			success: Ext.bind(function (response) {
				let data;
				try {
					data = Ext.decode(response.responseText);
				} catch (e) {
					return;
				}
				if (!Ext.isArray(data)) {
					return;
				}
				this.store.loadData(data);
				this.setHidden(data.length === 0);
			}, this),
			failure: Ext.bind(function () {
				this.store.removeAll();
				this.setHidden(true);
			}, this)
		});
	}
});
