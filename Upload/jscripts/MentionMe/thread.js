/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a module for the single mention functionality in mention.php
 */

var MentionMe = (function($, m) {
	"use strict";

	var useCkEditor = false,
		editor = null,
		$textarea = null;

	/**
	 * determine whether to use the standard editor or CKEDITOR/Rin
	 *
	 * @return void
	 */
	function init() {
		if (typeof CKEDITOR !== "undefined" &&
			typeof CKEDITOR.instances !== "undefined" &&
			typeof CKEDITOR.instances["message"] === "object") {
			editor = CKEDITOR.instances["message"];
			useCkEditor = true;
		} else {
			if ($("#message").length) {
				$textarea = $("#message");
			}
		}
		return;
	}

	/**
	 * insert the mention into the Quick Reply text area
	 *
	 * @param  string
	 * @return void
	 */
	function insert(name) {
		var quote = '',
			quotedName = '';

		// find an appropriate quote character based on whether or not the
		// mentioned name includes that character
		if (name.indexOf('"') == -1) {
			quote = '"';
		} else if (name.indexOf("'") == -1) {
			quote = "'";
		} else if (name.indexOf("`") == -1) {
			quote = "`";
		}

		quotedName = '@' +
			quote +
			name +
			quote +
			' ';

		if (useCkEditor) {
			if (editor.getData()) {
				quotedName = "\n" + quotedName;
			}
			editor.insertText(quotedName);
			editor.focus();
		} else {
			if ($textarea.val()) {
				$textarea.val($textarea.val() + "\n");
			}
			$textarea.val($textarea.val() + quotedName);
			$textarea.focus();
		}
	}

	m.insert = insert;

	$(init);

	return m;
})(jQuery, MentionMe || {});
