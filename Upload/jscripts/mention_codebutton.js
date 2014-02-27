/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains an extended class for the MyBB messageEditor,
 * adding a code button to the editor for mentions
 */

messageEditor = Class.create
(
	messageEditor,
	{
		initialize: function($super, textarea, options)
		{
			// we have nothing to do, just run the parent
			$super(textarea, options);
		},
		showEditor: function($super)
		{
			// build the tool-bars
			$super();

			// add our button to the right of the color drop-down
			this.addToolbarItem
			(
				'topformatting',
				{
					type: 'button',
					name: 'mention',
					insert: 'mention',
					title: this.options.lang.mention,
					image: 'mention.gif'
				}
			);
		},
		insertMyCode: function($super, code, extra)
		{
			// if it our turn,
			if(code == 'mention')
			{
				// run our handler
				this.insertMention();
			}
			else
			{
				// otherwise, just run the parent handler
				$super(code, extra);
			}
		},
		insertMention: function()
		{
			// there is selected text,
			var text = this.getSelectedText($(this.textarea));
			if(text && text != 'undefined')
			{
				// insert it and get out
				this.performInsert('@"' + text + '"', '', true, false);
				return;
			}
			// otherwise show the popup
			MyBB.popupWindow('misc.php?action=mentionme&mode=popup', 'mentionme', 400, 275);
		}
	}
);
