/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a module for the auto-completion functionality
 */

var MentionMe = (function($, m) {
	"use strict";

	var maxItems = 5,
		options = {
			minLength: 3,
			maxLength: 30,
		},
		lang = {
			instructions: "type a user name",
		},
		textAreaPadding = 2,
		cursorPointer = "pointer",

		items = [],
		selection = {
			start: 0,
			end: 0,
		},

		key = {
			BACKSPACE: 8,
			NEWLINE: 10,
			ENTER: 13,
			SHIFT: 16,
			CTRL: 17,
			ALT: 18,
			ESC: 27,
			SPACE: 32,
			PAGE_UP: 33,
			PAGE_DOWN: 34,
			END: 35,
			HOME: 36,
			LEFT: 37,
			UP: 38,
			RIGHT: 39,
			DOWN: 40,
			DEL: 46,
		},

		$textarea,
		$container,
		$input,
		$inputDiv,

		// maxkir js_cursor_position objects
		selectionRange,
		positioner,

		// internal objects
		cache = {},
		popup = {},
		keyCache = {},

		d = {
			log: function(){},
		};

	/**
	 * load options and language (used externally)
	 *
	 * @param  object options
	 * @return void
	 */
	function setup(opt) {
		$.extend(lang, opt.lang || {});
		delete opt.lang;
		$.extend(options, opt || {});
	}

	/**
	 * prepare to auto-complete
	 *
	 * @return void
	 */
	function init() {
		//  look for a text area
		var id;

		// almost every page uses this id
		if ($("#message").length) {
			id = "message";
		// usercp.php and modcp.php use this id
		} else if ($("#signature")) {
			id = "signature";
		} else {
			return;
		}

		// if no suitable text area is present, get out
		if (!$("#" + id).length) {
			return;
		}

		// gotta love ie
		if (navigator.userAgent.toLowerCase().indexOf("msie") != -1) {
			cursorPointer = "hand";
		}

		// store our elements
		$textarea = $("#" + id);
		$container = $textarea.closest("div");

		// go ahead and build the popup
		popup.init();

		// we'll use these closure-wide to deal with the caret and popup positioning
		positioner = new maxkir.CursorPosition($textarea[0], textAreaPadding);
		selectionRange = new maxkir.SelectionRange($textarea[0]);

		// poll for the @ char
		$textarea.keyup(onKeyUp);

		if (m.autoComplete.debug) {
			m.autoComplete.debug.init();
		}
	}

	/**
	 * polling for the @ character when uninitiated and
	 * some navigation and editing for our key cache
	 *
	 * @param  event (Object)
	 * @return void
	 */
	function onKeyUp(e) {
		var proceed = true;

		getCaret();

		// open the popup when user types an @
		if (!popup.isVisible()) {
			$([key.LEFT, key.RIGHT, key.UP, key.DOWN, key.BACKSPACE, key.ESC, key.SHIFT, key.CTRL, key.ALT]).each(function() {
				if (e.keyCode == this) {
					proceed = false;
				}
			});

			if (proceed &&
				$textarea.val().slice(selection.start - 1, selection.end) == "@") {
				popup.show();
			}
		}
	}

	/**
	 * basic navigation for when the popup is open
	 *
	 * @param  event (Object)
	 * @return void
	 */
	function onKeyDown(e) {
		switch (e.keyCode) {
		case key.ENTER:
			insertMention();
			break;
		case key.UP:
			popup.select("previous");
			break;
		case key.DOWN:
			popup.select("next");
			break;
		case key.END:
			popup.select("last");
			break;
		case key.HOME:
			popup.select();
			break;
		case key.PAGE_UP:
			popup.select("previousPage");
			break;
		case key.PAGE_DOWN:
			popup.select("nextPage");
			break;
		case key.ESC:
			popup.hide();
			break;
		case key.BACKSPACE:
			if ($input.val() === "") {
				popup.hide();
			}
			return;
			break;
		default:
			return;
		}

		/**
		 * prevent a few navigation keys from
		 * working when the popup is in view
		 */
		e.preventDefault();
	}

	/**
	 * insert the mention and get out
	 *
	 * @return void
	 */
	function insertMention() {
		var name = popup.getSelectedName(),
			offset = keyCache.getOffset(),
			quote = "";

		if (!name) {
			if (!popup.spinnerIsVisible()) {
				popup.hide();
			}
			return;
		}

		getCaret();

		// find an appropriate quote character based on whether or not the
		// mentioned name includes that character
		if (name.indexOf('"') == -1) {
			quote = '"';
		} else if (name.indexOf("'") == -1) {
			quote = "'";
		} else if (name.indexOf("`") == -1) {
			quote = "`";
		}

		$textarea.val($textarea.val().slice(0, offset) +
			quote +
			name +
			quote +
			$textarea.val().slice(offset));
		setCaret(offset + name.length + 2);

		// and we're done here (for now)
		popup.hide();
	}

	/**
	 * store info about the caret/selection
	 *
	 * @return void
	 */
	function getCaret() {
		var range = selectionRange.get_selection_range();

		selection.start = range[0];
		selection.end = range[1];
	}

	/**
	 * position the caret
	 *
	 * @param  Number
	 * @return void
	 */
	function setCaret(position) {
		var temp = $textarea[0];

		if (temp.setSelectionRange) {
			temp.focus();
			temp.setSelectionRange(position, position);
		} else if (temp.createTextRange) {
			var range = temp.createTextRange();
			range.collapse(true);
			range.moveEnd("character", position);
			range.moveStart("character", position);
			range.select();
		}
	}

	/**
	 * This function is from quirksmode.org
	 * Modified for use in MyBB
	*/
	function getPageScroll() {
		var yScroll;

		if (self.pageYOffset) {
			yScroll = self.pageYOffset;
		// Explorer 6 Strict
		} else if (document.documentElement &&
			document.documentElement.scrollTop) {
			yScroll = document.documentElement.scrollTop;
		// all other Explorers
		} else if (document.body) {
			yScroll = document.body.scrollTop;
		}

		return new Array("", yScroll);
	}

	/**
	 * this object manages the chars typed since the @ symbol
	 */
	keyCache = (function() {
		var data = "",
			mirror = "",
			offset = 0;

		/**
		 * ready the typeahead cache
		 *
		 * @return  void
		 */
		function init() {
			data = "";
			mirror = ""
			offset = selection.start;
		}

		/**
		 * mirror the currently typed characters in our key cache
		 *
		 * @return (Boolean) true if a character was added, false if not
		 */
		function update() {
			var ret = false;
			if (data !== $input.val()) {
				ret = true;
			}

			data = $input.val();
			return ret;
		}

		/**
		 * ensure the user is within bounds
		 *
		 * @param  Number key code
		 * @return void
		 */
		function checkCaret(code) {
			if ($input.val().length > options.maxLength) {
				popup.hide();
			}
		}

		/**
		 * getter for keyCache data length
		 *
		 * @return (Number) the length of the currently typed text
		 */
		function getLength() {
			return data.length;
		}

		/**
		 * getter for keyCache data
		 *
		 * @param  Boolean true to return the cache as-is,
		 * 	false to return lowercase
		 * @return String the currently typed text
		 */
		function getText(natural) {
			if (natural != true) {
				return data.toLowerCase();
			}
			return data;
		}

		/**
		 * getter for offset
		 *
		 * @return void
		 */
		function getOffset() {
			return offset;
		}

		// the public methods
		return {
			init: init,
			update: update,
			checkCaret: checkCaret,
			getLength: getLength,
			getText: getText,
			getOffset: getOffset,
		};
	})();

	/**
	 * the user name cache object
	 */
	cache = (function() {
		var data = {},
		ready = false,
		loading = false,
		searching = false,
		searched = [];

		/**
		 * ready the name cache
		 *
		 * @return  void
		 */
		function init() {
			loading = true;
			$.ajax({
				type: "post",
				url: "xmlhttp.php",
				data: {
					action: "mentionme",
					mode: "getNameCache"
				},
				success: loadNameCache,
			});
		}

		/**
		 * deal with the server response and store the data
		 *
		 * @param  transport (Object) the XMLHTTP transport
		 * @return  void
		 */
		function loadNameCache(response) {
			ready = true;
			loading = false;
			data = response;
			if (data.length === 0) {
				data = {};
				popup.showInstructions();
				popup.move();
				return;
			}

			if (popup.isVisible()) {
				popup.update();
			}
		}

		/**
		 * list names that match the keyCache (currently typed string)
		 *
		 * @return Number total items matched
		 */
		function match() {
			var property, i = 0;

			items = [];
			for (property in data) {
				if (!data.hasOwnProperty(property) ||
					!data[property] ||
					(keyCache.getLength() &&
					property.slice(0, keyCache.getLength()) != keyCache.getText())) {
					continue;
				}

				items.push(data[property]);
				i++;
			}
			items = items.sort(function(a, b) {
				if (a.length < b.length) {
					return -1;
				} else if (a.length > b.length) {
					return 1;
				} else {
					return 0;
				}
			});
			return i;
		}

		/**
		 * search for names that begin with the first
		 * {minLength} chars of the keyCache
		 *
		 * @return void
		 */
		function search() {
			var search = keyCache.getText().slice(0, options.minLength);

			/**
			 * if we're already searching or we've
			 * already searched this minimum-length
			 * name prefix, there is nothing to do
			 */
			if (searching ||
				searched.indexOf(search) != -1) {
				// if the spinner is up then we found nothing
				if (popup.spinnerIsVisible()) {
					// so get out
					popup.hide();
				}
				return;
			}

			// store this search so we don't repeat
			searched.push(search);
			searching = true;

			if (items.length === 0) {
				popup.showSpinner();
			}

			$.ajax({
				type: "post",
				url: "xmlhttp.php",
				data: {
					action: "mentionme",
					mode: "nameSearch",
					search: search,
				},
				success: load,
			});
		}

		/**
		 * handle the response solicited by search()
		 *
		 * @param  transport (Object) the XMLHTTP transport
		 * @return void
		 */
		function load(names) {
			var n = 0, property;

			searching = false;

			// if we have nothing
			if (!names) {
				// . . . and we had nothing before we searched . . .
				if (popup.spinnerIsVisible()) {
					// get out
					popup.hide();
				}
				return;
			}

			// add all the found names to the cache (will overwrite, not duplicate)
			for (property in names) {
				if (!names.hasOwnProperty(property) ||
					data[property]) {
					continue;
				}

				data[property] = names[property];
				n++;
			}

			if (!n ||
				!popup.isVisible()) {
				return;
			}

			// reset everything and rebuild the list
			match();
			popup.buildItems();
			popup.select();
		}

		/**
		 * getter for ready state
		 *
		 * @return Boolean true if cache loaded, false if not
		 */
		function isReady() {
			return ready;
		}

		/**
		 * getter for loading state
		 *
		 * @return Boolean true if cache loaded, false if not
		 */
		function isLoading() {
			return loading;
		}

		// the public methods
		return {
			init: init,
			match: match,
			search: search,
			isReady: isReady,
			isLoading: isLoading,
		};
	})();

	/**
	 * the popup object
	 */
	popup = (function() {
		var visible = false,
		virgin = true,

		selected = 0,
		lastSelected = null,

		$mainDiv,
		$div,
		$spinner,

		spinnerVisible = false,

		width = 0,
		scrollWidth = 0,
		lineHeight,
		inputHeight;

		/**
		 * ready the popup
		 *
		 * @return void
		 */
		function init() {
			var $testDiv;

			// create and show the main div off-screen
			$mainDiv = $("#mentionme_popup").css({
				left: "-1000px",
				top: "-1000px",
			});
			$spinner = $("#mentionme_spinner");
			$input = $("#mentionme_popup_input");
			$inputDiv = $("#mentionme_popup_input_container");
			$div = $("#mentionme_popup_body");

			$container.append($mainDiv.show());

			inputHeight = $inputDiv.height();

			$testDiv = $("<div/>")
						.html(Array(options.maxLength + 1)
						.join("M"))
						.addClass("mentionme_popup_item");

			$div.append($testDiv);

			// and then figure the line height for later use
			lineHeight = parseInt($testDiv.height()) + 1;
			width = $div.css("fontSize").replace("px", "") * (options.maxLength - 1);

			$div.width(width);
			scrollWidth = $div[0].scrollWidth;
		}

		/**
		 * display the popup where the user was typing (hopefully)
		 *
		 * @return void
		 */
		function show() {
			var coords = positioner.getPixelCoordinates(),
				taCoords = $textarea[0].getBoundingClientRect(),
				left,
				top;

			// load the name cache if necessary
			if (!cache.isReady() &&
				!cache.isLoading()) {
				cache.init();
			}

			// reset the typeahead
			keyCache.init();

			// go ahead and fill the popup with suggestions from the name cache
			popup.update();

			left = taCoords.left +
				   coords[0] +
				   5;
			top = (taCoords.top +
				   coords[1] +
				   getPageScroll()[1]) -
				   lineHeight +
				   3;

			// resize, locate and show the popup, selecting the first item
			move(left, top);
			$mainDiv.show();
			lastSelected = null;
			select();
			visible = true;

			// for highlighting, selecting and dismissing the popup and items
			$div.mouseover(onMouseMove);
			$div.click(onClick);
			$(document).click(hide);
			$textarea.click(hide);
			$input.keydown(onKeyDown);
			$input.keyup(updateCheck);
			$input.focus();
		}

		/**
		 * hide the popup
		 *
		 * @return void
		 */
		function hide() {
			$mainDiv.hide();
			$div.unbind("mouseover", onMouseMove);
			$div.unbind("click", onClick);
			$(document).unbind("click", hide);
			$textarea.unbind("click", hide);
			$input.unbind("keydown", onKeyDown);
			$input.unbind("keyup", updateCheck);
			visible = false;
			$input.val("");
			$textarea.focus();
		}

		/**
		 * update the popup if necessary
		 *
		 * @param  object event
		 * @return void
		 */
		function updateCheck(e) {
			if (keyCache.update()) {
				update();
			}
		}

		/**
		 * clear the popup
		 *
		 * @return void
		 */
		function clear() {
			$div.html("");
			lastSelected = null;
			spinnerVisible = false;
		}

		/**
		 * fill the popup with suggested names from
		 * the cache and search to fill the gaps
		 *
		 * @return void
		 */
		function update() {
			// if we get here too early, back off
			if (!cache.isReady()) {
				popup.showSpinner();
				return;
			}

			// get matching names and insert them into the list, selecting the first
			cache.match();
			buildItems();
			lastSelected = null;
			select();

			/**
			 * if we have at least {minLength} chars,
			 * search to augment the (incomplete) name cache
			 */
			if (keyCache.getLength() >= options.minLength) {
				cache.search();
			}
		}

		/**
		 * build the actual list items (divs)
		 *
		 * @return  void
		 */
		function buildItems() {
			var i;

			// if we've got no matches and the spinner isn't up . . .
			if (items.length === 0 &&
				spinnerVisible == false) {
				// . . . and there are typed chars . . .
				if (keyCache.getLength() > 0) {
					// . . . show them what they've typed
					clear();
					$div.html(keyCache.getText());
				} else {
					// . . . otherwise, instruct them (this should rarely, if ever, be seen)
					showInstructions();
				}
				// resize the popup
				move();
				return;
			}

			// if we have content, clear out and get ready to build items
			clear();

			for (i = 0; i < items.length; i++) {
				$div.append($("<div/>", {
					id: "mentionme_popup_item_" + i,
					"class": "mentionme_popup_item"
				}).html(items[i]).css({
					cursor: cursorPointer,
				}));
			}

			// resize the popup
			move();
		}

		/**
		 * resize and (optionally) reposition the popup
		 *
		 * @param  left (Number) the left-most x coordinate
		 * @param  top (Number) the top-most y coordinate
		 * @return void
		 */
		function move(left, top) {
			var style = {
				height: getCurrentHeight() + inputHeight + "px",
				width: scrollWidth + "px",
			};

			if (typeof left != "undefined") {
				style.left = left + "px";
			}
			if (typeof top != "undefined") {
				style.top = top + "px";
			}

			$mainDiv.css(style);

			$div.css({
				height: getCurrentHeight() + "px",
				width: width + "px",
			});
		}

		/**
		 * assign the "on" class to the currently
		 * selected list item
		 *
		 * @param  noScroll (Boolean) true to highlight
		 * without scrolling the item into view
		 * (for the mouse to prevent weirdness) or
		 * false to scroll to the newly highlighted item
		 *
		 * @return  void
		 */
		function highlightSelected(noScroll) {
			if (lastSelected == selected ||
				!$("#mentionme_popup_item_" + selected)) {
				return;
			}

			var selectedItem = $("#mentionme_popup_item_" + selected);
			if ($("#mentionme_popup_item_" + lastSelected)) {
				$("#mentionme_popup_item_" + lastSelected).removeClass("mentionme_popup_item_on");
			}
			lastSelected = selected;

			if (selectedItem &&
				!selectedItem.hasClass("mentionme_popup_item_on")) {
				selectedItem.addClass("mentionme_popup_item_on");
			}

			if (noScroll != true) {
				$div.prop("scrollTop", selectedItem.prop("offsetTop") - $inputDiv.height());
			}
		}

		/**
		 * highlight items when the mouse is hovering
		 *
		 * @param  event (Object)
		 * @return void
		 */
		function onMouseMove(event) {
			if (selectEventTarget(event)) {
				highlightSelected(true);
			}
		}

		/**
		 * trigger mention insertion on click
		 *
		 * @param  event (Object)
		 * @return void
		 */
		function onClick(e) {
			if (selectEventTarget(e)) {
				insertMention();
			} else {
				e.preventDefault();
			}
		}

		/**
		 * select the element that the event was originally triggered on
		 *
		 * @param  event
		 * @return void
		 */
		function selectEventTarget(e) {
			if (!e) {
				return false;
			}

			var idParts, target = e.target;

			// IE wigs out when the mouse hovers the scroll bar or border
			try {
				if (!target ||
				    !target.id ||
					target.id == "mentionme_popup" ||
					target.id == "mentionme_spinner") {
					return false;
				}
			} catch(e) {
				return false;
			}

			// get the item # from the id
			idParts = target.id.split("_");
			if (!idParts ||
				idParts.length == 0 ||
				!idParts[idParts.length - 1]) {
				return false;
			}

			// if all is good, select it
			selected = idParts[idParts.length - 1];
			return true;
		}

		/**
		 * return the name of the currently selected item (for insertMention)
		 *
		 * @return void
		 */
		function getSelectedName() {
			if (items.length === 0 ||
				!items[selected]) {
				return;
			}

			return items[selected];
		}

		/**
		 * highlight an item in the name list
		 *
		 * @param  selection (String) the position label
		 * @return void
		 */
		function select(selection) {
			var lastItem = items.length - 1;

			switch (selection) {
			case "last":
				selected = lastItem;
				break;
			case "next":
				selected++;
				if (selected > lastItem) {
					selected = 0;
				}
				break;
			case "previous":
				selected--;
				if (selected < 0) {
					selected = lastItem;
				}
				break;
			case "nextPage":
				selected  += maxItems;
				if (selected > lastItem) {
					selected = lastItem;
				}
				break;
			case "previousPage":
				selected  -= maxItems;
				if (selected < 0) {
					selected = 0;
				}
				break;
			default:
				selected = 0;
				break;
			}

			highlightSelected();
		}

		/**
		 * getter for spinner visibility
		 *
		 * @return Boolean true if visible, false if not
		 */
		function spinnerIsVisible() {
			return spinnerVisible;
		}

		/**
		 * show the activity indicator
		 *
		 * @return void
		 */
		function showSpinner() {
			clear();
			$div.html($spinner);
			spinnerVisible = true;
			move();
		}

		/**
		 * show the usage prompt
		 *
		 * @return void
		 */
		function showInstructions() {
			clear();
			$div.html('<span style="color: grey; font-style: italic;">'
				+ lang.instructions +
				"</span>");
		}

		/**
		 * approximate height based on initial line measurements
		 *
		 * @return Number the height in pixels
		 */
		function getCurrentHeight() {
			return (lineHeight * Math.max(1, Math.min(5, items.length))) + 2;
		}

		/**
		 * getter for popup visibility
		 *
		 * @return Boolean true if visible, false if not
		 */
		function isVisible() {
			return visible;
		}

		// the public methods
		return {
			init: init,
			hide: hide,
			show: show,
			move: move,
			clear: clear,
			update: update,
			buildItems: buildItems,
			select: select,
			getSelectedName: getSelectedName,
			showSpinner: showSpinner,
			isVisible: isVisible,
			spinnerIsVisible: spinnerIsVisible,
			showInstructions: showInstructions,
		};
	})();

	// the public method
	m.autoComplete = {
		setup: setup,
	};

	$(init);

	return m;
})(jQuery, MentionMe || {});
