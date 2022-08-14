const Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
	Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

const path = require('path');
const ConcatPlugin = require('@mcler/webpack-concat-plugin');

Encore
	// directory where compiled assets will be stored
	.setOutputPath('public/build/')
	// public path used by the web server to access the output path
	.setPublicPath('/build')
	// only needed for CDN's or sub-directory deploy
	//.setManifestKeyPrefix('build/')
	.addAliases({
		'@': path.resolve(__dirname, 'assets/'),
		'ExtJS': path.resolve(__dirname, 'public/js/packages/extjs')
	})
	.copyFiles([
		{
			from: './assets/images',
			to: 'images/[path][name].[ext]', // optional target path, relative to the output dir
			pattern: /\.(png|jpg|jpeg)$/ // only copy files matching this pattern
		},
		{
			from: './vendor/atelierspierrot/famfamfam-silk-sprite/src',
			to: 'famfamfam-silk-sprite/[path][name].[ext]',
			pattern: /\.(css|png)$/
		}
	])

	// When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
	.splitEntryChunks()

	// will require an extra script tag for runtime.js
	// but, you probably want this, unless you're building a single-page app
	.enableSingleRuntimeChunk()

	/*
	 * FEATURE CONFIG
	 *
	 * Enable & configure other features below. For a full
	 * list of features, see:
	 * https://symfony.com/doc/current/frontend.html#adding-more-features
	 */
	.cleanupOutputBeforeBuild()
	.enableBuildNotifications()
	.enableSourceMaps(!Encore.isProduction())
	// enables hashed filenames (e.g. app.abc123.css)
	.enableVersioning(Encore.isProduction())

	.configureBabel((config) => {
		config.plugins.push('@babel/plugin-proposal-class-properties');
	})

	// enables @babel/preset-env polyfills
	.configureBabelPresetEnv((config) => {
		config.useBuiltIns = 'usage';
		config.corejs = 3;
	})
;

