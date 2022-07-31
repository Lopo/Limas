Ext.define('Limas.Components.Part.Renderers.AttachmentRenderer', {
	extend: 'Limas.Components.Grid.Renderers.AbstractRenderer',

	alias: 'columnRenderer.partAttachment',

	renderer: function (val, q, rec) {
		let ret = '';
		if (rec.attachments().getCount() > 0) {
			ret += '<span class="web-icon fugue-icon paper-clip" title="' + i18n('Has attachments') + '"/>';
		}
		return ret;
	},

	statics: {
		rendererName: i18n('Attachment Renderer'),
		rendererDescription: i18n('Renders an attachment icon if one or more attachments exist'),
		restrictToEntity: ['Limas.Entity.Part']
	}
});
