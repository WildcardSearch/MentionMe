/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains a class for the multi-mention functionality in mention.php
 */

var MentionMe = {
	init: function()
	{
		var mentioned = Cookie.get("multi_mention");
		if(mentioned)
		{
			var post_ids = mentioned.split("|");
			post_ids.each(function(post_id) {
				if($("multi_mention_"+post_id))
				{
					element = $("multi_mention_"+post_id);
					element.src = element.src.replace("postbit_multi_mention.gif", "postbit_multi_mention_on.gif");
				}
			});
			if($('quickreply_multi_mention'))
			{
				$('quickreply_multi_mention').show();
			}
		}
		return true;
	},

	multiMention: function(pid)
	{
		var new_post_ids = new Array();
		var mentioned = Cookie.get("multi_mention");
		var is_new = true;
		if(mentioned)
		{
			var post_ids = mentioned.split("|");
			post_ids.each(function(post_id) {
				if(post_id != pid && post_id != '')
				{
					new_post_ids[new_post_ids.length] = post_id;
				}
				else if(post_id == pid)
				{
					is_new = false;
				}
			});
		}
		element = $("multi_mention_"+pid);
		if(is_new == true)
		{
			element.src = element.src.replace("postbit_multi_mention.gif", "postbit_multi_mention_on.gif");
			new_post_ids[new_post_ids.length] = pid;
		}
		else
		{
			element.src = element.src.replace("postbit_multi_mention_on.gif", "postbit_multi_mention.gif");
		}
		if($('quickreply_multi_mention'))
		{
			if(new_post_ids.length > 0)
			{
				$('quickreply_multi_mention').show();
			}
			else
			{
				$('quickreply_multi_mention').hide();
			}
		}
		Cookie.set("multi_mention", new_post_ids.join("|"));
	},

	loadMultiMentioned: function()
	{
		if(use_xmlhttprequest == 1)
		{
			this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
			new Ajax.Request('xmlhttp.php?action=get_multi_mentioned&load_all=1', {method: 'get', onComplete: function(request) {MentionMe.multiMentionedLoaded(request); }});
			return false;
		}
		else
		{
			return true;
		}
	},

	multiMentionedLoaded: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);
			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}
			if(this.spinner)
			{
				this.spinner.destroy();
				this.spinner = '';
			}
			alert('There was an error fetching the posts.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			var id = 'message';
			if(typeof clickableEditor != 'undefined')
			{
				id = clickableEditor.textarea;
			}
			if($(id).value)
			{
				$(id).value += "\n";
			}
			$(id).value += request.responseText;
		}
		MentionMe.clearMultiMentioned();
		$('quickreply_multi_mention').hide();
		$('mentioned_ids').value = 'all';
		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
		}
		$(id).focus();
	},

	clearMultiMentioned: function()
	{
		$('quickreply_multi_mention').hide();
		var mentioned = Cookie.get("multi_mention");
		if(mentioned)
		{
			var post_ids = mentioned.split("|");
			post_ids.each(function(post_id) {
				if($("multi_mention_"+post_id))
				{
					element = $("multi_mention_"+post_id);
					element.src = element.src.replace("postbit_multi_mention_on.gif", "postbit_multi_mention.gif");
				}
			});
		}
		Cookie.unset('multi_mention');
	}
};
Event.observe(document, 'dom:loaded', MentionMe.init);