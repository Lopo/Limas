Ext.define('Limas.Data.store.SystemNoticeStore', {
	extend: 'Ext.data.Store',
	storeId: 'SystemNoticeStore',
	autoLoad: true,
	model: 'Limas.Entity.SystemNotice',

	pageSize: 99999999,

	sorters: [
		{
			property: 'date',
			direction: 'DESC'
		}
	],

	filters: [
		{
			property: 'acknowledged',
			operator: '=',
			value: false
		}
	]
});
