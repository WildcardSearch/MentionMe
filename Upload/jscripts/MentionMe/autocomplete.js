/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a module for the auto-completion functionality
 */

var MentionMe = (function($, m) {
	"use strict";

	var options = {
			minLength: 3,
			maxLength: 30,
			maxItems: 5,
			tid: '',
			fullText: 0,
			showAvatars: 1,
		},

		lang = {
			instructions: "type a user name",
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

	/**
	 * the popup object
	 */
	popup = (function() {
		var visible = false,
			spinnerVisible = false,

			selected = 0,
			lastSelected = null,

			width = 0,
			inputHeight,
			scrollWidthDiff = 0,
			inputHeight,
			lineHeight,

			items = [],
			avatars = [],

			$container,
			$popup,
			$spinner,
			$input,
			$inputDiv,
			$body;

		/**
		 * ready the popup
		 *
		 * @return void
		 */
		function init() {
			var $testDiv,
				$testAvatar,
				container = core.getContainer();

			$popup = $("#mentionme_popup");
			$spinner = $("#mentionme_spinner").hide();
			$input = $("#mentionme_popup_input");
			$inputDiv = $("#mentionme_popup_input_container");
			$body = $("#mentionme_popup_body");

			if (typeof container === 'string' &&
				$("#" + container)) {
				$container = $("#" + container);
			} else if (typeof container === 'object' &&
				$(container).length) {
				$container = $(container);
			} else {
				return false;
			}

			$container.append($popup);
			$popup.css({
				left: "-1000px",
				top: "-1000px",
			}).show();

			inputHeight = $inputDiv.height();

			$testDiv = $("<div/>");

			if (options.showAvatars) {
				$testAvatar = $("<img/>", {
					"class": "mention_user_avatar",
					src: "images/default_avatar.png",
				}).appendTo($testDiv);
			}

			$testDiv.append(Array(options.maxLength + 1)
						.join("M"))
					.addClass("mentionme_popup_item");

			$body.html($testDiv);

			// figure the line height for later use
			lineHeight = parseInt($testDiv.height()) +
				core.lineHeightModifier +
				parseInt($testDiv.css("paddingTop").replace("px", "")) +
				parseInt($testDiv.css("paddingBottom").replace("px", ""));

			scrollWidthDiff = $body.width() - $body[0].scrollWidth;
		}

		/**
		 * display the popup where the user was typing (hopefully)
		 *
		 * @return void
		 */
		function show(left, top) {
			// load the name cache if necessary
			if (!nameCache.isReady() &&
				!nameCache.isLoading()) {
				nameCache.init();
			}

			keyCache.init();

			// go ahead and fill the popup with suggestions from the name cache
			update();

			// resize, locate and show the popup, selecting the first item
			move(left, top);
			$popup.show();
			lastSelected = null;
			select();
			visible = true;

			// for highlighting, selecting and dismissing the popup and items
			$body.mouseover(onMouseMove);
			$body.click(onClick);
			$(document).click(hide);
			core.bindClick(hide);
			$input.keydown(onKeyDown);
			$input.keyup(updateCheck);
			$input.click(onInputClick);
			$input.focus();
		}

		/**
		 * hide the popup
		 *
		 * @return void
		 */
		function hide() {
			$popup.hide();
			$body.unbind("mouseover", onMouseMove);
			$body.unbind("click", onClick);
			$(document).unbind("click", hide);
			core.unBindClick(hide);
			$input.unbind("keydown", onKeyDown);
			$input.unbind("keyup", updateCheck);
			$input.unbind("click", onInputClick);
			visible = false;
			$input.val("");
			core.focus();
		}

		/**
		 * resize and (optionally) reposition the popup
		 *
		 * @param  left (Number) the left-most x coordinate
		 * @param  top (Number) the top-most y coordinate
		 * @return void
		 */
		function move(left, top) {
			var style,
				longestName = nameCache.getLongestName(),
				$testAvatar;

			width = 0;
			if (options.showAvatars) {
				$testAvatar = $("<img/>", {
					"class": "mention_user_avatar",
					src: "images/default_avatar.png",
				}).css({
					left: "-1000px",
					top: "-1000px",
				}).appendTo($container);
				width += $testAvatar.width();
				$testAvatar.remove();
			}

			width += parseInt($body.css("fontSize").replace("px", "") * longestName);

			style = {
				height: getCurrentHeight() + inputHeight + "px",
				width: parseInt(width - scrollWidthDiff) + "px",
			};

			if (typeof left != "undefined") {
				style.left = left + "px";
			}
			if (typeof top != "undefined") {
				style.top = top + "px";
			}

			$popup.css(style);

			$body.css({
				height: getCurrentHeight() + "px",
				width: width,
			});
		}

		/**
		 * fill the popup with suggested names from
		 * the cache and search to fill the gaps
		 *
		 * @return void
		 */
		function update() {
			// if we get here too early, back off
			if (!nameCache.isReady()) {
				showSpinner();
				return;
			}

			// get matching names and insert them into the list, selecting the first
			nameCache.match();
			buildItems();
			lastSelected = null;
			select();

			/**
			 * if we have at least {minLength} chars,
			 * search to augment the (incomplete) name cache
			 */
			if (keyCache.getLength() >= options.minLength) {
				nameCache.search();
			}
		}

		/**
		 * build the actual list items (divs)
		 *
		 * @return  void
		 */
		function buildItems() {
			var i,
				text,
				avatar,
				start,
				c = (navigator.userAgent.toLowerCase().indexOf("msie") !== -1) ?
					"hand" :
					"pointer";

			items = nameCache.getItems();
			avatars = nameCache.getAvatars();

			// if we've got no matches and the spinner isn't up . . .
			if (nameCache.getItemsLength() === 0 &&
				spinnerVisible == false) {
				// . . . and there are typed chars . . .
				if (keyCache.getLength() > 0) {
					// . . . show them what they've typed
					clear();
					$body.html(keyCache.getText());
				} else {
					// . . . otherwise, instruct them (this should rarely, if ever, be seen)
					showInstructions();
				}
				// resize the popup
				if (isVisible()) {
					move();
				}
				return;
			}

			// if we have content, clear out and get ready to build items
			clear();

			for (i = 0; i < nameCache.getItemsLength(); i++) {
				text = items[i];
				if (keyCache.getText()) {
					start = items[i].toLowerCase().indexOf(keyCache.getText());

					if ((options.fullText && start !== -1) ||
						(!options.fullText && start === 0)) {
						text = items[i].slice(0, start) +
						'<span class="mention_name_highlight">' +
						items[i].slice(start, start + keyCache.getText().length) +
						'</span>' +
						items[i].slice(start + keyCache.getText().length);
					}
				}

				avatar = '';
				if (options.showAvatars) {
					if (!avatars[i]) {
						avatars[i] = "images/default_avatar.png";
					}
					avatar = '<img class="mention_user_avatar" src="' + avatars[i] + '" />';
				}
				$body.append($("<div/>", {
					id: "mentionme_popup_item_" + i,
					"class": "mentionme_popup_item"
				}).html(avatar + text).css({
					cursor: c,
				}));
			}

			// resize the popup
			if (isVisible()) {
				move();
			}
		}

		/**
		 * clear the popup
		 *
		 * @return void
		 */
		function clear() {
			$body.html("");
			lastSelected = null;
			spinnerVisible = false;

			// resize the popup
			if (isVisible()) {
				move();
			}
		}

		/**
		 * show the activity indicator
		 *
		 * @return void
		 */
		function showSpinner() {
			clear();
			$body.html($spinner);
			spinnerVisible = true;

			// resize the popup
			if (isVisible()) {
				move();
			}
		}

		/**
		 * show the usage prompt
		 *
		 * @return void
		 */
		function showInstructions() {
			clear();
			$body.html('<span style="color: grey; font-style: italic;">'
				+ lang.instructions +
				"</span>");
		}

		/**
		 * update the popup if necessary
		 *
		 * @param  object event
		 * @return void
		 */
		function updateCheck() {
			if (keyCache.update()) {
				update();
			}
		}

		/**
		 * highlight an item in the name list
		 *
		 * @param  selection (String) the position label
		 * @return void
		 */
		function select(selection) {
			var lastItem = nameCache.getItemsLength() - 1;

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
				selected  += options.maxItems;
				if (selected > lastItem) {
					selected = lastItem;
				}
				break;
			case "previousPage":
				selected  -= options.maxItems;
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
				$("#mentionme_popup_item_" + selected).length === 0) {
				return;
			}

			var selectedItem = $("#mentionme_popup_item_" + selected),
				lastSelectedItem = $("#mentionme_popup_item_" + lastSelected),
				highlightSpan = lastSelectedItem.find("span.mention_name_highlight_on");

			if (lastSelectedItem.length) {
				lastSelectedItem.removeClass("mentionme_popup_item_on");

				if (highlightSpan.length) {
					highlightSpan.removeClass("mention_name_highlight_on");
					highlightSpan.addClass("mention_name_highlight");
				}
			}
			lastSelected = selected;

			if (selectedItem) {
				if (!selectedItem.hasClass("mentionme_popup_item_on")) {
					selectedItem.addClass("mentionme_popup_item_on");
				}

				highlightSpan = selectedItem.find("span.mention_name_highlight");
				if (highlightSpan.length) {
					highlightSpan.removeClass("mention_name_highlight");
					highlightSpan.addClass("mention_name_highlight_on");
				}
			}

			if (noScroll != true) {
				$body.prop("scrollTop", parseInt(selectedItem.prop("offsetTop") - inputHeight));
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
				core.insert();
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
			case key.ESC:
				hide();
				break;
			case key.BACKSPACE:
				if ($input.val() === "") {
					hide();
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
		 * highlight items when the mouse is hovering
		 *
		 * @param  event
		 * @return void
		 */
		function onMouseMove(e) {
			if (selectEventTarget(e)) {
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
				core.insert();
			} else {
				e.preventDefault();
			}
		}

		/**
		 * prevent event bubbling for clicks in input
		 *
		 * @return void
		 */
		function onInputClick(e) {
			e.stopPropagation();
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

			var idParts,
				target = e.target;

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
		 * return the name of the currently selected item
		 *
		 * @return void
		 */
		function getSelectedName() {
			if (nameCache.getItemsLength() === 0 ||
				!items[selected]) {
				return;
			}

			return items[selected];
		}

		/**
		 * approximate height based on initial line measurements
		 *
		 * @return Number the height in pixels
		 */
		function getCurrentHeight() {
			return (lineHeight * Math.max(1, Math.min(options.maxItems, nameCache.getItemsLength()))) + core.heightModifier;
		}

		/**
		 * getter for popup input value
		 *
		 * @return string
		 */
		function getInputValue() {
			return $input.val();
		}

		/**
		 * getter for line height
		 *
		 * @return number
		 */
		function getLineHeight() {
			return lineHeight;
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
			show: show,
			hide: hide,
			move: move,
			update: update,
			buildItems: buildItems,
			showSpinner: showSpinner,
			showInstructions: showInstructions,
			select: select,
			getSelectedName: getSelectedName,
			getCurrentHeight: getCurrentHeight,
			getInputValue: getInputValue,
			getLineHeight: getLineHeight,
			spinnerIsVisible: spinnerIsVisible,
			isVisible: isVisible,
		};
	})(),

	/**
	 * this object manages the chars typed since the @ symbol
	 */
	keyCache = (function() {
		var data = "",
			mirror = "";

		/**
		 * ready the typeahead cache
		 *
		 * @return  void
		 */
		function init() {
			data = "";
			mirror = "";
		}

		/**
		 * mirror the currently typed characters in our key cache
		 *
		 * @return (Boolean) true if a character was added, false if not
		 */
		function update() {
			var ret = false;
			if (data !== popup.getInputValue()) {
				ret = true;
			}

			data = popup.getInputValue();
			return ret;
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

		// the public methods
		return {
			init: init,
			update: update,
			getLength: getLength,
			getText: getText,
		};
	})(),

	/**
	 * the user name cache object
	 */
	nameCache = (function() {
		var data = {},
			threadNames = {},
			allNames = {},
			ready = false,
			loading = false,
			searching = false,
			searched = [],
			items = [],
			avatars = [],
			longestName = 5;

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
					mode: "getNameCache",
					tid: options.tid,
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
			threadNames = response.inThread;
			allNames = response.cached;

			$.extend(data, threadNames, allNames);

			if (data.length === 0) {
				data = {};
				popup.showInstructions();
				// resize the popup
				if (popup.isVisible()) {
					popup.move();
				}
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
			var property,
				i = 0,
				done = {},
				threadItems = [],
				allItems = [],
				threadAvatars = [],
				allAvatars = [];

			items = [];
			avatars = [];
			longestName = 5;

			for (property in threadNames) {
				if (!checkEntry(property, threadNames, done)) {
					continue;
				}

				if (property.length > longestName) {
					longestName = property.length;
				}
				threadItems.push(threadNames[property]['username']);

				if (options.showAvatars) {
					threadAvatars.push(threadNames[property]['avatar']);
				}
				done[property] = true;
				i++;
			}

			$(threadItems).each(function() {
				items.push(this);
			});

			if (options.showAvatars) {
				$(threadAvatars).each(function() {
					avatars.push(this);
				});
			}

			for (property in data) {
				if (!checkEntry(property, data, done)) {
					continue;
				}

				if (property.length > longestName) {
					longestName = property.length;
				}
				allItems.push(data[property]['username']);

				if (options.showAvatars) {
					allAvatars.push(data[property]['avatar']);
				}
				done[property] = true;
				i++;
			}

			allItems = allItems.sort(sortNames);

			$(allItems).each(function() {
				items.push(this);
			});

			if (options.showAvatars) {
				$(allAvatars).each(function() {
					avatars.push(this);
				});
			}
			return i;
		}

		/**
		 * sort names by length
		 *
		 * @return number
		 */
		function sortNames(a, b) {
			if (a.length < b.length) {
				return -1;
			} else if (a.length > b.length) {
				return 1;
			} else {
				return 0;
			}
		}

		/**
		 * check a name before using it
		 *
		 * @return bool
		 */
		function checkEntry(property, data, done) {
			if (!data.hasOwnProperty(property) ||
				!data[property] ||
				done[property] ||
				(keyCache.getLength() &&
				((!options.fullText &&
				property.slice(0, keyCache.getLength()) !== keyCache.getText()) ||
				(options.fullText &&
				property.indexOf(keyCache.getText()) === -1)))) {
				return false;
			}
			return true;
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

		/**
		 * getter for the item list
		 *
		 * @return array
		 */
		function getItems() {
			return items;
		}

		/**
		 * getter for the avatar list
		 *
		 * @return array
		 */
		function getAvatars() {
			return avatars;
		}

		/**
		 * getter for items length
		 *
		 * @return number
		 */
		function getItemsLength() {
			return items.length;
		}

		/**
		 * getter for longest name length
		 *
		 * @return number
		 */
		function getLongestName() {
			return longestName;
		}

		// the public methods
		return {
			init: init,
			match: match,
			search: search,
			isReady: isReady,
			isLoading: isLoading,
			getItems: getItems,
			getAvatars: getAvatars,
			getItemsLength: getItemsLength,
			getLongestName: getLongestName,
		};
	})(),

	/**
	 * interface for textarea element
	 */
	textareaCore = (function() {
		var $textarea,
			$container,

			selection = {
				start: 0,
				end: 0,
			};

		/**
		 * see if there is a valid textarea
		 *
		 * @return bool
		 */
		function check() {
			if ($("#message").length == 0 &&
				$("#signature").length == 0) {
				return false;
			}
			return true;
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
			} else if ($("#signature").length) {
				id = "signature";
			} else {
				return false;
			}

			// if no suitable text area is present, get out
			if (!$("#" + id).length) {
				return false;
			}

			// store our elements
			$textarea = $("#" + id);
			$container = $textarea.closest("div");

			// go ahead and build the popup
			popup.init();

			// poll for the @ char
			$textarea.keyup(onKeyUp);
		}

		/**
		 * polling for the @ character when uninitiated and
		 * some navigation and editing for our key cache
		 *
		 * @param  event
		 * @return void
		 */
		function onKeyUp(e) {
			var proceed = true;

			getCaret();

			// open the popup when user types an @
			if (!popup.isVisible()) {
				$([key.LEFT, key.RIGHT, key.UP, key.DOWN, key.BACKSPACE, key.ESC, key.SHIFT, key.CTRL, key.ALT, key.ENTER]).each(function() {
					if (e.keyCode == this) {
						proceed = false;
					}
				});

				if (proceed &&
					$textarea.val().slice(selection.start - 1, selection.end) == "@") {
					showPopup();
				}
			}
		}

		/**
		 * position and display the popup
		 *
		 * @return void
		 */
		function showPopup() {
			var coords = $textarea.caret("offset"),
				left = coords.left + 3,
				top = coords.top;

			popup.show(left, top);
		}

		/**
		 * insert the mention and get out
		 *
		 * @return void
		 */
		function insertMention() {
			var mention = prepMention();

			if (!mention) {
				if (!popup.spinnerIsVisible()) {
					popup.hide();
				}
				return;
			}

			getCaret();

			$textarea.val($textarea.val().slice(0, selection.start) +
				mention +
				$textarea.val().slice(selection.start));
			setCaret(selection.start + mention.length);

			// and we're done here (for now)
			popup.hide();
		}

		/**
		 * store info about the caret/selection
		 *
		 * @return void
		 */
		function getCaret() {
			var range = $textarea.caret("pos");

			selection.start = range;
			selection.end = range;
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
		 * API for popup to attach event listener
		 *
		 * @return bool
		 */
		function bindClick(f) {
			$textarea.click(f);
		}

		/**
		 * API for popup to detach event listener
		 *
		 * @return bool
		 */
		function unBindClick(f) {
			$textarea.unbind('click', f);
		}

		/**
		 * API for popup to focus editor
		 *
		 * @return bool
		 */
		function focus() {
			$textarea.focus();
		}

		/**
		 * getter for the container element
		 *
		 * @return mixed
		 */
		function getContainer() {
			return $container;
		}

		return {
			init: init,
			check: check,
			heightModifier: 0,
			lineHeightModifier: 1,
			insert: insertMention,
			bindClick: bindClick,
			unBindClick: unBindClick,
			focus: focus,
			getContainer: getContainer,
		};
	})(),

	/**
	 * interface for SCEditor
	 */
	sceditorCore = (function() {
		var editor,
			rangeHelper,

			selection = {
				start: 0,
				end: 0,
			},

			$container,
			$iFrame,
			$body,
			$currentNode;

		/**
		 * see if there is a valid SCEditor instance
		 *
		 * @return bool
		 */
		function check() {
			if (MyBBEditor === null ||
				typeof MyBBEditor !== "object" ||
				!MyBBEditor.getRangeHelper ||
				typeof MyBBEditor.getRangeHelper !== "function") {
				return false;
			}
			return true;
		}

		/**
		 * prepare to auto-complete
		 *
		 * @return void
		 */
		function init() {
			var doc;

			editor = MyBBEditor;
			rangeHelper = editor.getRangeHelper();

			$iFrame = $("iframe");
			$container = $iFrame.closest("div");
			doc = ($iFrame[0].contentDocument) ? $iFrame[0].contentDocument : $iFrame[0].contentWindow.document;
			$body = $(doc).find("body");

			editor.keyUp(onKeyUp);

			// go ahead and build the popup
			popup.init();
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

			if (!e.keyCode) {
				if (e.originalEvent &&
					e.originalEvent.keyCode) {
					e.keyCode = e.originalEvent.keyCode;
				} else {
					return;
				}
			}

			// open the popup when user types an @
			if (!popup.isVisible()) {
				$([key.LEFT, key.RIGHT, key.UP, key.DOWN, key.BACKSPACE, key.ESC, key.SHIFT, key.CTRL, key.ALT, key.ENTER]).each(function() {
					if (e.keyCode == this) {
						proceed = false;
					}
				});

				if (proceed &&
					$currentNode.text().slice(selection.start - 1, selection.end) == "@") {
					showPopup();
				}
				return;
			}
		}

		/**
		 * position and display the popup
		 *
		 * @return void
		 */
		function showPopup() {
			var coords = $body.caret("offset", {
					iframe: $iFrame[0],
				}),
				left = parseInt(coords.left) + 7,
				top = parseInt(coords.top + $container.find('div.sceditor-toolbar').height()) + 7;

			popup.show(left, top);
		}

		/**
		 * insert the mention and get out
		 *
		 * @return void
		 */
		function insertMention() {
			var mention = prepMention();

			if (!mention) {
				if (!popup.spinnerIsVisible()) {
					popup.hide();
				}
				return;
			}

			editor.insert(mention);

			// and we're done here (for now)
			popup.hide();
		}

		/**
		 * store info about the caret/selection
		 *
		 * @return void
		 */
		function getCaret() {
			var range = rangeHelper.selectedRange();

			if (range.startContainer) {
				$currentNode = $(range.startContainer);
			} else {
				$currentNode = $(editor.currentNode());
			}

			selection.start = range.startOffset;
			selection.end = range.endOffset;
		}

		/**
		 * API for popup to attach event listener
		 *
		 * @return bool
		 */
		function bindClick(f) {
			$body.click(f);
		}

		/**
		 * API for popup to detach event listener
		 *
		 * @return bool
		 */
		function unBindClick(f) {
			$body.unbind('click', f);
		}

		/**
		 * API for popup to focus editor
		 *
		 * @return bool
		 */
		function focus() {
			$iFrame.focus();
		}

		/**
		 * getter for the container element
		 *
		 * @return mixed
		 */
		function getContainer() {
			return $container;
		}

		return {
			init: init,
			check: check,
			heightModifier: -2,
			lineHeightModifier: 0,
			insert: insertMention,
			bindClick: bindClick,
			unBindClick: unBindClick,
			focus: focus,
			getContainer: getContainer,
		};
	})(),

	/**
	 * interface for CKEditor
	 */
	ckeditorCore = (function() {
		var editor,

			selection = {
				start: 0,
				end: 0,
			},

			$container,
			$iFrame,
			$body,
			$currentNode;

		/**
		 * see if there is a valid CKEditor instance
		 *
		 * @return bool
		 */
		function check() {
			if (typeof CKEDITOR === "undefined" ||
				typeof CKEDITOR.instances === "undefined" ||
				CKEDITOR.instances.length == 0) {
				return false;
			}
			return true;
		}

		/**
		 * prepare to auto-complete
		 *
		 * @return void
		 */
		function init() {
			var key = Object.keys(CKEDITOR.instances)[0];

			if (typeof CKEDITOR.instances[key] !== "object") {
				return false;
			}

			editor = CKEDITOR.instances[key];

			editor.on("instanceReady", finalize);
		}

		/**
		 * when CKEditor is ready, finish up initialization
		 *
		 * @return void
		 */
		function finalize() {
			var doc;

			$iFrame = $("iframe");
			$container = $iFrame.closest("div");
			doc = ($iFrame[0].contentDocument) ? $iFrame[0].contentDocument : $iFrame[0].contentWindow.document;
			$body = $(doc).find("body");

			$(editor.document.$).keyup(onKeyUp);

			// go ahead and build the popup
			popup.init();
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

			// open the popup when user types an @
			if (!popup.isVisible()) {
				$([key.LEFT, key.RIGHT, key.UP, key.DOWN, key.BACKSPACE, key.ESC, key.SHIFT, key.CTRL, key.ALT, key.ENTER]).each(function() {
					if (e.keyCode == this) {
						proceed = false;
					}
				});

				if (proceed &&
					getPrevChar() == "@") {
					showPopup();
				}
				return;
			}
		}

		/**
		 * position and display the popup
		 *
		 * @return void
		 */
		function showPopup() {
			var coords = $body.caret("offset", {
					iframe: $iFrame[0],
				}),
				iFrameOffset = $iFrame.offset(),
				left = parseInt(coords.left + iFrameOffset.left) + 2,
				top = parseInt(coords.top + iFrameOffset.top);

			popup.show(left, top);
		}

		/**
		 * insert the mention and get out
		 *
		 * @return void
		 */
		function insertMention() {
			var mention = prepMention();

			if (!mention) {
				if (!popup.spinnerIsVisible()) {
					popup.hide();
				}
				return;
			}

			editor.insertText(mention);

			// and we're done here (for now)
			popup.hide();
		}

		/**
		 * get the character just before the cursor
		 * credit:
		 * http://stackoverflow.com/questions/20972431/ckeditor-get-previous-character-of-current-cursor-position
		 *
		 * @return mixed
		 */
		function getPrevChar() {
			var range = editor.getSelection().getRanges()[0],
				startNode;

			if (!range ||
				!range.startContainer) {
				return null;
			}
			startNode = range.startContainer;

			if (startNode.type == CKEDITOR.NODE_TEXT &&
				range.startOffset) {
				// Range at the non-zero position of a text node.
				return startNode.getText()[range.startOffset - 1];
			} else {
				// Expand the range to the beginning of editable.
				range.collapse(true);
				range.setStartAt(editor.editable(), CKEDITOR.POSITION_AFTER_START);

				// Let's use the walker to find the closes (previous) text node.
				var walker = new CKEDITOR.dom.walker(range),
					node;

				while ((node = walker.previous())) {
					// If found, return the last character of the text node.
					if (node.type == CKEDITOR.NODE_TEXT) {
						return node.getText().slice( -1 );
					}
				}
			}

			// Selection starts at the 0 index of the text node and/or there's no previous text node in contents.
			return null;
		}

		/**
		 * API for popup to attach event listener
		 *
		 * @return bool
		 */
		function bindClick(f) {
			$body.click(f);
		}

		/**
		 * API for popup to detach event listener
		 *
		 * @return bool
		 */
		function unBindClick(f) {
			$body.unbind('click', f);
		}

		/**
		 * API for popup to focus editor
		 *
		 * @return bool
		 */
		function focus() {
			editor.focus();
		}

		/**
		 * getter for the container element
		 *
		 * @return mixed
		 */
		function getContainer() {
			return $container;
		}

		return {
			init: init,
			check: check,
			heightModifier: 0,
			lineHeightModifier: 2,
			insert: insertMention,
			bindClick: bindClick,
			unBindClick: unBindClick,
			focus: focus,
			getContainer: getContainer,
		};
	})(),

	core = null;

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

		$(['minLength', 'maxLength', 'maxItems', 'fullText', 'showAvatars']).each(function() {
			options[this] = parseInt(options[this], 10);
		});
	}

	/**
	 * prepare to auto-complete
	 *
	 * @return void
	 */
	function init() {
		if (ckeditorCore.check()) {
			core = ckeditorCore;
		} else if (sceditorCore.check()) {
			core = sceditorCore;
		} else if (textareaCore.check()) {
			core = textareaCore;
		} else {
			return;
		}
		core.init();
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
	 * quote a name and return it
	 *
	 * @return mixed
	 */
	function prepMention() {
		var name = popup.getSelectedName(),
			quote = "";

		if (!name) {
			return;
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

		return quote +
			name +
			quote +
			' ';
	}

	$(init);

	m.autoComplete = {
		setup: setup,
	};

	return m;
})(jQuery, MentionMe || {});
