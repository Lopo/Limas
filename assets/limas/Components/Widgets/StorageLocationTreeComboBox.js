/**
 * Storage Location picker that combines the two-level
 * StorageLocationCategory + StorageLocation hierarchy into a single
 * tree dropdown. Folders are categories, leaves are locations.
 *
 * Why this exists: a flat StorageLocation combo loses the category
 * context entirely (operator can't disambiguate "Box A" in Workshop vs
 * "Box A" in Lab). The two-pane StorageLocationPicker shows both but
 * looks like an embedded subpage when squeezed into a form field. This
 * widget gives a compact tree dropdown where the path is implicit and
 * clicking a leaf locks in the choice.
 *
 * Implementation: piggyback on the existing StorageLocationCategoryStore
 * (TreeStore the whole storage editor uses), then on the first store
 * load also fetch /api/storage_locations and inject each location as a
 * leaf child of its owning category node.
 */
Ext.define('Limas.Widgets.StorageLocationTreeComboBox', {
	extend: 'Limas.Widgets.TreePicker',
	alias: 'widget.StorageLocationTreeComboBox',

	displayField: 'name',
	valueField: '@id',
	// No type-ahead: tree pickers don't pair well with editable inputs
	// because the typed string doesn't map to any one branch. Click-to-pick.
	editable: false,

	initComponent: function () {
		let me = this;
		me.store = Ext.create('Limas.Data.store.StorageLocationCategoryStore');

		// Inject location leaves after the category tree lands. Single
		// listener invocation per picker lifetime — we don't expect the
		// tree to reload on its own (user would close + reopen the
		// owning window for a refresh).
		me.store.on('load', me.loadLocations, me, {single: true});

		me.callParent();
	},

	// Pulls the full StorageLocation list in one request — the same
	// pageSize bypass that StorageLocationComboBox uses. Anything in the
	// thousands and we'd need a smarter approach, but stockrooms with
	// 10k+ bins are not the target user.
	loadLocations: function () {
		let me = this;
		Ext.Ajax.request({
			url: Limas.getBasePath() + '/api/storage_locations?itemsPerPage=99999',
			headers: Limas.Auth.AuthenticationProvider.getAuthenticationProvider().getHeaders(),
			success: function (response) {
				let data = Ext.decode(response.responseText);
				me.injectLocations(data['hydra:member'] || []);
			},
			failure: function () {
				// Silent: the operator will see an empty / folders-only
				// tree and can complain. Not worth a modal toast here.
			}
		});
	},

	injectLocations: function (locations) {
		let me = this;
		// Bucket by category IRI. The serializer can emit `category` as
		// either a string IRI or an expanded `{@id: ...}` object
		// depending on serialization groups — handle both shapes.
		let byCategory = {};
		locations.forEach(function (loc) {
			let catRef = loc.category;
			let catIri = typeof catRef === 'string' ? catRef : (catRef && catRef['@id']);
			if (!catIri) return;
			(byCategory[catIri] = byCategory[catIri] || []).push(loc);
		});

		let root = me.store.getRoot();
		root.cascade(function (node) {
			let bucket = byCategory[node.get('@id')];
			if (!bucket || bucket.length === 0) return;
			bucket.forEach(function (loc) {
				node.appendChild({
					'@id': loc['@id'],
					name: loc.name,
					leaf: true,
					iconCls: 'fugue-icon box',
					__storageLocation: true
				});
			});
		});
	},

	// Override the parent's itemclick handler: commit only when the
	// clicked record is a location leaf. Category folders should just
	// expand / collapse like a regular tree.
	onItemClick: function (view, record) {
		if (record.get('leaf') && record.get('__storageLocation')) {
			this.selectItem(record);
		}
	}
});