if (Encore.isDev()) {
	Encore
		.copyFiles([
			{
				from: './assets/limas',
				to: 'limas/[path][name].[ext]', // optional target path, relative to the output dir
			}
		]);
} else {
	Encore
		.addPlugin(new ConcatPlugin({
			name: 'extjs',
			outputPath: './limas',
			filesToConcat: [
				'ExtJS/packages/ux/classic/src/TreePicker.js',
				'ExtJS/packages/ux/classic/src/TabCloseMenu.js',
				'ExtJS/packages/ux/classic/src/statusbar/StatusBar.js',
				'ExtJS/packages/ux/classic/src/IFrame.js'
			],
			attributes: {
				async: false,
				defer: false
			},
			injectType: 'none'
		}))
		.addPlugin(new ConcatPlugin({
			name: 'main',
			outputPath: './limas',
			filesToConcat: [
				'ExtJS/build/packages/charts/classic/charts.js',
				'@/limas/Data/CallActions.js',
				'@/limas/Data/field/Array.js',
				'@/limas/Data/field/Decimal.js',
				'@/limas/Data/HydraModel.js',
				'@/limas/Data/HydraField.js',
				'@/limas/Data/HydraTreeModel.js',
				'@/limas/Data/store/ModelStore.js',
				'@/limas/Data/store/BaseStore.js',
				'@/limas/ExtJS/Bugfixes/Ext.form.field.Checkbox.EXTJS-21886.js',
				'@/limas/ExtJS/Enhancements/Ext.view.Table-renderCell.js',
				'@/limas/ExtJS/Enhancements/Ext.data.field.Date-ISO8601.js',
				'@/limas/ExtJS/Bugfixes/Ext.chart.legend.SpriteLegend-EXTJS-27485.js'
			],
			attributes: {
				async: false,
				defer: false
			},
			injectType: 'none'
		}))
		.addPlugin(new ConcatPlugin({
			name: 'main2',
			outputPath: './limas',
			filesToConcat: [
				'@/limas/Ext.ux/TabCloseOnMiddleClick.js',
				'@/limas/ExtJS/Enhancements/Ext.data.Proxy.allowResponseType.js',
				'@/limas/ExtJS/Enhancements/Ext.data.Store.getFieldValue.js',
				'@/limas/Util/i18n.js',
				'@/limas/Data/store/CurrencyStore.js',
				'@/limas/ExtJS/Enhancements/Ext.grid.Column-multipleRendererSupport.js',
				'@/limas/Components/Grid/Renderers/AbstractRenderer.js',
				'@/limas/Components/Grid/Renderers/RendererRegistry.js',
				'@/limas/Components/Grid/Renderers/IconRenderer.js',
				'@/limas/Components/Grid/Renderers/ObjectFieldRenderer.js',
				'@/limas/Components/Part/Renderers/AttachmentRenderer.js',
				'@/limas/Components/Part/Renderers/PartParameterRenderer.js',
				'@/limas/Components/Project/Renderers/ProjectPartParameterRenderer.js',
				'@/limas/Components/Part/Renderers/StockLevelRenderer.js',
				'@/limas/Components/Grid/Renderers/CurrencyRenderer.js',
				'@/limas/Components/Grid/Renderers/InternalIDRenderer.js',
				'@/limas/Data/ReflectionFieldTreeModel.js',
				'@/limas/Components/Widgets/EntityQueryPanel.js',
				'@/limas/Components/Widgets/EntityPicker.js',
				'@/limas/Components/Widgets/PresetComboBox.js',
				'@/limas/Components/Exporter/GridExporter.js',
				'@/limas/Components/Exporter/GridExporterButton.js',
				'@/limas/Components/Importer/GridImporterButton.js',
				'@/limas/Components/Importer/Importer.js',
				'@/limas/Components/Importer/ImporterEntityConfiguration.js',
				'@/limas/Components/Importer/ImporterOneToManyConfiguration.js',
				'@/limas/Components/Importer/ImporterManyToOneConfiguration.js',
				'@/limas/Components/Importer/ImporterFieldConfiguration.js',
				'@/limas/Components/Importer/ImportFieldMatcherGrid.js',
				'@/limas/Ext.ux/StoreMenu.js',
				'@/limas/Components/Grid/GridPresetButton.js',
				'@/limas/Data/store/OperatorStore.js',
				'@/limas/ExtJS/Enhancements/Ext.grid.header.Container-addMoreMenu.js',
				'@/limas/Components/Widgets/ColumnConfigurator/Panel.js',
				'@/limas/Components/Widgets/ColumnConfigurator/Window.js',
				'@/limas/Components/Widgets/ColumnConfigurator/ColumnListGrid.js',
				'@/limas/Components/Widgets/ColumnConfigurator/ColumProperties.js',
				'@/limas/Components/Widgets/ColumnConfigurator/RenderersGrid.js',
				'@/limas/Components/Widgets/ColumnConfigurator/RendererConfigurationForm.js',
				'@/limas/Components/Widgets/FilterExpression.js',
				'@/limas/Components/Widgets/FilterExpressionWindow.js',
				'@/limas/Components/ModelTreeMaker/ModelTreeMaker.js',
				'@/limas/Models/ColumnRendererConfiguration.js',
				'@/limas/Models/ColumnConfiguration.js',
				'@/limas/Util/Blob.js',
				'@/limas/Util/FileSaver.js',
				'@/limas/Components/Widgets/PagingToolbar.js',
				'@/limas/Components/Exporter/Exporter.js',
				'@/limas/Util/Filter.js',
				'@/limas/Components/Auth/LoginManager.js',
				'@/limas/ExtJS/Bugfixes/Ext.grid.feature.Summary-selectorFix.js',
				'@/limas/Components/Widgets/PreferencePlugin.js',
				'@/limas/Components/Auth/AuthenticationProvider.js',
				'@/limas/Components/Auth/HTTPBasicAuthenticationProvider.js',
				'@/limas/Components/Auth/JWTAuthenticationProvider.js',
				'@/limas/Data/store/TipOfTheDayStore.js',
				'@/limas/Data/store/TipOfTheDayHistoryStore.js',
				'@/limas/Data/store/SystemPreferenceStore.js',
				'@/limas/Data/store/UserProvidersStore.js',
				'@/limas/Components/Project/Renderers/MetaPartRenderer.js',
				'@/limas/Components/Project/Renderers/QuantityRenderer.js',
				'@/limas/Components/Project/Renderers/RemarksRenderer.js',
				'@/limas/Models/ProjectReportList.js',
				'@/limas/Models/SystemInformationRecord.js',
				'@/limas/Models/StatisticSample.js',
				'@/limas/ExtJS/Bugfixes/Ext.data.Model-EXTJS-15037.js',
				'@/limas/Util/JsonWithAssociationsWriter.js',
				'@/limas/Limas.js',
				'@/limas/Components/Grid/AppliedFiltersToolbar.js',
				'@/limas/Util/FilterPlugin.js',
				'@/limas/Ext.ux/NumericField.js',
				'@/limas/Components/Widgets/TreePicker.js',
				'@/limas/Components/Widgets/CurrencyNumberField.js',
				'@/limas/form/field/SearchField.js',
				'@/limas/Util/ServiceCall.js',
				'@/limas/locale.js',
				'@/limas/Components/Widgets/FieldSelectorWindow.js',
				'@/limas/ExtJS/Enhancements/Ext.grid.plugin.CellEditing-associationSupport.js',
				'@/limas/ExtJS/Enhancements/Ext.grid.plugin.Editing-associationSupport.js',
				'@/limas/ExtJS/Enhancements/Ext.form.field.ComboBox-associationSupport.js',
				'@/limas/Data/HydraException.js',
				'@/limas/Dialogs/ExceptionWindow.js',
				'@/limas/Dialogs/FileUploadDialog.js',
				'@/limas/Dialogs/RememberChoiceMessageBox.js',
				'@/limas/Data/HydraProxy.js',
				'@/limas/Data/HydraReader.js',
				'@/limas/Data/HydraTreeReader.js',
				'@/limas/Data/store/PartCategoryStore.js',
				'@/limas/Data/store/PartStore.js',
				'@/limas/Data/store/FootprintCategoryStore.js',
				'@/limas/Data/store/StorageLocationCategoryStore.js',
				'@/limas/Data/store/BarcodeScannerActionsStore.js',
				'@/limas/Data/store/UserPreferenceStore.js',
				'@/limas/ExtJS/Enhancements/Ext.tree.View-missingMethods.js',
				'@/limas/ExtJS/Enhancements/Ext.form.Basic-AssociationSupport.js',
				'@/limas/ExtJS/Enhancements/Ext.ux.TreePicker-setValueWithObject.js',
				'@/limas/Components/Widgets/OperatorComboBox.js',
				'@/limas/Actions/BaseAction.js',
				'@/limas/Actions/LogoutAction.js',
				'@/limas/Components/StatusBar.js',
				'@/limas/Components/Auth/LoginController.js',
				'@/limas/Components/Auth/LoginDialog.js',
				'@/limas/Components/Part/PartImageDisplay.js',
				'@/limas/Components/Part/PartInfoGrid.js',
				'@/limas/Components/Part/PartsManager.js',
				'@/limas/Components/Part/Editor/PartEditorWindow.js',
				'@/limas/Components/Part/PartDisplay.js',
				'@/limas/Components/Part/PartStockWindow.js',
				'@/limas/Components/Part/PartFilterPanel.js',
				'@/limas/Components/Part/Editor/MetaPartEditorWindow.js',
				'@/limas/Components/Widgets/PartParameterSearch.js',
				'@/limas/Components/Widgets/PartParameterSearchWindow.js',
				'@/limas/Components/MenuBar.js',
				'@/limas/Components/Grid/BaseGrid.js',
				'@/limas/Components/Part/Editor/PartParameterGrid.js',
				'@/limas/Components/Part/Editor/PartDistributorGrid.js',
				'@/limas/Components/Part/Editor/PartManufacturerGrid.js',
				'@/limas/Components/StockReport/AbstractStockHistoryGrid.js',
				'@/limas/Components/Part/PartStockHistory.js',
				'@/limas/Components/StockReport/StockHistoryGrid.js',
				'@/limas/Components/Widgets/AttachmentGrid.js',
				'@/limas/Components/Part/Editor/PartAttachmentGrid.js',
				'@/limas/Components/Footprint/FootprintAttachmentGrid.js',
				'@/limas/Components/Project/ProjectAttachmentGrid.js',
				'@/limas/Components/Editor/EditorGrid.js',
				'@/limas/Components/Distributor/DistributorGrid.js',
				'@/limas/Components/Part/PartsGrid.js',
				'@/limas/Components/Manufacturer/ManufacturerGrid.js',
				'@/limas/Components/PartMeasurementUnit/PartMeasurementUnitGrid.js',
				'@/limas/Components/Unit/UnitGrid.js',
				'@/limas/Components/User/UserGrid.js',
				'@/limas/Components/SystemNotice/SystemNoticeGrid.js',
				'@/limas/Components/StorageLocation/StorageLocationGrid.js',
				'@/limas/Components/Project/ProjectGrid.js',
				'@/limas/Components/MessageLog.js',
				'@/limas/Components/Project/ProjectPartGrid.js',
				'@/limas/Components/SystemInformation/SystemInformationGrid.js',
				'@/limas/Components/TimeDisplay.js',
				'@/limas/Components/Widgets/UrlTextField.js',
				'@/limas/Components/Widgets/RemotePartComboBox.js',
				'@/limas/Components/Widgets/FadingButton.js',
				'@/limas/Components/Widgets/SystemNoticeButton.js',
				'@/limas/Components/Widgets/ConnectionButton.js',
				'@/limas/Components/Widgets/SiUnitList.js',
				'@/limas/Components/Widgets/SiUnitField.js',
				'@/limas/Components/Widgets/CategoryComboBox.js',
				'@/limas/Components/Widgets/PartParameterComboBox.js',
				'@/limas/Components/Widgets/RemoteImageField.js',
				'@/limas/Components/Widgets/WebcamPanel.js',
				'@/limas/Components/Widgets/ReloadableComboBox.js',
				'@/limas/Components/Widgets/DistributorComboBox.js',
				'@/limas/Components/Widgets/UserComboBox.js',
				'@/limas/Components/Widgets/FootprintComboBox.js',
				'@/limas/Components/Widgets/ManufacturerComboBox.js',
				'@/limas/Components/Widgets/UnitComboBox.js',
				'@/limas/Components/Widgets/PartUnitComboBox.js',
				'@/limas/Components/Widgets/StorageLocationComboBox.js',
				'@/limas/Components/Widgets/SiUnitCombo.js',
				'@/limas/Components/Editor/Editor.js',
				'@/limas/Components/Distributor/DistributorEditor.js',
				'@/limas/Components/Part/Editor/PartEditor.js',
				'@/limas/Components/Manufacturer/ManufacturerEditor.js',
				'@/limas/Components/Part/Editor/PartParameterValueEditor.js',
				'@/limas/Components/PartMeasurementUnit/PartMeasurementUnitEditor.js',
				'@/limas/Components/Unit/UnitEditor.js',
				'@/limas/Components/Footprint/FootprintEditor.js',
				'@/limas/Components/User/UserEditor.js',
				'@/limas/Components/SystemNotice/SystemNoticeEditor.js',
				'@/limas/Components/StorageLocation/StorageLocationEditor.js',
				'@/limas/Components/Part/Editor/MetaPartEditor.js',
				'@/limas/Components/Project/ProjectEditor.js',
				'@/limas/Components/Editor/EditorComponent.js',
				'@/limas/Components/Distributor/DistributorEditorComponent.js',
				'@/limas/Components/Manufacturer/ManufacturerEditorComponent.js',
				'@/limas/Components/PartMeasurementUnit/PartMeasurementUnitEditorComponent.js',
				'@/limas/Components/Unit/UnitEditorComponent.js',
				'@/limas/Components/Footprint/FootprintEditorComponent.js',
				'@/limas/Components/Footprint/FootprintNavigation.js',
				'@/limas/Components/Footprint/FootprintGrid.js',
				'@/limas/Components/BatchJob/BatchJobEditor.js',
				'@/limas/Components/BatchJob/BatchJobEditorComponent.js',
				'@/limas/Components/BatchJob/BatchJobGrid.js',
				'@/limas/Components/BatchJob/BatchJobUpdateExpression.js',
				'@/limas/Components/BatchJob/BatchJobUpdateExpressionWindow.js',
				'@/limas/Components/BatchJob/BatchJobExecutionWindow.js',
				'@/limas/Components/User/UserEditorComponent.js',
				'@/limas/Components/SystemNotice/SystemNoticeEditorComponent.js',
				'@/limas/Components/StorageLocation/StorageLocationEditorComponent.js',
				'@/limas/Components/Project/ProjectEditorComponent.js',
				'@/limas/Components/StorageLocation/StorageLocationMultiCreateWindow.js',
				'@/limas/Components/StorageLocation/StorageLocationMultiAddDialog.js',
				'@/limas/Components/StorageLocation/StorageLocationNavigation.js',
				'@/limas/Components/Project/MetaPartSubgrid.js',
				'@/limas/Components/Project/MetaPartRowExpander.js',
				'@/limas/Components/Project/ProjectReportList.js',
				'@/limas/Components/Project/ProjectReport.js',
				'@/limas/Components/Project/ProjectReportResultGrid.js',
				'@/limas/Components/Project/ProjectReportGrid.js',
				'@/limas/Components/Project/Renderers/MetaPartAvailabilityRenderer.js',
				'@/limas/Components/Statistics/StatisticsChart.js',
				'@/limas/Components/Statistics/StatisticsChartPanel.js',
				'@/limas/Components/Statistics/SummaryStatisticsPanel.js',
				'@/limas/Data/store/SystemNoticeStore.js',
				'@/limas/Components/TipOfTheDay/TipOfTheDayWindow.js',
				'@/limas/Components/CategoryTree.js',
				'@/limas/Components/CategoryEditor/CategoryEditorTree.js',
				'@/limas/Components/StorageLocation/StorageLocationTree.js',
				'@/limas/Components/Part/PartCategoryTree.js',
				'@/limas/Components/Footprint/FootprintTree.js',
				'@/limas/Components/CategoryEditor/CategoryEditorWindow.js',
				'@/limas/Components/CategoryEditor/CategoryEditorForm.js',
				'@/limas/Components/Widgets/StorageLocationPicker.js',
				'@/limas/Components/Preferences/Panel.js',
				'@/limas/Components/SystemPreferences/Panel.js',
				'@/limas/Components/UserPreferences/Panel.js',
				'@/limas/Components/Preferences/Tree.js',
				'@/limas/Components/Preferences/PreferenceEditor.js',
				'@/limas/Components/SystemPreferences/Preferences/FulltextSearch.js',
				'@/limas/Components/SystemPreferences/Preferences/RequiredPartFields.js',
				'@/limas/Components/SystemPreferences/Preferences/RequiredPartManufacturerFields.js',
				'@/limas/Components/SystemPreferences/Preferences/RequiredPartDistributorFields.js',
				'@/limas/Components/SystemPreferences/Preferences/BarcodeScannerConfiguration.js',
				'@/limas/Components/SystemPreferences/Preferences/ActionsConfiguration.js',
				'@/limas/Components/UserPreferences/Preferences/TipOfTheDayConfiguration.js',
				'@/limas/Components/UserPreferences/Preferences/FormattingConfiguration.js',
				'@/limas/Components/UserPreferences/Preferences/DisplayConfiguration.js',
				'@/limas/Components/UserPreferences/Preferences/StockConfiguration.js',
				'@/limas/Components/UserPreferences/Preferences/PasswordConfiguration.js',
				'@/limas/Components/UserPreferences/Preferences/OctoPartConfiguration.js',
				'@/limas/Components/ProjectRun/ProjectRunEditor.js',
				'@/limas/Components/ProjectRun/ProjectRunGrid.js',
				'@/limas/Components/ProjectRun/ProjectRunEditorComponent.js',
				'@/limas/Components/BarcodeScanner/Manager.js',
				'@/limas/Components/BarcodeScanner/Action.js',
				'@/limas/Components/BarcodeScanner/ActionsComboBox.js',
				'@/limas/Components/BarcodeScanner/Actions/AddRemoveStock.js',
				'@/limas/Components/Part/AddRemoveStockWindow.js',
				'@/limas/Components/BarcodeScanner/Actions/AddPart.js',
				'@/limas/Components/BarcodeScanner/Actions/SearchPart.js',
				'@/limas/Components/Widgets/FieldSelector.js',
				'@/limas/Models/Message.js',
				'@/limas/Components/OctoPart/SearchPanel.js',
				'@/limas/Components/OctoPart/SearchWindow.js',
				'@/limas/Components/OctoPart/DataApplicator.js',
				'@/limas/Components/PatreonStatusDialog.js',
				'@/limas/Components/ThemeTester/ThemeTester.js',
				'@/limas/phpjs.js'
			],
			attributes: {
				async: false,
				defer: false
			},
			injectType: 'none'
		}));
}

module.exports = Encore.getWebpackConfig();
