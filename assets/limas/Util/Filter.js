Ext.define('Limas.util.Filter', {
	extend: 'Ext.util.Filter',

	config: {
		/**
		 * @cfg {String} [property=null]
		 * The property to filter on. Required unless a {@link #filterFn} is passed.
		 */
		subfilters: [],
	},
	/**
	 * @param {Object} config Config object
	 */
	constructor: function (config) {
		this.operatorFns['notin'] = function (candidate) {
			let v = this._filterValue;
			return !Ext.Array.contains(v, this.getCandidateValue(candidate, v));
		};
		//<debug>
		let warn = Limas.util.Filter.isInvalid(config);
		if (warn) {
			Ext.log.warn(warn);
		}
		//</debug>
		this.initConfig(config);
	},
	propertyLabels: {
		'storageLocation': i18n('Storage Location'),
		'category': i18n('Category'),
		'stockLevel': i18n('Stock Level'),
		'lowStock': i18n('Low Stock'),
		'averagePrice': i18n('Price'),
		'distributors.orderNumber': i18n('Order Number'),
		'distributors.distributor': i18n('Distributor'),
		'manufacturers.manufacturer': i18n('Manufacturer'),
		'manufacturers.partNumber': i18n('Manufacturer Part Number'),
		'footprint': i18n('Footprint'),
		'createDate': i18n('Create Date'),
		'removals': i18n('Stock Removals'),
		'needsReview': i18n('Needs Review'),
		'status': i18n('Status'),
		'partCondition': i18n('Condition'),
		'internalPartNumber': i18n('Internal Part Number'),
		'comment': i18n('Comment'),
		'name': i18n('Name'),
		'id': i18n('ID'),
		'parameters.name': i18n('Parameter'),
		'parameters.normalizedValue': i18n('Value'),
		'parameters.stringValue': i18n('Value')
	},

	getFilterDescription: function () {
		let config = this.getInitialConfig();

		// Subfilter group (parameter filters)
		if (config.subfilters instanceof Array && config.subfilters.length > 0) {
			// For parameter filters, build a readable description like "Diameter <= 0.008"
			let paramName = null, operator = null, value = null;
			for (let i = 0; i < config.subfilters.length; i++) {
				let sf = config.subfilters[i],
					sfConfig = sf.getInitialConfig ? sf.getInitialConfig() : sf;
				if (sfConfig.property === 'parameters.name' && sfConfig.operator === '=') {
					paramName = sfConfig.value;
				} else if (sfConfig.property === 'parameters.normalizedValue' || sfConfig.property === 'parameters.stringValue') {
					operator = sfConfig.operator;
					value = sfConfig.value;
				}
			}

			if (paramName !== null) {
				return paramName + ' ' + (operator || '') + ' ' + (value !== null ? value : '');
			}

			// Search filter - all subfilters are LIKE with the same term
			let searchTerms = [], isSearchFilter = true;
			for (let i = 0; i < config.subfilters.length; i++) {
				let sf = config.subfilters[i],
					sfConfig = sf.getInitialConfig ? sf.getInitialConfig() : sf;
				if (sfConfig.operator && sfConfig.operator.toLowerCase() === 'like') {
					let term = (sfConfig.value || '').replace(/^%|%$/g, '');
					if (searchTerms.indexOf(term) === -1) {
						searchTerms.push(term);
					}
				} else if (sfConfig.subfilters) {
					// Nested OR groups (split terms) - extract terms from inner subfilters
					let innerConfig = sfConfig.subfilters || (sf.getSubfilters ? sf.getSubfilters() : []);
					for (let k = 0; k < innerConfig.length; k++) {
						let innerSf = innerConfig[k],
							innerSfConfig = innerSf.getInitialConfig ? innerSf.getInitialConfig() : innerSf;
						if (innerSfConfig.operator && innerSfConfig.operator.toLowerCase() === 'like') {
							let term = (innerSfConfig.value || '').replace(/^%|%$/g, '');
							if (searchTerms.indexOf(term) === -1) {
								searchTerms.push(term);
							}
						} else {
							isSearchFilter = false;
						}
					}
				} else {
					isSearchFilter = false;
				}
			}

			if (isSearchFilter && searchTerms.length > 0) {
				return i18n('Search') + ': ' + searchTerms.join(' ');
			}

			// Generic subfilter fallback
			let parts = [];
			for (let i = 0; i < config.subfilters.length; i++) {
				parts.push(config.subfilters[i].getFilterDescription());
			}
			return parts.join((config.type && config.type.toLowerCase() === 'or') ? ' OR ' : ' AND ');
		}

		// Simple filter
		if (config.property !== null && config.operator !== null) {
			let label = this.propertyLabels[config.property] || config.property,
				displayValue = config.value;

			if (displayValue === true) {
				return label;
			}
			if (displayValue === false) {
				return label + ': ' + i18n('No');
			}

			// Category IN - value is array of tree nodes
			if (config.property === 'category' && Array.isArray(displayValue)) {
				if (displayValue.length > 0 && displayValue[0].get) {
					return label + ': ' + displayValue[0].get('name');
				}
				return label;
			}

			// IRI values - extract readable name from store
			if (typeof displayValue === 'string' && displayValue.indexOf('/api/') === 0) {
				let storeMappings = {
					'category': 'PartCategoryStore',
					'storageLocation': 'StorageLocationStore',
					'footprint': 'FootprintStore',
					'distributors.distributor': 'DistributorStore',
					'manufacturers.manufacturer': 'ManufacturerStore'
				};
				let storeName = storeMappings[config.property];
				if (storeName) {
					let store = Ext.data.StoreManager.lookup(storeName);
					if (store) {
						let rec = store.findRecord('@id', displayValue, 0, false, true, true);
						if (rec) {
							displayValue = rec.get('name');
						}
					}
				}
			}

			// LIKE operator - strip % wildcards for display
			if (config.operator === 'LIKE' && typeof displayValue === 'string') {
				return label + ' ~ ' + displayValue.replace(/^%|%$/g, '');
			}

			return label + ' ' + config.operator + ' ' + displayValue;
		}

		return '';
	},

	preventConvert: {
		'in': 1,
		'notin': 1
	},
	/**
	 * Returns this filter's state
	 * @return {Object}
	 */
	getState: function () {
		let config = this.getInitialConfig(),
			result = {};

		for (let name in config) {
			// We only want the instance properties in this case, not inherited ones,
			// so we need hasOwnProperty to filter out our class values.
			if (name === 'subfilters') {
				if (config[name] instanceof Array) {
					let tempConfigs = [];

					for (let i = 0; i < config[name].length; i++) {
						tempConfigs.push(config[name][i].getState());
					}

					result[name] = tempConfigs;
				}
			} else if (config.hasOwnProperty(name)) {
				result[name] = config[name];
			}
		}

		delete result.root;

		if (config['subfilters'] instanceof Array) {
			// Do nothing for now
		} else {
			result.value = this.getValue();
		}
		return result;
	},
	inheritableStatics: {
		/**
		 * Checks whether the filter will produce a meaningful value. Since filters may be used in conjunction with
		 * data binding, this is a sanity check to check whether the resulting filter will be able to match.
		 *
		 * @param {Object} cfg The filter config object
		 * @return {Boolean} `true` if the filter will produce a valid value
		 *
		 * @private
		 */
		isInvalid: function (cfg) {
			return false;
			if (!cfg.filterFn) {
				// If we don't have a filterFn, we must have a property
				if (!cfg.property) {
					return 'A Filter requires either a property or a filterFn to be set';
				}

				if (!cfg.hasOwnProperty('value') && !cfg.operator) {
					return 'A Filter requires either a property and value, or a filterFn to be set';
				}
			}
			return false;
		}
	}
});
