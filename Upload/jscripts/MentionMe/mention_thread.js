/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a module for the single mention functionality in mention.php
 */

var MentionMe = (function(m) {
	/**
	 * insert()
	 *
	 * insert the mention into the Quick Reply text area
	 *
	 * @param - name - (string)
	 * @return: n/a
	 */
	function insert(name) {
		var id = 'message', quote = '';

		if (typeof clickableEditor != 'undefined') {
			id = clickableEditor.textarea;
		}

		// find an appropriate quote character based on whether or not the
		// mentioned name includes that character
		if (name.indexOf('"') == -1) {
			quote = '"';
		} else if (name.indexOf("'") == -1) {
			quote = "'";
		} else if (name.indexOf("`") == -1) {
			quote = "`";
		}

		$(id).value += '@' + quote + name + quote + ' ';
		$(id).focus();
	}

	m.insert = insert;

	return m;
})(MentionMe || {});
