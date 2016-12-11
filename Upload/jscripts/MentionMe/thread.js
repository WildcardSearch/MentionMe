/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a module for the single mention functionality in mention.php
 */

var MentionMe = (function($, m) {
	"use strict";

	/**
	 * insert the mention into the Quick Reply text area
	 *
	 * @param  string
	 * @return void
	 */
	function insert(name) {
		var $textarea = $("#message"),
			quote = '';

		// find an appropriate quote character based on whether or not the
		// mentioned name includes that character
		if (name.indexOf('"') == -1) {
			quote = '"';
		} else if (name.indexOf("'") == -1) {
			quote = "'";
		} else if (name.indexOf("`") == -1) {
			quote = "`";
		}

		$textarea.val($textarea.val() +
			'@' +
			quote +
			name +
			quote +
			' ');

		$textarea.focus();
	}

	m.insert = insert;

	return m;
})(jQuery, MentionMe || {});
