/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains an extended class for the MyBB messageEditor,
 * adding a code button to the editor for mentions
 */

messageEditor = Class.create(messageEditor,	{
	/**
	 * initialize()
	 *
	 * post-object construction
	 *
	 * @param - $super - (callback) the inherited method
	 * @param - textarea - (string) the id of the text area to use
	 * @param - options - (object) editor config
	 * @return: n/a
	 */
	initialize: function($super, textarea, options) {
		// we have nothing to do, just run the parent
		$super(textarea, options);
	},

	/**
	 * showEditor()
	 *
	 * add our button to the top tool bar
	 *
	 * @param - $super - (callback) the inherited method
	 * @return: n/a
	 */
	showEditor: function($super) {
		// build the tool-bars
		$super();

		// add our button to the right of the color drop-down
		this.addToolbarItem('topformatting', {
			type: 'button',
			name: 'mention',
			insert: 'mention',
			title: this.options.lang.mention,
			image: 'mention.gif'
		});
	},

	/**
	 * insertMyCode()
	 *
	 * monitor the calls to the parent method and intercept mentions
	 *
	 * @return: n/a
	 */
	insertMyCode: function($super, code, extra) {
		// if its our turn,
		if (code == 'mention') {
			// run our handler
			this.insertMention();
			return;
		}

		// otherwise, just run the parent handler
		$super(code, extra);
	},

	/**
	 * insertMention()
	 *
	 * perform the insertion or open the popup
	 *
	 * @return: n/a
	 */
	insertMention: function() {
		// there is selected text,
		var text = this.getSelectedText($(this.textarea));
		if (text && text != 'undefined') {
			// insert it and get out
			this.performInsert('@"' + text + '"', '', true, false);
			return;
		}

		// otherwise show the popup
		MyBB.popupWindow('misc.php?action=mentionme&mode=popup', 'mentionme', 400, 275);
	}
});
