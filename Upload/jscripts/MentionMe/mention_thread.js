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
		var id = 'message';
		if (typeof clickableEditor != 'undefined') {
			id = clickableEditor.textarea;
		}
		$(id).value += '@"' + name + '" ';
		$(id).focus();
	}

	m.insert = insert;

	return m;
})(MentionMe || {});
