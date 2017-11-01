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
			tid: "",
			fullText: 0,
			showAvatars: 1,
			imageDirectory: "images",
			lockSelection: 1,
		},

		lang = {
			instructions: "type a user name",
		},

		key = {
			BACKSPACE: 8,
			ENTER: 13,
			SHIFT: 16,
			CTRL: 17,
			ALT: 18,
			ESC: 27,
			PAGE_UP: 33,
			PAGE_DOWN: 34,
			END: 35,
			HOME: 36,
			LEFT: 37,
			UP: 38,
			RIGHT: 39,
			DOWN: 40,
			INSERT: 45,
			DELETE: 46,
			NUMLOCK: 144,
		},

	/**
	 * the popup object
	 */
	Popup = (function() {
		/**
		 * constructor
		 *
		 * @param  function editor interface
		 * @return void
		 */
		function MentionMeAutoCompletePopup(editorInterface) {
			var $testDiv, $testAvatar, container;

			this.editorInterface = editorInterface;
			container = this.editorInterface.getContainer();

			// clone the master popup and get linked up to the copy
			this.$popup = $("#mentionme_master_popup").clone().attr("id", "");
			this.$spinner = this.$popup.find("div.mentionme_spinner").hide();
			this.$input = this.$popup.find("input.mentionme_popup_input");
			this.$inputDiv = this.$popup.find("div.mentionme_popup_input_container");
			this.$body = this.$popup.find("div.mentionme_popup_body");

			if (typeof container === "string" &&
				$("#" + container).length) {
				this.$container = $("#" + container);
			} else if (typeof container === "object" &&
				$(container).length) {
				this.$container = $(container);
			} else {
				return false;
			}

			this.$container.append(this.$popup);
			this.$popup.css({
				left: "-1000px",
				top: "-1000px",
			}).show();

			this.inputHeight = this.$inputDiv.height();

			$testDiv = $("<div/>");

			if (options.showAvatars) {
				$testAvatar = $("<img/>", {
					"class": "mention_user_avatar",
					src: options.defaultAvatar,
				}).appendTo($testDiv);
			}

			$testDiv.append(Array(options.maxLength + 1).join("M"))
				.addClass("mentionme_popup_item");

			this.$body.html($testDiv);

			// figure the line height for later use
			this.lineHeight = pi($testDiv.height()) +
				this.editorInterface.lineHeightModifier +
				pi($testDiv.css("paddingTop").replace("px", "")) +
				pi($testDiv.css("paddingBottom").replace("px", ""));

			this.$instructions = $("<span/>", {
				"class": "mentionme_popup_instructions",
			}).html(lang.instructions);

			this.scrollWidthDiff = this.$body.width() - this.$body[0].scrollWidth;

			this.keyCache = new KeyCache(this);
			this.nameCache = new NameCache(this);
		}

		/**
		 * display the popup where the user was typing (hopefully)
		 *
		 * @param  int
		 * @param  int
		 * @return void
		 */
		function show(left, top) {
			this.keyCache.clear();

			// go ahead and fill the popup with suggestions from the name cache
			this.update();

			// resize, locate and show the popup, selecting the first item
			this.move(left, top);
			this.$popup.show();
			this.lastSelected = null;
			this.select();
			this.visible = true;

			// for highlighting, selecting and dismissing the popup and items
			this.$body.mouseover($.proxy(this.onMouseMove, this));
			this.$body.click($.proxy(this.onClick, this));
			$(document).click($.proxy(this.hide, this));
			this.editorInterface.bindClick($.proxy(this.hide, this));
			this.$input.keydown($.proxy(this.onKeyDown, this));
			this.$input.keyup($.proxy(this.updateCheck, this));
			this.$input.click($.proxy(this.onInputClick, this));

			this.$input.focus();
		}

		/**
		 * hide the popup
		 *
		 * @return void
		 */
		function hide() {
			this.$popup.hide();

			this.$body.off("mouseover", this.onMouseMove);
			this.$body.off("click", this.onClick);
			$(document).off("click", this.hide);
			this.editorInterface.unbindClick(this.hide);
			this.$input.off("keydown", this.onKeyDown);
			this.$input.off("keyup", this.updateCheck);
			this.$input.off("click", this.onInputClick);

			this.visible = false;
			this.$input.val("");
		}

		/**
		 * resize and/or reposition the popup
		 *
		 * @param  int x1
		 * @param  int y1
		 * @return void
		 */
		function move(left, top) {
			var style,
				longestName = this.nameCache.getLongestName(),
				$testAvatar;

			this.width = 0;
			if (options.showAvatars) {
				$testAvatar = $("<img/>", {
					"class": "mention_user_avatar",
					src: options.defaultAvatar,
				}).css({
					left: "-1000px",
					top: "-1000px",
				}).appendTo(this.$container);
				this.width += $testAvatar.width();
				$testAvatar.remove();
			}

			this.width += pi(this.$body.css("fontSize").replace("px", "") * longestName);

			style = {
				height: this.getCurrentHeight() + this.inputHeight + "px",
				width: pi(this.width - this.scrollWidthDiff) + "px",
			};

			if (typeof left != "undefined") {
				style.left = left + "px";
			}
			if (typeof top != "undefined") {
				style.top = top + "px";
			}

			this.$popup.css(style);

			this.$body.css({
				height: this.getCurrentHeight() + "px",
				width: this.width,
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
			if (!this.nameCache.isReady()) {
				this.showSpinner();
				return;
			}

			// get matching names and insert them into the list, selecting the first
			this.nameCache.match();
			this.buildItems();
			this.lastSelected = null;
			this.select();

			/**
			 * if we have at least {minLength} chars,
			 * search to augment the (incomplete) name cache
			 */
			if (this.keyCache.getLength() >= options.minLength) {
				this.nameCache.search();
			}
		}

		/**
		 * build the actual list items (divs)
		 *
		 * @return  void
		 */
		function buildItems() {
			var i, text, avatar, avatarPath, start,
				cacheLength = this.nameCache.getItemsLength(),
				data = this.nameCache.getData(),
				c = (navigator.userAgent.toLowerCase().indexOf("msie") !== -1) ? "hand" : "pointer";

			this.items = this.nameCache.getItems();

			// if we've got no matches and the spinner isn't up . . .
			if (cacheLength === 0 &&
				this.spinnerVisible == false) {
				// . . . and there are typed chars . . .
				if (this.keyCache.getLength() > 0) {
					// . . . show them what they've typed
					this.clear();
					this.$body.html($("<span/>", {
						"class": "mentionme_typed_text",
					}).html(this.keyCache.getText()));
				} else {
					// . . . otherwise, instruct them (this should rarely, if ever, be seen)
					this.showInstructions();
				}

				// resize the popup
				if (this.isVisible()) {
					this.move();
				}
				return;
			}

			// if we have content, clear out and get ready to build items
			this.clear();

			for (i = 0; i < cacheLength; i++) {
				if (typeof this.items[i] === "undefined" ||
					typeof data[this.items[i]] === "undefined" ||
					typeof data[this.items[i]]["username"] === "undefined" ||
					typeof data[this.items[i]]["avatar"] === "undefined") {
					continue;
				}

				text = data[this.items[i]]["username"];
				if (this.keyCache.getText()) {
					start = this.items[i].indexOf(this.keyCache.getText());

					if ((options.fullText && start !== -1) ||
						(!options.fullText && start === 0)) {
						text = text.slice(0, start) +
						'<span class="mention_name_highlight">' +
						text.slice(start, start + this.keyCache.getLength()) +
						"</span>" +
						text.slice(start + this.keyCache.getLength());
					}
				}

				avatar = "";
				if (options.showAvatars) {
					avatarPath = data[this.items[i]]["avatar"];
					if (avatarPath.length == 0) {
						avatarPath = options.defaultAvatar;
					}
					avatar = $("<img/>", {
						"class": "mention_user_avatar",
						src: avatarPath,
					}).one("error", function() {
						this.src = options.defaultAvatar;
					});
				}

				this.$body.append($("<div/>", {
					"class": "mentionme_popup_item mentionme_popup_item_" + i,
				}).append(avatar).append(text).css({
					cursor: c,
				}));
			}

			// resize the popup
			if (this.isVisible()) {
				this.move();
			}
		}

		/**
		 * clear the popup
		 *
		 * @return void
		 */
		function clear() {
			this.$body.html("");
			this.lastSelected = null;
			this.spinnerVisible = false;

			// resize the popup
			if (this.isVisible()) {
				this.move();
			}
		}

		/**
		 * show the activity indicator
		 *
		 * @return void
		 */
		function showSpinner() {
			this.clear();
			this.$body.html(this.$spinner);
			this.spinnerVisible = true;

			// resize the popup
			if (this.isVisible()) {
				this.move();
			}
		}

		/**
		 * show the usage prompt
		 *
		 * @return void
		 */
		function showInstructions() {
			this.clear();
			this.$body.html(this.$instructions);
		}

		/**
		 * update the popup if necessary
		 *
		 * @return void
		 */
		function updateCheck() {
			if (this.keyCache.update()) {
				this.update();
			}
		}

		/**
		 * highlight an item in the name list
		 *
		 * @param  string position alias
		 * @return void
		 */
		function select(selection) {
			var lastItem = this.nameCache.getItemsLength() - 1;

			switch (selection) {
			case "last":
				this.selected = lastItem;
				break;
			case "next":
				this.selected++;
				if (this.selected > lastItem) {
					this.selected = 0;
				}
				break;
			case "previous":
				this.selected--;
				if (this.selected < 0) {
					this.selected = lastItem;
				}
				break;
			case "nextPage":
				this.selected  += options.maxItems;
				if (this.selected > lastItem) {
					this.selected = lastItem;
				}
				break;
			case "previousPage":
				this.selected  -= options.maxItems;
				if (this.selected < 0) {
					this.selected = 0;
				}
				break;
			default:
				this.selected = 0;
				break;
			}

			this.highlightSelected();
		}

		/**
		 * assign the "on" class to the currently
		 * selected list item
		 *
		 * @param  bool true to highlight without scrolling the item into view
		 * @return void
		 */
		function highlightSelected(noScroll) {
			var $selectedItem = this.$popup.find(".mentionme_popup_item_" + this.selected),
				$lastSelectedItem = this.$popup.find(".mentionme_popup_item_" + this.lastSelected),
				$highlightSpan = $lastSelectedItem.find("span.mention_name_highlight_on"),
				offset = this.itemInView($selectedItem);

			if (this.lastSelected == this.selected ||
				$selectedItem.length == 0) {
				return;
			}

			if ($lastSelectedItem.length) {
				$lastSelectedItem.removeClass("mentionme_popup_item_on");

				if ($highlightSpan.length) {
					$highlightSpan.removeClass("mention_name_highlight_on");
					$highlightSpan.addClass("mention_name_highlight");
				}
			}
			this.lastSelected = this.selected;

			if ($selectedItem) {
				if (!$selectedItem.hasClass("mentionme_popup_item_on")) {
					$selectedItem.addClass("mentionme_popup_item_on");
				}

				$highlightSpan = $selectedItem.find("span.mention_name_highlight");
				if ($highlightSpan.length) {
					$highlightSpan.removeClass("mention_name_highlight");
					$highlightSpan.addClass("mention_name_highlight_on");
				}
			}

			if (noScroll ||
				(options.lockSelection !== 1 &&
				offset === true)) {
				return;
			}

			if (options.lockSelection) {
				if (this.nameCache.getItemsLength() - options.maxItems > 0) {
					this.$body.prop("scrollTop", pi($selectedItem.prop("offsetTop") - this.inputHeight));
				}
				return;
			}

			if (this.selected == 0) {
				this.$body.prop("scrollTop", -this.inputHeight);
				return;
			}

			if (offset > 0) {
				this.$body.prop("scrollTop", pi($selectedItem.prop("offsetTop") - (this.getCurrentHeight() - this.lineHeight) - this.inputHeight));
				return;
			}

			this.$body.prop("scrollTop", pi($selectedItem.prop("offsetTop") - this.inputHeight));
		}

		/**
		 * determines whether an item is in view
		 *
		 * @param  jQuery element
		 * @return boolean|int
		 */
		function itemInView($el) {
			var offset = $el.prop("offsetTop") - this.$body.prop("scrollTop");
			if (offset > 0 &&
				(offset + this.lineHeight) < this.getCurrentHeight()) {
				return true;
			}
			return offset;
		}

		/**
		 * basic navigation for when the popup is open
		 *
		 * @param  event
		 * @return void
		 */
		function onKeyDown(e) {
			switch (e.keyCode) {
			case key.ENTER:
				this.editorInterface.insert();
				break;
			case key.UP:
				this.select("previous");
				break;
			case key.DOWN:
				this.select("next");
				break;
			case key.END:
				this.select("last");
				break;
			case key.HOME:
				this.select();
				break;
			case key.PAGE_UP:
				this.select("previousPage");
				break;
			case key.PAGE_DOWN:
				this.select("nextPage");
				break;
			case key.ESC:
				this.hide();
				break;
			case key.BACKSPACE:
				if (this.$input.val() === "") {
					this.hide();
					this.editorInterface.focus();
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
			if (this.selectEventTarget(e)) {
				this.highlightSelected(true);
			}
		}

		/**
		 * trigger mention insertion on click
		 *
		 * @param  event
		 * @return void
		 */
		function onClick(e) {
			if (this.selectEventTarget(e)) {
				this.editorInterface.insert();
			} else {
				e.preventDefault();
			}
		}

		/**
		 * prevent event bubbling for clicks in input
		 *
		 * @param  event
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

			var $target = $(e.target),
				classes,
				className,
				classNameParts,
				gotClassName = false;

			if ($target.length == 0 ||
				!$target.hasClass("mentionme_popup_item")) {
				return;
			}

			classes = $target.prop("class").split(" ");
			while (className = classes.shift()) {
				if (typeof className !== "undefined" &&
					["mentionme_popup_item", "mentionme_popup_item_on"].indexOf(className) === -1 &&
					className.indexOf("mentionme_popup_item_") === 0) {
					gotClassName = true;
					break;
				}
			}

			classNameParts = className.split("_");
			if (!gotClassName ||
				!classNameParts ||
				classNameParts.length == 0 ||
				!classNameParts[classNameParts.length - 1]) {
				return false;
			}

			// if all is good, select it
			this.selected = classNameParts[classNameParts.length - 1];
			return true;
		}

		/**
		 * return the name of the currently selected item
		 *
		 * @return void
		 */
		function getSelectedName() {
			if (this.nameCache.getItemsLength() === 0 ||
				!this.items[this.selected]) {
				return;
			}

			return this.nameCache.getData()[this.items[this.selected]]["username"];
		}

		/**
		 * approximate height based on initial line measurements
		 *
		 * @return int the height in pixels
		 */
		function getCurrentHeight() {
			return (this.lineHeight * Math.max(1, Math.min(options.maxItems, this.nameCache.getItemsLength()))) + this.editorInterface.heightModifier + 4;
		}

		/**
		 * getter for popup input value
		 *
		 * @return string
		 */
		function getInputValue() {
			return this.$input.val();
		}

		/**
		 * getter for line height
		 *
		 * @return int
		 */
		function getLineHeight() {
			return this.lineHeight;
		}

		/**
		 * getter for spinner visibility
		 *
		 * @return bool true if visible, false if not
		 */
		function spinnerIsVisible() {
			return this.spinnerVisible;
		}

		/**
		 * getter for popup visibility
		 *
		 * @return bool true if visible, false if not
		 */
		function isVisible() {
			return this.visible;
		}

		// extend the prototype
		$.extend(MentionMeAutoCompletePopup.prototype, {
			show: show,
			hide: hide,
			move: move,
			update: update,
			buildItems: buildItems,
			clear: clear,
			showSpinner: showSpinner,
			showInstructions: showInstructions,
			updateCheck: updateCheck,
			select: select,
			highlightSelected: highlightSelected,
			itemInView: itemInView,
			onKeyDown: onKeyDown,
			onMouseMove: onMouseMove,
			onClick: onClick,
			onInputClick: onInputClick,
			selectEventTarget: selectEventTarget,
			getSelectedName: getSelectedName,
			getCurrentHeight: getCurrentHeight,
			getInputValue: getInputValue,
			getLineHeight: getLineHeight,
			spinnerIsVisible: spinnerIsVisible,
			isVisible: isVisible,
		});

		return MentionMeAutoCompletePopup;
	})(),

	/**
	 * this object manages the chars typed since the @ symbol
	 */
	KeyCache = (function() {
		/**
		 * constructor
		 *
		 * @param  MentionMeAutoCompletePopup
		 * @return void
		 */
		function MentionMeKeyCache(popup) {
			this.popup = popup;
			this.clear();
		}

		/**
		 * reset the key cache
		 *
		 * @return void
		 */
		function clear() {
			this.data = "";
			this.mirror = "";
		}

		/**
		 * get change state
		 *
		 * @return bool true if changed
		 */
		function update() {
			var ret = false,
				inputVal = this.popup.getInputValue();
			if (this.data !== inputVal) {
				ret = true;
			}

			this.data = inputVal;
			return ret;
		}

		/**
		 * getter for data length
		 *
		 * @return int
		 */
		function getLength() {
			return this.data.length;
		}

		/**
		 * getter for data
		 *
		 * @param  bool false forces lowercase
		 * @return string
		 */
		function getText(natural) {
			if (natural != true) {
				return this.data.toLowerCase();
			}
			return this.data;
		}

		// extend the prototype
		$.extend(MentionMeKeyCache.prototype, {
			clear: clear,
			update: update,
			getLength: getLength,
			getText: getText,
		});

		return MentionMeKeyCache;
	})(),

	/**
	 * the user name cache object
	 */
	NameCache = (function() {
		/**
		 * constructor
		 *
		 * @param  MentionMeAutoCompletePopup
		 * @return void
		 */
		function MentionMeNameCache(popup) {
			this.data = {};
			this.threadNames = {};
			this.allNames = {};
			this.ready = false;
			this.loading = true;
			this.searching = false;
			this.searched = [];
			this.items = [];
			this.longestName = 5;
			this.popup = popup;
			this.editorInterface = this.popup.editorInterface;
			this.keyCache = popup.keyCache;

			$.ajax({
				type: "post",
				url: "xmlhttp.php",
				data: {
					action: "mentionme",
					mode: "getNameCache",
					tid: options.tid,
				},
				success: this.loadNameCache.bind(this),
			});
		}

		/**
		 * deal with the server response and store the data
		 *
		 * @param  object XMLHTTP response JSON
		 * @return void
		 */
		function loadNameCache(response) {
			this.ready = true;
			this.loading = false;
			this.threadNames = response.inThread;
			this.allNames = response.cached;

			$.extend(this.data, this.threadNames, this.allNames);

			if ($.isEmptyObject(this.data)) {
				this.data = {};
				this.popup.showInstructions();
				// resize the popup
				if (this.popup.isVisible()) {
					this.popup.move();
				}
				return;
			}

			if (this.popup.isVisible()) {
				this.popup.update();
			}
		}

		/**
		 * list names that match the keyCache (currently typed string)
		 *
		 * @return int total items matched
		 */
		function match() {
			var property,
				i = 0,
				done = {},
				allItems = [];

			this.items = [];
			this.longestName = 5;

			// thread participants
			for (property in this.threadNames) {
				if (!this.checkEntry(property, this.threadNames, done)) {
					continue;
				}

				this.items.push(property);
				done[property] = true;
				i++;
			}

			// standard name cache
			for (property in this.data) {
				if (!this.checkEntry(property, this.data, done)) {
					continue;
				}

				allItems.push(property);
				done[property] = true;
				i++;
			}
			allItems = allItems.sort(sortByLength);

			$.merge(this.items, allItems);
			return i;
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
				(this.keyCache.getLength() &&
				((!options.fullText &&
				property.slice(0, this.keyCache.getLength()) !== this.keyCache.getText()) ||
				(options.fullText &&
				property.indexOf(this.keyCache.getText()) === -1)))) {
				return false;
			}

			if (property.length > this.longestName) {
				this.longestName = property.length;
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
			var search = this.keyCache.getText().slice(0, options.minLength);

			/**
			 * if we're already searching or we've
			 * already searched this minimum-length
			 * name prefix, there is nothing to do
			 */
			if (this.searching ||
				this.searched.indexOf(search) !== -1) {
				// if the spinner is up then we found nothing
				if (this.popup.spinnerIsVisible()) {
					// so get out
					this.popup.hide();
					this.editorInterface.focus();
				}
				return;
			}

			// store this search so we don't repeat
			this.searched.push(search);
			this.searching = true;

			if (this.items.length === 0) {
				this.popup.showSpinner();
			}

			$.ajax({
				type: "post",
				url: "xmlhttp.php",
				data: {
					action: "mentionme",
					mode: "nameSearch",
					search: search,
				},
				success: load.bind(this),
			});
		}

		/**
		 * handle the response solicited by search()
		 *
		 * @param  object XMLHTTP response JSON
		 * @return void
		 */
		function load(names) {
			var n = 0, property;

			this.searching = false;

			// if we have nothing
			if (!names) {
				// . . . and we had nothing before we searched . . .
				if (this.popup.spinnerIsVisible()) {
					// get out
					this.popup.hide();
					this.editorInterface.focus();
				}
				return;
			}

			// add all the found names to the cache (will overwrite, not duplicate)
			for (property in names) {
				if (!names.hasOwnProperty(property) ||
					this.data[property]) {
					continue;
				}

				this.data[property] = names[property];
				n++;
			}

			if (!n ||
				!this.popup.isVisible()) {
				return;
			}

			// reset everything and rebuild the list
			this.match();
			this.popup.buildItems();
			this.popup.select();
		}

		/**
		 * getter for ready state
		 *
		 * @return bool true if cache loaded
		 */
		function isReady() {
			return this.ready;
		}

		/**
		 * getter for loading state
		 *
		 * @return bool true if cache loaded
		 */
		function isLoading() {
			return this.loading;
		}

		/**
		 * getter for user data
		 *
		 * @return object
		 */
		function getData() {
			return this.data;
		}

		/**
		 * getter for the item list
		 *
		 * @return array
		 */
		function getItems() {
			return this.items;
		}

		/**
		 * getter for items length
		 *
		 * @return int
		 */
		function getItemsLength() {
			return this.items.length;
		}

		/**
		 * getter for longest name length
		 *
		 * @return int
		 */
		function getLongestName() {
			return this.longestName;
		}

		// extend the prototype
		$.extend(MentionMeNameCache.prototype, {
			loadNameCache: loadNameCache,
			match: match,
			checkEntry: checkEntry,
			search: search,
			load: load,
			isReady: isReady,
			isLoading: isLoading,
			getData: getData,
			getItems: getItems,
			getItemsLength: getItemsLength,
			getLongestName: getLongestName,
		});

		return MentionMeNameCache;
	})(),

	/**
	 * interface for textarea element
	 */
	TextareaInterface = (function() {
		/**
		 * constructor
		 *
		 * @param  string
		 * @return void
		 */
		function AutoCompleteTextareaInterface(textareaId) {
			this.$textarea = $("#" + textareaId);
			this.$container = this.$textarea.closest("div");

			this.selection = {
				start: 0,
				end: 0,
			};

			// go ahead and build the popup
			this.popup = new Popup(this);

			// poll for the @ char
			this.bindKeyup();
		}

		/**
		 * polling for the @ character when uninitiated
		 *
		 * @param  event
		 * @return void
		 */
		function onKeyUp(e) {
			if (this.popup.isVisible()) {
				return;
			}

			// open the popup when user types an @
			this.getCaret();
			if (checkKeyCode(e.keyCode) &&
				this.$textarea.val().slice(this.selection.start - 1, this.selection.end) == "@") {
				this.showPopup();
			}
		}

		/**
		 * position and display the popup
		 *
		 * @return void
		 */
		function showPopup() {
			var coords = this.$textarea.caret("offset"),
				left = coords.left + 3,
				top = coords.top - 5;

			this.popup.show(left, top);
		}

		/**
		 * insert the mention and get out
		 *
		 * @return void
		 */
		function insertMention() {
			var mention = prepMention(this.popup);

			if (!mention) {
				if (!this.popup.spinnerIsVisible()) {
					this.popup.hide();
				}
				return;
			}

			this.getCaret();

			this.$textarea.val(this.$textarea.val().slice(0, this.selection.start) +
				mention +
				this.$textarea.val().slice(this.selection.start));
			this.setCaret(this.selection.start + mention.length);

			// and we're done here (for now)
			this.popup.hide();
		}

		/**
		 * store info about the caret/selection
		 *
		 * @return void
		 */
		function getCaret() {
			var range = this.$textarea.caret("pos");

			this.selection.start = range;
			this.selection.end = range;
		}

		/**
		 * position the caret
		 *
		 * @param  int
		 * @return void
		 */
		function setCaret(position) {
			var temp = this.$textarea[0],
				range;

			if (temp.setSelectionRange) {
				temp.focus();
				temp.setSelectionRange(position, position);
			} else if (temp.createTextRange) {
				range = temp.createTextRange();
				range.collapse(true);
				range.moveEnd("character", position);
				range.moveStart("character", position);
				range.select();
			}
		}

		/**
		 * API for popup to attach event listener
		 *
		 * @return void
		 */
		function bindClick(f) {
			this.$textarea.click(f);
		}

		/**
		 * API for popup to detach event listener
		 *
		 * @return void
		 */
		function unbindClick(f) {
			this.$textarea.off("click", f);
		}

		/**
		 * API for popup to attach event listener
		 *
		 * @return void
		 */
		function bindKeyup() {
			this.$textarea.keyup($.proxy(this.onKeyUp,this));
		}

		/**
		 * API for popup to detach event listener
		 *
		 * @return void
		 */
		function unbindKeyup() {
			this.$textarea.off("keyup");
		}

		/**
		 * API for popup to focus editor
		 *
		 * @return void
		 */
		function focus() {
			this.$textarea.focus();
		}

		/**
		 * getter for the container element
		 *
		 * @return string|object
		 */
		function getContainer() {
			return this.$container;
		}

		// extend the prototype
		$.extend(AutoCompleteTextareaInterface.prototype, {
			heightModifier: 0,
			lineHeightModifier: 0,
			onKeyUp: onKeyUp,
			showPopup: showPopup,
			insert: insertMention,
			getCaret: getCaret,
			setCaret: setCaret,
			bindClick: bindClick,
			unbindClick: unbindClick,
			bindKeyup: bindKeyup,
			unbindKeyup: unbindKeyup,
			focus: focus,
			getContainer: getContainer,
		});

		return AutoCompleteTextareaInterface;
	})(),

	/**
	 * interface for SCEditor
	 */
	SCEditorInterface = (function() {
		/**
		 * constructor
		 *
		 * @return void
		 */
		function AutoCompleteSCEditorInterface() {
			this.editor = MyBBEditor;
			this.rangeHelper = this.editor.getRangeHelper();

			this.$iFrame = $("iframe");
			this.$container = this.$iFrame.closest("td");
			this.$body = this.editor.getBody();

			this.selection = {
				start: 0,
				end: 0,
			};

			// go ahead and build the popup
			this.popup = new Popup(this);

			this.editor.keyUp(this.onKeyUp.bind(this));
		}

		/**
		 * polling for the @ character when uninitiated and
		 * some navigation and editing for our key cache
		 *
		 * @param  event
		 * @return void
		 */
		function onKeyUp(e) {
			this.getCaret();

			if (!e.keyCode) {
				if (e.originalEvent &&
					e.originalEvent.keyCode) {
					e.keyCode = e.originalEvent.keyCode;
				} else {
					return;
				}
			}

			// open the popup when user types an @
			if (!this.popup.isVisible()) {
				if (checkKeyCode(e.keyCode) &&
					this.$currentNode.text().slice(this.selection.start - 1, this.selection.end) == "@") {
					this.showPopup();
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
			var fontSize, left, top,
				coords = this.$body.caret("offset", {
					iframe: this.$iFrame[0],
				}),
				containerOffset = this.$container.offset();

			fontSize = 7;
			if (this.$currentNode.closest("div").length &&
				typeof this.$currentNode.closest("div").css === "function") {
				fontSize = pi(this.$currentNode.closest("div").css("fontSize").replace("px", "") / 2);
			}

			left = pi(coords.left) + containerOffset.left + pi(this.$container.css("paddingLeft").replace("px", "")) + fontSize + 2;
			top = pi(coords.top + this.$container.find("div.sceditor-toolbar").height()) + containerOffset.top + pi(this.$container.css("paddingTop").replace("px", "")) + 3;

			this.popup.show(left, top);
		}

		/**
		 * insert the mention and get out
		 *
		 * @return void
		 */
		function insertMention() {
			var mention = prepMention(this.popup);

			if (!mention) {
				if (!this.popup.spinnerIsVisible()) {
					this.popup.hide();
				}
				return;
			}

			this.editor.insert(mention);

			// and we're done here (for now)
			this.popup.hide();
		}

		/**
		 * store info about the caret/selection
		 *
		 * @return void
		 */
		function getCaret() {
			var range = this.rangeHelper.selectedRange();

			if (range.startContainer) {
				this.$currentNode = $(range.startContainer);
			} else {
				this.$currentNode = $(editor.currentNode());
			}

			this.selection.start = range.startOffset;
			this.selection.end = range.endOffset;
		}

		/**
		 * API for popup to attach event listener
		 *
		 * @return void
		 */
		function bindClick(f) {
			this.$body.click(f);
		}

		/**
		 * API for popup to detach event listener
		 *
		 * @return void
		 */
		function unbindClick(f) {
			this.$body.off("click", f);
		}

		/**
		 * API for popup to focus editor
		 *
		 * @return void
		 */
		function focus() {
			this.$iFrame.focus();
		}

		/**
		 * getter for the container element
		 *
		 * @return string|object
		 */
		function getContainer() {
			return this.$container;
		}

		// extend the prototype
		$.extend(AutoCompleteSCEditorInterface.prototype, {
			heightModifier: 0,
			lineHeightModifier: 0,
			onKeyUp: onKeyUp,
			showPopup: showPopup,
			insert: insertMention,
			getCaret: getCaret,
			bindClick: bindClick,
			unbindClick: unbindClick,
			focus: focus,
			getContainer: getContainer,
		});

		return AutoCompleteSCEditorInterface;
	})(),

	/**
	 * interface for CKEditor
	 */
	CKEditorInterface = (function() {
		/**
		 * constructor
		 *
		 * @param  string
		 * @return void
		 */
		function AutoCompleteCKEditorInterface(textareaId) {
			if ($("#" + textareaId).length === 0 ||
				typeof CKEDITOR.instances[textareaId] === "undefined") {
				return;
			}

			this.id = textareaId;
			this.editor = CKEDITOR.instances[this.id];

			if (textareaId === "message" ||
				textareaId === "signature") {
				this.editor.on("instanceReady", $.proxy(this.finalize, this));
			} else {
				this.finalize();
			}

			$("#quick_reply_submit").click($.proxy(this.quickReplyPosted, this));
		}

		/**
		 * when CKEditor is ready, finish up initialization
		 *
		 * @return void
		 */
		function finalize() {
			this.$iFrame = $("#cke_" + this.id).find("iframe");
			this.$container = this.$iFrame.closest("div");
			this.$doc = $(this.editor.document.$);
			this.$body = this.$doc.find("body");

			this.bindKeyup();

			// go ahead and build the popup
			this.popup = new Popup(this);
		}

		/**
		 * polling for the @ character when uninitiated and
		 * some navigation and editing for our key cache
		 *
		 * @param  event
		 * @return void
		 */
		function onKeyUp(e) {
			// open the popup when user types an @
			if (!this.popup.isVisible()) {
				if (checkKeyCode(e.keyCode) &&
					this.getPrevChar() == "@") {
					this.showPopup();
				}
				return;
			}
		}

		/**
		 * reinstate observation on AJAX post
		 *
		 * @return void
		 */
		function quickReplyPosted() {
			if (typeof this.$doc !== "undefined" &&
				this.$doc.length) {
				this.$doc.off("keyup", this.onKeyUp);
			}

			setTimeout($.proxy(function() {
				this.$doc.keyup($.proxy(this.onKeyUp, this));
			}, this), 500);
		}

		/**
		 * position and display the popup
		 *
		 * @return void
		 */
		function showPopup() {
			var coords = this.$body.caret("offset", {
					iframe: this.$iFrame[0],
				}),
				iFrameOffset = this.$iFrame.offset(),
				left = pi(coords.left + iFrameOffset.left) + 2,
				top = pi(coords.top + iFrameOffset.top) - 5;

			this.popup.show(left, top);
		}

		/**
		 * insert the mention and get out
		 *
		 * @return void
		 */
		function insertMention() {
			var mention = prepMention(this.popup);

			if (!mention) {
				if (!this.popup.spinnerIsVisible()) {
					this.popup.hide();
				}
				return;
			}

			this.editor.insertText(mention);

			// and we're done here (for now)
			this.popup.hide();
		}

		/**
		 * get the character just before the cursor
		 * credit:
		 * http://stackoverflow.com/questions/20972431/ckeditor-get-previous-character-of-current-cursor-position
		 *
		 * @return mixed
		 */
		function getPrevChar() {
			var startNode, walker, node,
				range = this.editor.getSelection().getRanges()[0];

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
				range.setStartAt(this.editor.editable(), CKEDITOR.POSITION_AFTER_START);

				// use the walker to find the closest previous text node.
				walker = new CKEDITOR.dom.walker(range);

				while (node = walker.previous()) {
					// If found, return the last character of the text node.
					if (node.type == CKEDITOR.NODE_TEXT) {
						return node.getText().slice(-1);
					}
				}
			}

			// Selection starts at the 0 index of the text node and/or there's no previous text node in contents.
			return null;
		}

		/**
		 * API for popup to attach event listener
		 *
		 * @param  function
		 * @return void
		 */
		function bindClick(f) {
			this.$doc.click(f);
		}

		/**
		 * API for popup to detach event listener
		 *
		 * @param  function
		 * @return void
		 */
		function unbindClick(f) {
			this.$doc.off("click", f);
		}

		/**
		 * API for popup to attach event listener
		 *
		 * @return void
		 */
		function bindKeyup() {
			this.$doc.keyup($.proxy(this.onKeyUp, this));
		}

		/**
		 * API for popup to detach event listener
		 *
		 * @return void
		 */
		function unbindKeyup() {
			this.$doc.off("keyup", this.onKeyUp);
		}

		/**
		 * API for popup to focus editor
		 *
		 * @return void
		 */
		function focus() {
			this.editor.focus();
		}

		/**
		 * getter for the container element
		 *
		 * @return string|object
		 */
		function getContainer() {
			return this.$container;
		}

		// extend the prototype
		$.extend(AutoCompleteCKEditorInterface.prototype, {
			heightModifier: 0,
			lineHeightModifier: 0,
			finalize: finalize,
			onKeyUp: onKeyUp,
			quickReplyPosted: quickReplyPosted,
			showPopup: showPopup,
			insert: insertMention,
			getPrevChar: getPrevChar,
			bindClick: bindClick,
			unbindClick: unbindClick,
			bindKeyup: bindKeyup,
			unbindKeyup: unbindKeyup,
			focus: focus,
			getContainer: getContainer,
		});

		return AutoCompleteCKEditorInterface;
	})();

	/**
	 * load options and language (used externally)
	 *
	 * @param  object
	 * @return void
	 */
	function setup(opt) {
		$.extend(lang, opt.lang || {});
		delete opt.lang;
		$.extend(options, opt || {});

		$(["minLength", "maxLength", "maxItems", "fullText", "showAvatars", "lockSelection"]).each(function() {
			options[this] = pi(options[this]);
		});

		options.defaultAvatar = options.imageDirectory + "/default_avatar.png";
	}

	/**
	 * prepare to auto-complete
	 *
	 * @return void
	 */
	function init() {
		var id, key;

		if (typeof CKEDITOR !== "undefined" &&
			typeof CKEDITOR.instances !== "undefined") {
			key = $.map(CKEDITOR.instances, function(i, k) { return k })[0];

			if (typeof CKEDITOR.instances[key] !== "object") {
				return false;
			}
			new CKEditorInterface(key);
		} else if (MyBBEditor !== null &&
			typeof MyBBEditor === "object" &&
			MyBBEditor.getRangeHelper &&
			typeof MyBBEditor.getRangeHelper === "function") {
			new SCEditorInterface();
		} else if ($("#message").length > 0 ||
			$("#signature").length > 0) {
			// almost every page uses this id
			if ($("#message").length) {
				id = "message";
			// usercp.php and modcp.php use this id
			} else if ($("#signature").length) {
				id = "signature";
			}

			// if no suitable text area is present, get out
			if (!id ||
				!$("#" + id).length) {
				return false;
			}

			new TextareaInterface(id);
		}

		// quick edit
		$(".quick_edit_button").click(doQuickEdit);
		$("#quick_reply_submit").click(doQuickReply);
	}

	/**
	 * create a new instance when quick edit is invoked
	 *
	 * @param  event
	 * @return void
	 */
	function doQuickEdit(e) {
		var pid = this.id.split("_").slice(-1)[0],
			id = "quickedit_" + pid,
			i;

		if ($("#" + id).length == 0) {
			return;
		}

		if (typeof CKEDITOR === "undefined") {
			i = new TextareaInterface(id);
		} else {
			setTimeout(function() {
				i = new CKEditorInterface(id);
			}, 1100);
		}

		setTimeout(function() {
			$("#quicksub_" + pid)
				.add($("#quicksub_" + pid).next("button"))
				.click(function() {
				i.unbindKeyup();
			});
		}, 1100);
	}

	/**
	 * attach event listeners after a new AJAX post
	 *
	 * @param  event
	 * @return void
	 */
	function doQuickReply(e) {
		$(".quick_edit_button").off("click", doQuickEdit);
		setTimeout(function() {
			$(".quick_edit_button").click(doQuickEdit);
		}, 500);
	}

	/**
	 * quote a name and return it
	 *
	 * @return string
	 */
	function prepMention(popup) {
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
			" ";
	}

	/**
	 * check key code against a bad list
	 *
	 * @param  int
	 * @return bool
	 */
	function checkKeyCode(keyCode) {
		return [key.LEFT, key.RIGHT, key.UP, key.DOWN, key.BACKSPACE, key.ESC, key.SHIFT, key.CTRL, key.ALT, key.ENTER, key.DELETE, key.INSERT, key.END, key.NUMLOCK].indexOf(keyCode) === -1;
	}

	/**
	 * sort strings by length
	 *
	 * @param  string
	 * @param  string
	 * @return int
	 */
	function sortByLength(a, b) {
		if (a.length < b.length) {
			return -1;
		} else if (a.length > b.length) {
			return 1;
		} else {
			return 0;
		}
	}

	/**
	 * alias for parseInt
	 *
	 * @param  number
	 * @return int
	 */
	function pi(i) {
		return parseInt(i, 10);
	}

	$(init);

	m.autoComplete = {
		setup: setup,
	};

	return m;
})(jQuery, MentionMe || {});
