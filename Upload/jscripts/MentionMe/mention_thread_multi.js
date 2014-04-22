/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a class for the multi-mention functionality in mention.php
 */

var MentionMe = (function(m) {
	var spinner;

	/**
	 * init()
	 *
	 * 'turn on' any previously selected multi-mention buttons and if
	 * applicable, show the mention insert notice in Quick Reply
	 *
	 * @return  void
	 */
	function init() {
		var element, post_ids, mentioned = Cookie.get("multi_mention");

		if (mentioned) {
			post_ids = mentioned.split("|");

			post_ids.each(function(post_id) {
				// standard image buttons
				if ($("multi_mention_" + post_id)) {
					element = $("multi_mention_" + post_id);
					element.src = element.src.replace("postbit_multi_mention.gif", "postbit_multi_mention_on.gif");
				}

				// CSS buttons
				if ($("multi_mention_link_" + post_id)) {
					element = $("multi_mention_link_" + post_id);
					element.addClassName('postbit_multimention_on');
				}
			});

			if ($('quickreply_multi_mention')) {
				$('quickreply_multi_mention').show();
			}
		}
		return true;
	}

	/**
	 * mention()
	 *
	 * if this is a new mention, add it to the cookie and if applicable,
	 * turn on the button and show the Quick Reply notice
	 *
	 * @param: pid (Number) the post id
	 * @return  void
	 */
	function mention(pid) {
		var element, new_post_ids = new Array(),
		mentioned = Cookie.get("multi_mention"),
		is_new = true, post_ids;

		if (mentioned) {
			post_ids = mentioned.split("|");
			post_ids.each(function(post_id) {
				if (post_id != pid && post_id != '') {
					new_post_ids[new_post_ids.length] = post_id;
				} else if (post_id == pid) {
					is_new = false;
				}
			});
		}

		if (is_new == true) {
			new_post_ids[new_post_ids.length] = pid;
		}

		// standard image buttons
		if ($("multi_mention_" + pid)) {
			element = $("multi_mention_" + pid);
			if (is_new == true) {
				element.src = element.src.replace("postbit_multi_mention.gif", "postbit_multi_mention_on.gif");
			} else {
				element.src = element.src.replace("postbit_multi_mention_on.gif", "postbit_multi_mention.gif");
			}
		}

		// CSS buttons
		if ($("multi_mention_link_" + pid)) {
			element = $("multi_mention_link_" + pid);
			if (is_new == true) {
				element.addClassName('postbit_multimention_on');
			} else {
				element.removeClassName('postbit_multimention_on');
			}
		}

		if ($('quickreply_multi_mention')) {
			if (new_post_ids.length > 0) {
				$('quickreply_multi_mention').show();
			} else {
				$('quickreply_multi_mention').hide();
			}
		}
		Cookie.set("multi_mention", new_post_ids.join("|"));
	}

	/**
	 * load()
	 *
	 * fetch the mentions
	 *
	 * @return: (Boolean) true to use standard newreply.php functionality or
	 * false if AJAX was used
	 */
	function load() {
		if (use_xmlhttprequest == 1) {
			spinner = new ActivityIndicator("body", {
				image: imagepath + "/spinner_big.gif"
			});

			new Ajax.Request('xmlhttp.php', {
				method: 'get',
				parameters: {
					action: 'mentionme',
					mode: 'get_multi_mentioned',
					load_all: 1
				},
				onComplete: insert
			});
			return false;
		}
		return true;
	}

	/**
	 * insert()
	 *
	 * insert any mentions return by AJAX
	 *
	 * @param - transport - (XMLHTTP response)
	 * @return: n/a
	 */
	function insert(transport) {
		var id, message;

		if (transport.responseText.match(/<error>(.*)<\/error>/)) {
			message = transport.responseText.match(/<error>(.*)<\/error>/);
			if (!message[1]) {
				message[1] = "An unknown error occurred.";
			}

			if (spinner) {
				spinner.destroy();
				spinner = '';
			}
			alert('There was an error fetching the posts.\n\n'+message[1]);
		} else if (transport.responseText) {
			id = 'message';

			if (typeof clickableEditor != 'undefined') {
				id = clickableEditor.textarea;
			}

			if ($(id).value) {
				$(id).value += "\n";
			}
			$(id).value += transport.responseText;
		}

		clear();
		$('quickreply_multi_mention').hide();
		$('mentioned_ids').value = 'all';

		if (spinner) {
			spinner.destroy();
			spinner = '';
		}
		$(id).focus();
	}

	/**
	 * clear()
	 *
	 * clear the cookie and any buttons
	 *
	 * @return: n/a
	 */
	function clear() {
		var element, post_ids, mentioned = Cookie.get("multi_mention");

		$('quickreply_multi_mention').hide();
		if (mentioned) {
			post_ids = mentioned.split("|");
			post_ids.each(function(post_id) {
				// standard image buttons
				if ($("multi_mention_" + post_id)) {
					element = $("multi_mention_" + post_id);
					element.src = element.src.replace("postbit_multi_mention_on.gif", "postbit_multi_mention.gif");
				}

				// CSS buttons
				if ($("multi_mention_link_" + post_id)) {
					element = $("multi_mention_link_" + post_id);
					element.removeClassName('postbit_multimention_on');
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

	return m;
})(MentionMe || {});
Event.observe(document, 'dom:loaded', MentionMe.multi.init);
