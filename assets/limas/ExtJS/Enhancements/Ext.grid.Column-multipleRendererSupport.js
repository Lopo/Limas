/**
 * Enhances grid columns to support multiple renderers
 */
Ext.define('Limas.ExtJS.Enhancements.Grid.MultipleRendererSupport', {
	override: 'Ext.grid.Column',

	rendererInstances: [],

	initComponent: function () {
		let rendererDef, rendererClass;

		if (this.$className !== 'Ext.grid.column.Column') {
			return this.callParent(arguments);
		}

		this.originalRenderer = this.renderer;
		this.originalScope = this.scope;
		this.scope = this;
		this.renderer = this.doRender;

		this.callParent(arguments);

		if (!this.renderers) {
			this.renderers = [];
		}

		if (!(this.renderers instanceof Array)) {
			this.renderers = [this.renderers];
		}

		this.rendererInstances = [];

		for (let i = 0; i < this.renderers.length; i++) {
			rendererDef = this.renderers[i];

			if (typeof (rendererDef) === 'string') {
				rendererClass = Limas.Components.Grid.Renderers.RendererRegistry.lookupRenderer(rendererDef);

				this.rendererInstances.push(Ext.create(rendererClass));
				continue;
			}

			if (typeof (rendererDef) === 'object' && rendererDef.rtype) {
				rendererClass = Limas.Components.Grid.Renderers.RendererRegistry.lookupRenderer(rendererDef.rtype);
				this.rendererInstances.push(Ext.create(rendererClass, rendererDef));
				continue;
			}

			if (rendererDef instanceof Limas.Components.Grid.Renderers.AbstractRenderer) {
				Ext.raise('Passing a renderer instance is prohibited!');
				continue;
			}

			Ext.raise('No valid renderers definition found for entry:');
			Ext.raise(rendererDef);
		}
	},
	doRender: function (value, metaData, record, rowIndex, colIndex, store, view) {
		value = Ext.util.Format.htmlEncode(value);
		for (let i = 0; i < this.rendererInstances.length; i++) {
			value = this.rendererInstances[i].renderer.call(this.originalScope, value, metaData, record, rowIndex, colIndex, store, view, this.rendererInstances[i]);
		}

		if (typeof (this.originalRenderer) === 'function') {
			return this.originalRenderer.call(this.originalScope, value, metaData, record, rowIndex, colIndex, store, view);
		}
		return value;
	}
});
