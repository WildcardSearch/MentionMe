/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a module for the auto-completion functionality
 */

var MentionMe = (function($, m) {
	var maxItems = 5,
		options = {
			minLength: 3,
			maxLength: 30,
		},
		lang = {
			loading: "loading . . .",
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
	 * @var object options
	 * @return  void
	 */
	function setup(opt) {
		$.extend(lang, opt.lang || {});
		delete opt.lang;
		$.extend(options, opt || {});
	}

	/**
	 * prepare to auto-complete
	 *
	 * @return  void
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
	 * @return  void
	 */
	function onKeyUp(e) {
		getCaret();

		// open the popup when user types an @
		if (!popup.isVisible() &&
			e.keyCode != key.LEFT &&
			e.keyCode != key.RIGHT &&
			e.keyCode != key.BACKSPACE &&
			e.keyCode != key.ESC) {
			if ($textarea.val().slice(selection.start - 1, selection.end) == "@") {
				popup.show();
			}
			return;
		}

		getCaret();

		switch (e.keyCode) {
		case key.ESC:
			popup.hide();
			break;
		case key.LEFT:
		case key.RIGHT:
			keyCache.checkCaret(e.keyCode);
			break;
		default:
			if (keyCache.update()) {
				popup.update();
			}
		}
	}

	/**
	 * insert the mention and get out
	 *
	 * @return  void
	 */
	function insertMention() {
		var name = popup.getSelectedName(),
			offset = keyCache.getOffset(),
			mention,
			prevChar,
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

		// do the insertion
		mention =
			"@" +
			quote +
			name +
			quote;

		$textarea.val($textarea.val().slice(0, offset - 1) +
			mention +
			$textarea.val().slice(offset +
			keyCache.getLength()));
		setCaret(offset + mention.length - 1);

		// and we're done here (for now)
		popup.hide();
	}

	/**
	 * store info about the caret/selection
	 *
	 * @return  void
	 */
	function getCaret() {
		var range = selectionRange.get_selection_range();

		selection.start = range[0];
		selection.end = range[1];
	}

	/**
	 * position the caret
	 *
	 * @return  void
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

		arrayPageScroll = new Array("", yScroll);

		return arrayPageScroll;
	}

	/*
	 * This function is from quirksmode.org
	 * Modified for use in MyBB
	 */
	function getPageSize() {
		var xScroll, yScroll;

		if (window.innerHeight &&
			window.scrollMaxY) {
			xScroll = document.body.scrollWidth;
			yScroll = window.innerHeight + window.scrollMaxY;
		// All but Explorer Mac
		} else if (document.body.scrollHeight > document.body.offsetHeight) {
			xScroll = document.body.scrollWidth;
			yScroll = document.body.scrollHeight;
		// Explorer Mac...would also work in Explorer 6 Strict, Mozilla and Safari
		} else {
			xScroll = document.body.offsetWidth;
			yScroll = document.body.offsetHeight;
		}

		var windowWidth, windowHeight;
		// all except Explorer
		if (self.innerHeight) {
			windowWidth = self.innerWidth;
			windowHeight = self.innerHeight;
		// Explorer 6 Strict Mode
		} else if (document.documentElement &&
			document.documentElement.clientHeight) {
			windowWidth = document.documentElement.clientWidth;
			windowHeight = document.documentElement.clientHeight;
		// other Explorers
		} else if (document.body) {
			windowWidth = document.body.clientWidth;
			windowHeight = document.body.clientHeight;
		}

		var pageHeight, pageWidth;

		// For small pages with total height less then height of the viewport
		if (yScroll < windowHeight) {
			pageHeight = windowHeight;
		} else {
			pageHeight = yScroll;
		}

		// For small pages with total width less then width of the viewport
		if (xScroll < windowWidth) {
			pageWidth = windowWidth;
		} else {
			pageWidth = xScroll;
		}

		var arrayPageSize = new Array(pageWidth, pageHeight,windowWidth, windowHeight);

		return arrayPageSize;
	}

	/**
	 * this object manages the chars typed since the @ symbol
	 */
	keyCache = (function() {
		var data = "",
		mirror = "",
		offset = 0,
		caret = 0;

		/**
		 * ready the typeahead cache
		 *
		 * @return  void
		 */
		function init() {
			data = "";
			getCaret();
			offset = selection.start;
			mirror = $textarea.val();
			caret = 0;
		}

		/**
		 * mirror the currently typed characters in our key cache
		 *
		 * @return  (Boolean) true if a character was added, false if not
		 */
		function update() {
			if ($textarea.val() == mirror) {
				return false;
			}

			var insertedChars = ($textarea.val().length - mirror.length),
				newData;

			if ($textarea.val().length < offset ||
			    selection.start < offset ||
				data.length + insertedChars > options.maxLength) {
				popup.hide();
				return false;
			}

			newData = $textarea.val().slice(offset, offset + data.length + insertedChars);

			updateCaret();
			if (newData == data) {
				return false;
			}

			data = newData;
			mirror = $textarea.val();
			return true;
		}

		/**
		 * ensure the user is within bounds
		 *
		 * @param  code (Number) event.keyCode
		 * @return  void
		 */
		function checkCaret(code) {
			if (selection.start < offset ||
			    selection.start > offset + data.length ||
				(offset + caret == $textarea.val().length &&
				code == key.RIGHT)) {
				popup.hide();
			}
			updateCaret();
		}

		/**
		 * sync the caret position internally
		 *
		 * @return  void
		 */
		function updateCaret() {
			caret = selection.start - offset;
		}

		/**
		 * getter for keyCache data length
		 *
		 * @return  (Number) the length of the currently typed text
		 */
		function getLength() {
			return data.length;
		}

		/**
		 * getter for keyCache data
		 *
		 * @param  natural (Boolean) true to return the cache as-is,
		 * false to return lowercase
		 * @return  (String) the currently typed text
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
		 * @return  void
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
					mode: "get_name_cache"
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
		 * @return  (Number) the amount of items in total
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
		 * search for names that begin with the first {minLength} chars
		 * of the keyCache
		 *
		 * @return  void
		 */
		function search() {
			var search = keyCache.getText().slice(0, options.minLength);

			/**
			 * if we're already searching or we've already searched
			 * this minimum-length name prefix, there is nothing to do
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
					mode: "name_search",
					search: search,
				},
				success: load,
			});
		}

		/**
		 * handle the response solicited by search()
		 *
		 * @param  transport (Object) the XMLHTTP transport
		 * @return  void
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
		 * @return  (Boolean) true if cache loaded, false if not
		 */
		function isReady() {
			return ready;
		}

		/**
		 * getter for loading state
		 *
		 * @return  (Boolean) true if cache loaded, false if not
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

		selected = 0,
		lastSelected = null,

		$div,
		$spinner,
		spinnerVisible = false,

		lineHeight,
		width = 0;

		/**
		 * ready the popup
		 *
		 * @return  void
		 */
		function init() {
			var e;

			// create and show the main div off-screen
			$div = $("<div/>", {
				id: "mentionme_popup",
				"class": "mentionme_popup",
			}).css({
				left: "-1000px",
				top: "-1000px",
			});
			$container.append($div);
			$div.show();

			// fill it with max amount of item divs
			for (e = 0; e < maxItems; e++) {
				$div.append($("<div/>")
					.html(Array(options.maxLength + 1)
					.join("M"))
					.addClass("mentionme_popup_item"));
			}

			// and then figure the line height for later user
			lineHeight = parseInt($div.height() / maxItems);
			width = $div.css("fontSize").replace("px", "") * (options.maxLength - 1);

			// build the spinner
			$spinner = $("<div/>", {
				id: "mentionme_spinner",
				"class": "mentionme_spinner",
			}).append($("<img/>", {
				src: "images/spinner.gif",
			}));
			$spinner.append($("<span/>").html(lang.loading));
			$div.html($spinner);
		}

		/**
		 * display the popup where the user was typing (hopefully)
		 *
		 * @return  void
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

			left = taCoords.left + coords[0];
			top = taCoords.top + coords[1] +  getPageScroll()[1];

			// resize, locate and show the popup, selecting the first item
			move(left, top);
			$div.show();
			lastSelected = null;
			select();
			visible = true;

			// for highlighting, selecting and dismissing the popup and items
			$div.mouseover(onMouseMove);
			$div.click(onClick);
			$(document).click(hide);
			$textarea.click(hide);
			$textarea.keydown(onKeyDown);
		}

		/**
		 * hide the popup
		 *
		 * @return  void
		 */
		function hide() {
			$div.hide();
			$div.unbind("mouseover", onMouseMove);
			$div.unbind("click", onClick);
			$(document).unbind("click", hide);
			$textarea.unbind("click", hide);
			$textarea.unbind("keydown", onKeyDown);
			visible = false;
		}

		/**
		 * clear the popup
		 *
		 * @return  void
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
		 * @return  void
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
			var thisClass;

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
				height: getCurrentHeight() + "px",
				overflow: "auto",
				width: width + "px",
			};

			if (left) {
				style.left = left + "px";
			}
			if (top) {
				style.top = top + "px";
			}
			$div.css(style);
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
				selectedItem.parent("div").prop("scrollTop", selectedItem.prop("offsetTop"));
			}
		}

		/**
		 * basic navigation for when the popup is open
		 *
		 * @param  event (Object)
		 * @return void
		 */
		function onKeyDown(event) {
			switch (event.keyCode) {
			case key.ENTER:
				insertMention();
				break;
			case key.UP:
				select("previous");
				break;
			case key.DOWN:
				select("next");
				break;
			case key.END:
				select("last");
				break;
			case key.HOME:
				select();
				break;
			case key.PAGE_UP:
				select("previousPage");
				break;
			case key.PAGE_DOWN:
				select("nextPage");
				break;
			default:
				return;
			}

			/**
			 * prevent a few navigation keys from
			 * working when the popup is in view
			 */
			event.preventDefault();
		}

		/**
		 * highlight items when the mouse is hovering
		 *
		 * @param  event (Object)
		 * @return  void
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
		 * @return  void
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
		 * @return  void
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
		 * @return  void
		 */
		function select(selection) {
			switch (selection) {
			case "last":
				selected = items.length - 1;
				break;
			case "next":
				selected++;
				if (selected > items.length - 1) {
					selected = 0;
				}
				break;
			case "previous":
				selected--;
				if (selected < 0) {
					selected = items.length - 1;
				}
				break;
			case "nextPage":
				selected  += maxItems;
				if (selected > items.length - 1) {
					selected = items.length - 1;
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
		 * @return  (Boolean) true if visible, false if not
		 */
		function spinnerIsVisible() {
			return spinnerVisible;
		}

		/**
		 * show the activity indicator
		 *
		 * @return  void
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
		 * @return  void
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
		 * @return  (Number) the height in pixels
		 */
		function getCurrentHeight() {
			return  (lineHeight * Math.max(1, Math.min(5, items.length))) + 4;
		}

		/**
		 * getter for popup visibility
		 *
		 * @return  (Boolean) true if visible, false if not
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
