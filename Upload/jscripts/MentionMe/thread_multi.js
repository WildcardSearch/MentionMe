/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a class for the multi-mention functionality in mention.php
 */

var MentionMe = (function($, m) {
	"use strict";

	/**
	 * 'turn on' any previously selected multi-mention buttons and if
	 * applicable, show the mention insert notice in Quick Reply
	 *
	 * @return void
	 */
	function init() {
		var postIds,
			mentioned = Cookie.get("multi_mention");

		if (mentioned) {
			postIds = mentioned.split("|");

			$(postIds).each(function() {
				if ($("#multi_mention_link_" + this).length) {
					$("#multi_mention_link_" + this).addClass('postbit_multimention_on');
				}
			});

			if ($("#quickreply_multi_mention").length) {
				$("#quickreply_multi_mention").show();
			}
		}
		return true;
	}

	/**
	 * if this is a new mention, add it to the cookie and if applicable,
	 * turn on the button and show the Quick Reply notice
	 *
	 * @param  Number post id
	 * @return void
	 */
	function mention(pid) {
		var $el,
			newPostIds = new Array(),
			mentioned = Cookie.get("multi_mention"),
			isNew = true,
			postIds;

		if (mentioned) {
			postIds = mentioned.split("|");
			$(postIds).each(function() {
				if (this != pid &&
					this != '') {
					newPostIds[newPostIds.length] = this;
				} else if (this == pid) {
					isNew = false;
				}
			});
		}

		if (isNew == true) {
			newPostIds[newPostIds.length] = pid;
		}

		// toggle CSS buttons
		if ($("#multi_mention_link_" + pid).length) {
			$el = $("#multi_mention_link_" + pid);
			if (isNew == true) {
				$el.removeClass('postbit_multimention');
				$el.addClass('postbit_multimention_on');
			} else {
				$el.removeClass('postbit_multimention_on');
				$el.addClass('postbit_multimention');
			}
		}

		if ($('#quickreply_multi_mention')) {
			if (newPostIds.length > 0) {
				$('#quickreply_multi_mention').show();
			} else {
				$('#quickreply_multi_mention').hide();
			}
		}
		Cookie.set("multi_mention", newPostIds.join("|"));
	}

	/**
	 * fetch the mentions
	 *
	 * @return Boolean true to use standard newreply.php
	 * 	functionality or false if AJAX was used
	 */
	function load() {
		if (use_xmlhttprequest == 1) {
			$.ajax({
				type: 'get',
				url: 'xmlhttp.php',
				data: {
					action: 'mentionme',
					mode: 'getMultiMentioned',
					load_all: 1,
				},
				success: insert,
			});
			return false;
		}
		return true;
	}

	/**
	 * insert any mentions returned by AJAX
	 *
	 * @param  (XMLHTTP response)
	 * @return void
	 */
	function insert(data) {
		var $textarea = $("#message"),
			message;

		if (data.match(/<error>(.*)<\/error>/)) {
			message = data.match(/<error>(.*)<\/error>/);
			if (!message[1]) {
				message[1] = "An unknown error occurred.";
			}

			$.jGrowl('There was an error fetching the posts.\n\n' + message[1], {theme:'jgrowl_error'});
		} else if (data) {
			if ($textarea.val()) {
				$textarea.val($textarea.val() + "\n");
			}
			$textarea.val($textarea.val() + data);
		}

		clear();
		$('#quickreply_multi_mention').hide();
		$('#mentioned_ids').val('all');
		$textarea.focus();
	}

	/**
	 * clear the cookie and any buttons
	 *
	 * @return void
	 */
	function clear() {
		var $el,
			postIds,
			mentioned = Cookie.get("multi_mention");

		$('#quickreply_multi_mention').hide();
		if (mentioned) {
			postIds = mentioned.split("|");
			$(postIds).each(function() {
				if ($("#multi_mention_link_" + this).length) {
					$el = $("#multi_mention_link_" + this);
					$el.removeClass('postbit_multimention_on');
					$el.addClass('postbit_multimention');
				}
			});
		}
		Cookie.unset('multi_mention');
	}

	// the public methods
	m.multi = {
		init: init,
		load: load,
		clear: clear,
		mention: mention,
	};

	$(init);

	return m;
})(jQuery, MentionMe || {});
