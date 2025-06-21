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
		const cleanObject = function(obj) {
			if (obj && typeof obj === 'object') {
				delete obj.root;
				delete obj.lft;
				delete obj.rgt;
				delete obj.lvl;
			}
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
					}
				}
				// Handle arrays
				else if (Array.isArray(data[key])) {
					data[key] = data[key].map(item => {
						if (typeof item === 'object' && item !== null) {
							cleanObject(item);
							if (item['@id']) {
								return item['@id'];
							} else if (typeof item.getId === 'function') {
								return item.getId();
							}
						}
						return item;
					});
				}
			}
		}
		
		return data;
	}
});
