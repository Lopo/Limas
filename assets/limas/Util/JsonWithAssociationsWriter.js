Ext.define('Limas.JsonWithAssociations', {
	extend: 'Ext.data.writer.Json',
	alias: 'writer.jsonwithassociations',

	/**
	 * @cfg {Array} associations Which associations to include
	 */
	associations: [],
	writeRecordId: false,

	getRecordData: function (record, operation) {
		let data = this.callParent(arguments);
		Ext.apply(data, record.getAssociatedData(null, {serialize: true}));

		// Add JSON-LD fields for Hydra compatibility
		// Add @id field for updates (PUT requests)
		if (operation && operation.action === 'update' && record.getId()) {
			data['@id'] = record.getId();
		}

		// Add @type field if available
		if (record.entityName) {
			data['@type'] = record.entityName;
		} else if (record.$className) {
			// Fallback - derive from class name
			// Limas.Entity.Part -> Part
			let parts = record.$className.split('.');
			if (parts.length > 0) {
				data['@type'] = parts[parts.length - 1];
			}
		}

		// Remove problematic fields from objects
		const cleanObject = function (obj) {
			if (obj && typeof obj === 'object') {
				delete obj.root;
				delete obj.lft;
				delete obj.rgt;
				delete obj.lvl;
			}
		};

		// Recursively convert nested objects with @id to IRI strings
		const convertNestedToIri = function (obj) {
			if (obj === null || typeof obj !== 'object') {
				return obj;
			}
			if (Array.isArray(obj)) {
				return obj.map(item => convertNestedToIri(item));
			}
			// Process each property
			let result = {};
			for (let prop in obj) {
				if (!obj.hasOwnProperty(prop)) {
					continue;
				}
				let value = obj[prop];
				if (value === null || typeof value !== 'object') {
					result[prop] = value;
				} else if (Array.isArray(value)) {
					result[prop] = value.map(item => convertNestedToIri(item));
				} else {
					// It's an object
					cleanObject(value);
					if (value['@id']) {
						// Convert to IRI string
						result[prop] = value['@id'];
					} else if (typeof value.getId === 'function') {
						result[prop] = value.getId();
					} else {
						// Recursively process nested object
						result[prop] = convertNestedToIri(value);
					}
				}
			}
			return result;
		};

		// For associations with @id, send only the IRI reference
		for (let key in data) {
			if (data.hasOwnProperty(key) && data[key]) {
				if (typeof data[key] === 'object' && data[key] !== null && !Array.isArray(data[key])) {
					// Clean the object first
					cleanObject(data[key]);

					// If object has @id, send only that (IRI reference)
					if (data[key]['@id']) {
						data[key] = data[key]['@id'];
					}
					// If it's an object with getId method
					else if (typeof data[key].getId === 'function') {
						data[key] = data[key].getId();
					} else {
						// Recursively convert nested objects
						data[key] = convertNestedToIri(data[key]);
					}
				}
				// Handle arrays (hasMany associations — parameters, distributors,
				// manufacturers, …). These are owned child collections the user
				// edits in place, so every row must serialize its FULL field data.
				// convertNestedToIri keeps the row's @id, preserves scalar fields
				// and turns nested single-valued associations (e.g. a parameter's
				// unit/siPrefix) into IRI strings. Previously existing rows (with
				// an @id) collapsed to a bare {'@id': …}, which silently dropped
				// every edited field — so inline edits to a parameter's unit, a
				// distributor's price, etc. never persisted through the Part PUT.
				else if (Array.isArray(data[key])) {
					data[key] = data[key].map(item => {
						if (typeof item === 'object' && item !== null) {
							cleanObject(item);
							return convertNestedToIri(item);
						}
						return item;
					});
				}
			}
		}

		return data;
	}
});
