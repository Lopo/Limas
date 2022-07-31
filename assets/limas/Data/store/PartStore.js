Ext.define('Limas.Data.Store.PartStore', {
	extend: 'Limas.Data.Store.BaseStore',

	alias: 'store.PartStore',
	model: 'Limas.Entity.Part',

	autoLoad: true,

	pageSize: 50,
	groupField: 'categoryPath',

	searchFieldSystemPreference: 'limas.part.search.field',
	searchFieldSystemPreferenceDefaults: ['name', 'description', 'comment', 'internalPartNumber'],
	splitSearchTermSystemPreference: 'limas.part.search.split',
	splitSearchTermSystemPreferenceDefaults: true,

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
