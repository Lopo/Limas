const {createCategoryTests} = require('./category-test-factory');

createCategoryTests({
	name: 'StorageLocation',
	treeAlias: 'StorageLocationTree',
	editorComponent: 'Limas.StorageLocationEditorComponent'
});
