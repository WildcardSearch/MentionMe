/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a module for the auto-completion functionality
 */

var MentionMe = (function(m) {
	var textarea = {},
	container = {},
	items = [],
	maxItems = 5,
	options = {
		minLength: 3,
		maxLength: 30,
	},
	lang = {
		loading: 'loading . . .',
		instructions: 'type a user name',
	},
	fullEditor = false,
	textAreaPadding = 4,
	cursorPointer = 'pointer',
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

	// maxkir js_cursor_position objects
	selectionRange, positioner,

	// internal objects
	cache = {}, popup = {}, keyCache = {};

	/**
	 * setup()
	 *
	 * load options and language (used externally)
	 *
	 * @return  void
	 */
	function setup(opt) {
		Object.extend(lang, opt.lang || {});
		delete opt.lang;
		Object.extend(options, opt || {});
	}

	/**
	 * init()
	 *
	 * prepare to auto-complete
	 *
	 * @return  void
	 */
	function init() {
		//  look for either the Quick Reply text area or the full editor
		var id = 'message';
		if (typeof clickableEditor != 'undefined') {
			id = clickableEditor.textarea;
			fullEditor = true;
			textAreaPadding = 2;
		}

		 // if neither are present, get out
		if (!$(id)) {
			return;
		}

		// gotta love ie
		if (MyBB.browser == 'ie') {
			cursorPointer = 'hand';
		}

		// store our elements
		textarea = $(id);
		container = textarea.up('div');

		// go ahead and build the popup
		popup.init();

		// we'll use these closure-wide to deal with the caret and popup positioning
		positioner = new maxkir.CursorPosition(textarea, textAreaPadding);
		selectionRange = new maxkir.SelectionRange(textarea);

		// poll for the @ char
		Event.observe(textarea, 'keyup', onKeyUp);

		if (m.autoComplete.debug) {
			m.autoComplete.debug.init();
		}
	}

	/**
	 * onKeyUp()
	 *
	 * polling for the @ character when uninitiated and
	 * some navigation and editing for our key cache
	 *
	 * @param  event (Object)
	 * @return  void
	 */
	function onKeyUp(event) {
		getCaret();

		// open the popup when user types an @
		if (!popup.isVisible()) {
			if (textarea.value.slice(selection.start - 1, selection.end) == '@') {
				popup.show();
			}
			return;
		}

		getCaret();

		switch (event.keyCode) {
		case key.ESC:
			popup.hide();
			break;
		case key.LEFT:
		case key.RIGHT:
			keyCache.checkCaret(event.keyCode);
			break;
		default:
			if (keyCache.update()) {
				popup.update();
			}
		}
	}

	/**
	 * onKeyDown()
	 *
	 * basic navigation for when the popup is open
	 *
	 * @param  event (Object)
	 * @return  void
	 */
	function onKeyDown(event) {
		switch (event.keyCode) {
		case key.ENTER:
			insertMention();
			break;
		case key.UP:
			popup.select('previous');
			break;
		case key.DOWN:
			popup.select('next');
			break;
		case key.END:
			popup.select('last');
			break;
		case key.HOME:
			popup.select();
			break;
		case key.PAGE_UP:
			popup.select('previousPage');
			break;
		case key.PAGE_DOWN:
			popup.select('nextPage');
			break;
		default:
			return;
		}

		/**
		 * prevent a few navigation keys from
		 * working when the popup is in view
		 */
		Event.stop(event);
	}

	/**
	 * insertMention()
	 *
	 * insert the mention and get out
	 *
	 * @return  void
	 */
	function insertMention() {
		var lPad = ' ', rPad = ' ', name = popup.getSelectedName(),
		offset = keyCache.getOffset(), mention, prevChar, quote = '';

		if (!name) {
			if (!popup.spinnerIsVisible()) {
				popup.hide();
			}
			return;
		}

		// if the user jammed the mention in without whitespace, add it
		prevChar = textarea.value.charCodeAt(offset - 2);
		if (offset <= 1 ||
			prevChar == key.SPACE ||
			prevChar == key.ENTER ||
			prevChar == key.NEWLINE) {
			lPad = '';
		}

		if (selection.start >= textarea.length ||
		    textarea.value.slice(selection.start, selection.start + 1) == ' ') {
			rPad = '';
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
		mention = lPad + '@' + quote + name + quote + rPad;
		textarea.value = textarea.value.slice(0, offset - 1) + mention + textarea.value.slice(offset + keyCache.getLength());
		setCaret(offset + mention.length - 1);

		// and we're done here (for now)
		popup.hide();
	}

	/**
	 * getCaret()
	 *
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
	 * setCaret()
	 *
	 * position the caret
	 *
	 * @return  void
	 */
	function setCaret(position) {
		if (textarea.setSelectionRange) {
			textarea.focus();
			textarea.setSelectionRange(position, position);
		} else if (textarea.createTextRange) {
			var range = textarea.createTextRange();
			range.collapse(true);
			range.moveEnd('character', position);
			range.moveStart('character', position);
			range.select();
		}
	}

	/**
	 * this object manages the chars typed since the @ symbol
	 */
	keyCache = (function() {
		var data = '',
		mirror = '',
		offset = 0,
		caret = 0;

		/**
		 * init()
		 *
		 * ready the typeahead cache
		 *
		 * @return  void
		 */
		function init() {
			data = '';
			getCaret();
			offset = selection.start;
			mirror = textarea.value;
			caret = 0;
		}

		/**
		 * update()
		 *
		 * mirror the currently typed characters in our key cache
		 *
		 * @return  (Boolean) true if a character was added, false if not
		 */
		function update() {
			if (textarea.value == mirror) {
				return false;
			}

			var insertedChars = (textarea.value.length - mirror.length), newData;

			if (textarea.value.length < offset ||
			    selection.start < offset ||
				data.length + insertedChars > options.maxLength) {
				popup.hide();
				return false;
			}

			newData = textarea.value.slice(offset, offset + data.length + insertedChars);

			updateCaret();
			if (newData == data) {
				return false;
			}

			data = newData;
			mirror = textarea.value;
			return true;
		}

		/**
		 * checkCaret()
		 *
		 * ensure the user is within bounds
		 *
		 * @param  code (Number) event.keyCode
		 * @return  void
		 */
		function checkCaret(code) {
			if (selection.start < offset ||
			    selection.start > offset + data.length ||
				(offset + caret == textarea.value.length && code == key.RIGHT)) {
				popup.hide();
			}
			updateCaret();
		}

		/**
		 * updateCaret()
		 *
		 * sync the caret position internally
		 *
		 * @return  void
		 */
		function updateCaret() {
			caret = selection.start - offset;
		}

		/**
		 * getLength()
		 *
		 * getter for keyCache data length
		 *
		 * @return  (Number) the length of the currently typed text
		 */
		function getLength() {
			return data.length;
		}

		/**
		 * getText()
		 *
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
		 * getOffset()
		 *
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
		 * init()
		 *
		 * ready the name cache
		 *
		 * @return  void
		 */
		function init() {
			loading = true;
			new Ajax.Request('xmlhttp.php', {
				parameters: {
					action: 'mentionme',
					mode: 'get_name_cache'
				},
				onSuccess: loadNameCache
			});
		}

		/**
		 * loadNameCache()
		 *
		 * deal with the server response and store the data
		 *
		 * @param  transport (Object) the XMLHTTP transport
		 * @return  void
		 */
		function loadNameCache(transport) {
			ready = true;
			loading = false;
			data = transport.responseJSON || {};
			if (data.length === 0) {
				data = {};
				popup.showInstructions();
				move();
				return;
			}

			if (popup.isVisible()) {
				popup.update();
			}
		}

		/**
		 * match()
		 *
		 * list names that match the keyCache (currently typed string)
		 *
		 * @return  (Number) the amount of items in total
		 */
		function match() {
			var property, i = 0;

			items = [];
			for (property in data) {
				if (!data.hasOwnProperty(property) || !data[property] || (keyCache.getLength() && property.slice(0, keyCache.getLength()) != keyCache.getText())) {
					continue;
				}

				items.push(data[property]);
				i++;
			}
			items = items.sortBy(function(s) {
				return s.length;
			});
			return i;
		}

		/**
		 * search()
		 *
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
			if (searching || searched.indexOf(search) != -1) {
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

			new Ajax.Request('xmlhttp.php', {
				parameters: {
					action: 'mentionme',
					mode: 'name_search',
					search: search,
				},
				onSuccess: load
			});
		}

		/**
		 * load()
		 *
		 * handle the response solicited by search()
		 *
		 * @param  transport (Object) the XMLHTTP transport
		 * @return  void
		 */
		function load(transport) {
			var n = 0, property, names = transport.responseJSON;

			searching = false;

			// if we have nothing
			if (names == null) {
				// . . . and we had nothing before we searched . . .
				if (popup.spinnerIsVisible()) {
					// get out
					popup.hide();
				}
				return;
			}

			// add all the found names to the cache (will overwrite, not duplicate)
			for (property in names) {
				if (!names.hasOwnProperty(property) || data[property]) {
					continue;
				}

				data[property] = names[property];
				n++;
			}

			if (!n || !popup.isVisible()) {
				return;
			}

			// reset everything and rebuild the list
			match();
			popup.buildItems();
			popup.select();
		}

		/**
		 * isReady()
		 *
		 * getter for ready state
		 *
		 * @return  (Boolean) true if cache loaded, false if not
		 */
		function isReady() {
			return ready;
		}

		/**
		 * isLoading()
		 *
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
		div,
		spinner,
		spinnerVisible = false,
		lineHeight,
		width = 0;

		/**
		 * init()
		 *
		 * ready the popup
		 *
		 * @return  void
		 */
		function init() {
			var e;

			// create and show the main div off-screen
			div = new Element('div', {
				id: 'mentionme_popup',
				'class': 'mentionme_popup',
			}).setStyle({
				left: '-1000px',
				top: '-1000px',
			});
			container.insert(div);
			div.show();

			// fill it with max amount of item divs
			for (e = 0; e < maxItems; e++) {
				div.insert(new Element('div').update(Array(options.maxLength + 1).join('M')));
			}

			// and then figure the line height for later user
			lineHeight = parseInt(div.offsetHeight / maxItems);
			width = div.getStyle('fontSize').replace('px', '') * (options.maxLength - 1);

			// build the spinner
			spinner = new Element('div', {
				id: 'mentionme_spinner',
				'class': 'mentionme_spinner',
			}).insert(new Element('img', {
				src: 'images/spinner.gif',
			}));
			spinner.insert(new Element('span').update(lang.loading));
			div.update(spinner);
		}

		/**
		 * show()
		 *
		 * display the popup where the user was typing (hopefully)
		 *
		 * @return  void
		 */
		function show() {
			var coords = positioner.getPixelCoordinates(), taCoords = textarea.viewportOffset(), left, top;

			// load the name cache if necessary
			if (!cache.isReady() && !cache.isLoading()) {
				cache.init();
			}

			// reset the typeahead
			keyCache.init();

			// go ahead and fill the popup with suggestions from the name cache
			popup.update();

			if (fullEditor) {
				left = coords[0];
				top = coords[1] + clickableEditor.toolbarHeight + lineHeight;
			// Quick Reply
			} else {
				left = taCoords[0] + coords[0];
				top = taCoords[1] + coords[1] +  DomLib.getPageScroll()[1];
			}

			// resize, locate and show the popup, selecting the first item
			move(left, top);
			div.show();
			lastSelected = null;
			select();
			visible = true;

			// for highlighting, selecting and dismissing the popup and items
			Event.observe(div, 'mouseover', onMouseMove);
			Event.observe(div, 'click', onClick);
			Event.observe(document, 'click', hide);
			Event.observe(textarea, 'click', hide);
			Event.observe(textarea, 'keydown', onKeyDown);
		}

		/**
		 * hide()
		 *
		 * hide the popup
		 *
		 * @return  void
		 */
		function hide() {
			div.hide();
			Event.stopObserving(div, 'mouseover', onMouseMove);
			Event.stopObserving(div, 'click', onClick);
			Event.stopObserving(document, 'click', hide);
			Event.stopObserving(textarea, 'click', hide);
			Event.stopObserving(textarea, 'keydown', onKeyDown);
			visible = false;
		}

		/**
		 * clear()
		 *
		 * clear the popup
		 *
		 * @return  void
		 */
		function clear() {
			div.update('');
			lastSelected = null;
			spinnerVisible = false;
		}

		/**
		 * update()
		 *
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
			 * if we have at least {minLength} chars, search to augment
			 * the (incomplete) name cache
			 */
			if (keyCache.getLength() >= options.minLength) {
				cache.search();
			}
		}

		/**
		 * buildItems()
		 *
		 * build the actual list items (divs)
		 *
		 * @return  void
		 */
		function buildItems() {
			var thisClass;

			// if we've got no matches and the spinner isn't up . . .
			if (items.length === 0 && spinnerVisible == false) {
				// . . . and there are typed chars . . .
				if (keyCache.getLength() > 0) {
					// . . . show them what they've typed
					clear();
					div.update(keyCache.getText());
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
				div.insert(new Element('div', {
					id: 'mentionme_popup_item_' + i,
					'class': 'mentionme_popup_item'
				}).update(items[i]).setStyle({
					cursor: cursorPointer,
				}));
			}

			// resize the popup
			move();
		}

		/**
		 * move()
		 *
		 * resize and (optionally) reposition the popup
		 *
		 * @param  left (Number) the left-most x coordinate
		 * @param  top (Number) the top-most y coordinate
		 * @return  void
		 */
		function move(left, top) {
			var style = {
				height: getCurrentHeight() + 'px',
				overflow: 'auto',
				width: width + 'px',
			};

			if (left) {
				style.left = left + 'px';
			}
			if (top) {
				style.top = top + 'px';
			}
			div.setStyle(style);
		}

		/**
		 * highlightSelected()
		 *
		 * assign the 'on' class to the currently selected list item
		 *
		 * @param  noScroll (Boolean) true to highlight without scrolling the item into
		 * view (for the mouse to prevent weirdness) or false to scroll to the newly
		 * highlighted item
		 * @return  void
		 */
		function highlightSelected(noScroll) {
			if (lastSelected == selected || !$('mentionme_popup_item_' + selected)) {
				return;
			}

			var selectedItem = $('mentionme_popup_item_' + selected);
			if ($('mentionme_popup_item_' + lastSelected)) {
				$('mentionme_popup_item_' + lastSelected).removeClassName('mentionme_popup_item_on');
			}
			lastSelected = selected;

			if (selectedItem && !selectedItem.hasClassName('mentionme_popup_item_on')) {
				selectedItem.addClassName('mentionme_popup_item_on');
			}

			if (noScroll != true) {
				selectedItem.up('div').scrollTop = selectedItem.offsetTop;
			}
		}

		/**
		 * onMouseMove()
		 *
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
		 * onClick()
		 *
		 * trigger mention insertion on click
		 *
		 * @param  event (Object)
		 * @return  void
		 */
		function onClick(event) {
			if (selectEventTarget(event)) {
				insertMention();
			} else {
				Event.stop(event);
			}
		}

		/**
		 * selectEventTarget()
		 *
		 * select the element that the event was originally triggered on
		 *
		 * @param  event (Object)
		 * @return  void
		 */
		function selectEventTarget(event) {
			if (!event) {
				return false;
			}

			var idParts, target = event.findElement();

			// IE wigs out when the mouse hovers the scroll bar or border
			try {
				if (!target ||
				    !target.id ||
					target.id == 'mentionme_popup' ||
					target.id == 'mentionme_spinner') {
					return false;
				}
			} catch(e) {
				return false;
			}

			// get the item # from the id
			idParts = target.id.split('_');
			if (!idParts || idParts.length == 0 || !idParts[idParts.length - 1]) {
				return false;
			}

			// if all is good, select it
			selected = idParts[idParts.length - 1];
			return true;
		}

		/**
		 * getSelectedName()
		 *
		 * return the name of the currently selected item (for insertMention)
		 *
		 * @return  void
		 */
		function getSelectedName() {
			if (items.length === 0 || !items[selected]) {
				return;
			}

			return items[selected];
		}

		/**
		 * select()
		 *
		 * highlight an item in the name list
		 *
		 * @param  selection (String) the position label
		 * @return  void
		 */
		function select(selection) {
			switch (selection) {
			case 'last':
				selected = items.length - 1;
				break;
			case 'next':
				selected++;
				if (selected > items.length - 1) {
					selected = 0;
				}
				break;
			case 'previous':
				selected--;
				if (selected < 0) {
					selected = items.length - 1;
				}
				break;
			case 'nextPage':
				selected  += maxItems;
				if (selected > items.length - 1) {
					selected = items.length - 1;
				}
				break;
			case 'previousPage':
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
		 * spinnerIsVisible()
		 *
		 * getter for spinner visibility
		 *
		 * @return  (Boolean) true if visible, false if not
		 */
		function spinnerIsVisible() {
			return spinnerVisible;
		}

		/**
		 * showSpinner()
		 *
		 * show the activity indicator
		 *
		 * @return  void
		 */
		function showSpinner() {
			clear();
			div.update(spinner);
			spinnerVisible = true;
			move();
		}

		/**
		 * showInstructions()
		 *
		 * show the usage prompt
		 *
		 * @return  void
		 */
		function showInstructions() {
			clear();
			div.update('<span style="color: grey; font-style: italic;">' + lang.instructions + '</span>');
		}

		/**
		 * getCurrentHeight()
		 *
		 * approximate height based on initial line measurements
		 *
		 * @return  (Number) the height in pixels
		 */
		function getCurrentHeight() {
			return  (lineHeight * Math.max(1, Math.min(5, items.length))) + 4;
		}

		/**
		 * isVisible()
		 *
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

	// the public methods
	m.autoComplete = {
		init: init,
		setup: setup,
	};

	return m;
})(MentionMe || {});
Event.observe(window, 'load', MentionMe.autoComplete.init);
