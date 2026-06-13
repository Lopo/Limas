const {createCategoryTests} = require('./category-test-factory');

createCategoryTests({
	name: 'Part',
	treeAlias: 'PartCategoryTree',
	editorComponent: null // Part Manager is default view
});
