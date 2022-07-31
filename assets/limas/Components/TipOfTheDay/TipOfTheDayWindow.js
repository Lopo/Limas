Ext.define('Limas.TipOfTheDayWindow', {
	extend: 'Ext.window.Window',

	titleTemplate: i18n('Tip of the Day'),

	/* Cosmetic settings */
	width: 600,
	height: 300,
	minWidth: 600,
	minHeight: 300,

	layout: 'fit',

	/**
	 * Stores the currently displayed tip, or null if none is displayed
	 * @var Ext.data.Record
	 */
	currentTip: null,
	/**
	 * Holds an instance of the TipOfTheDay store
	 */
	tipStore: null,
	/**
	 * Holds an instance of the TipOfTheDayHistory store
	 */
	tipHistoryStore: null,

	/**
	 * Initializes the window. Adds the iframe used for displaying tips, as well
	 * as the user controls (prev/next buttons, config checkboxes).
	 */
	initComponent: function () {
		this.title = this.titleTemplate;

		this.tipStore = Ext.data.StoreManager.lookup('TipOfTheDayStore');
		this.tipHistoryStore = Ext.data.StoreManager.lookup('TipOfTheDayHistoryStore');

		this.tipDisplay = Ext.create('Ext.ux.IFrame', {
			border: false
		});
		this.items = this.tipDisplay;

		this.previousButton = Ext.create('Ext.button.Button', {
			text: i18n('Previous Tip'),
			handler: Ext.bind(this.displayPreviousTip, this),
			iconCls: 'partkeepr-icon tip_previous',
			disabled: true
		});

		this.nextButton = Ext.create('Ext.button.Button', {
			text: i18n('Next Tip'),
			iconCls: 'partkeepr-icon tip_next',
			handler: Ext.bind(this.displayNextTip, this)
		});

		this.showTipsCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Show Tips on login'),
			handler: Ext.bind(this.showTipsHandler, this)
		});

		this.displayReadTipsCheckbox = Ext.create('Ext.form.field.Checkbox', {
			boxLabel: i18n('Show read tips'),
			handler: Ext.bind(this.showReadTipsHandler, this)
		});

		this.showTipsCheckbox.setValue(Limas.getApplication().getUserPreference('limas.tipoftheday.showtips') !== false);

		// Append the controls to the bottom toolbar
		this.dockedItems = [
			{
				xtype: 'toolbar',
				dock: 'bottom',
				ui: 'footer',
				defaults: {minWidth: 100},
				pack: 'start',
				items: [
					this.previousButton,
					this.nextButton,
					'->',
					this.showTipsCheckbox,
					this.displayReadTipsCheckbox
				]
			}
		];

		// Auto-load the next unread tip on window display
		this.updateFilter();
		this.currentTip = this.tipStore.getAt(0);
		this.on('show', this.displayTip, this);

		// Window destroy handler
		this.on('destroy', this.onDestroy, this);
		this.callParent();
	},
	displayPreviousTip: function () {
		let idx = this.tipStore.indexOf(this.currentTip);
		this.currentTip = this.tipStore.getAt(idx - 1);
		if (this.currentTip === null) {
			this.currentTip = this.tipStore.getAt(0);
		}
		this.displayTip(this.currentTip);
	},
	displayNextTip: function () {
		let idx = this.tipStore.indexOf(this.currentTip);
		this.currentTip = this.tipStore.getAt(idx + 1);
		if (this.currentTip === null) {
			this.currentTip = this.tipStore.getAt(0);
		}
		this.displayTip(this.currentTip);
	},
	/**
	 * Updates the filter for the tip store to exclude read tips
	 */
	updateFilter: function () {
		this.tipStore.clearFilter();

		if (this.displayReadTipsCheckbox.getValue() === true) {
			return;
		}

		let filterItems = [];
		this.tipHistoryStore.each(function (record) {
			filterItems.push(record.get('name'));
		});

		let tipFilter = Ext.create('Limas.util.Filter', {
			property: 'name',
			operator: 'notin',
			value: filterItems
		});

		this.tipStore.addFilter(tipFilter);
	},
	/**
	 * If the "show read tips" checkbox was clicked, update the buttons to reflect the tip navigation
	 */
	showReadTipsHandler: function () {
		this.updateFilter();
		this.updateButtons(this.currentTip);
	},
	/**
	 * Destroy handler. Removes the "read tip" timer.
	 */
	onDestroy: function () {
		this.cancelReadTimer();
	},
	cancelReadTimer: function () {
		if (this.markAsReadTask) {
			this.markAsReadTask.cancel();
		}
	},
	/**
	 * Handler when the "show tips" checkbox was clicked
	 */
	showTipsHandler: function (checkbox, checked) {
		Limas.getApplication().setUserPreference('limas.tipoftheday.showtips', checked);
	},
	/**
	 * Displays a specific tip of the day
	 * @param record The record which contains the information regarding the tip
	 */
	displayTip: function () {
		if (!this.currentTip) {
			return;
		}

		// Cancel the old read timer
		this.cancelReadTimer();

		// Update buttons to reflect position
		this.updateButtons(this.currentTip);

		// Set the title to the tip name
		this.setTitle(this.titleTemplate + ': ' + this.currentTip.get('name'));

		// Set iframe to the tip url
		this.tipDisplay.load(sprintf(Limas.getApplication().getParameter('tip_of_the_day_uri'), this.currentTip.get('name')));

		// Fire up delayed task to mark the tip as read
		if (this.markAsReadTask) {
			this.markAsReadTask.cancel();
		}

		this.markAsReadTask = new Ext.util.DelayedTask(this.markTipRead, this);
		this.markAsReadTask.delay(5000);
	},
	/**
	 * Updates the navigation buttons
	 *
	 * This method has two modes, depending on which state the "show read tips" checkbox is in
	 * @param record The currently displayed tip
	 */
	updateButtons: function (record) {
		if (this.tipStore.indexOf(record) > 0) {
			this.previousButton.enable();
		} else {
			this.previousButton.disable();
		}

		if (this.tipStore.indexOf(record) === this.tipStore.getCount() - 1) {
			this.nextButton.disable();
		} else {
			this.nextButton.enable();
		}
	},
	/**
	 * Marks the current tip as read. Commits the information to the server.
	 */
	markTipRead: function () {
		this.currentTip.callPutAction('markTipRead', {}, Ext.bind(this.onMarkTipRead, this));
	},
	/**
	 * Callback for when the markTipRead action has been completed. Re-loads the history store
	 */
	onMarkTipRead: function () {
		this.tipHistoryStore.load({
			scope: this,
			callback: this.onHistoryStoreLoaded
		});
	},
	/**
	 * Callback for when the history store has been loaded. Updates the filter
	 */
	onHistoryStoreLoaded: function () {
		this.updateFilter();
	},
	/**
	 * Returns if there are tips in the tip database which aren't read
	 *
	 * @return {Boolean} True if there are tips available, false otherwise
	 */
	hasTips: function () {
		return this.tipStore.count() > 0;
	}
});
