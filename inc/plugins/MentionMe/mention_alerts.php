<?php
/**
 * This file is part of MentionMe and provide MyAlerts routines for mention.php
 *
 * Copyright © 2013 Wildcard
 * http://www.rantcentralforums.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses
 */
 
// Disallow direct access to this file for security reasons.
if(!defined('IN_MYBB') || !defined('IN_MENTIONME'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

define('MYALERTS_PLUGIN_PATH', MYBB_ROOT . 'inc/plugins/MyAlerts/');

$plugins->add_hook("datahandler_post_update", "myalerts_alert_mentioned_editpost");

/* myalerts_alert_mentioned_editpost()
 *
 * create alerts when users edit a post and add a new mention. try to avoid sending any duplicate alerts.
 *
 * @param - $this_post is an object containing post info
 */
function myalerts_alert_mentioned_editpost($this_post)
{
	global $db, $mybb, $Alerts;
	
	// Is the alerts class present?
	require_once MYALERTS_PLUGIN_PATH . 'Alerts.class.php';
	try
	{
		$Alerts = new Alerts($mybb, $db);
	}
	catch (Exception $e)
	{
		die($e->getMessage());
	}
	
	// grab the post data
	$message = $this_post->data['message'];
	$tid = (int) $this_post->data['tid'];
	$pid = (int) $this_post->data['pid'];
	$edit_uid = (int) $mybb->user['uid'];
	
	// get all mentions
	$match = array();
	mention_find_in_post($message, $match);
	
	// no results, no alerts
	if(!is_array($match))
	{
		return;
	}
	
	// avoid duplicate mention alerts
	$mentioned_already = array();
	
	// loop through all matches (if any)
	foreach($match as $val)
	{
		// if there are matches, create alerts
		if($val[0])
		{
			$uid = (int) $val[1];
			
			// create an alert if MyAlerts and mention alerts are enabled and prevent multiple alerts for duplicate mentions in the post and the user mentioning themselves.
			if (!$mentioned_already[$uid] && $edit_uid != $uid )
			{
				// make sure if the user was already alerted for being mentioned in this post that a duplicate mention isn't created
				$mentioned_already[$uid] = true;
				$already_alerted = false;
				
				// check that we haven't already sent an alert for this mention
				$query = $db->simple_select('alerts', '*', "uid='$uid' AND from_id='$edit_uid' AND tid='$tid' AND alert_type='mention'");
				if($db->num_rows($query) > 0)
				{
					while($this_alert = $db->fetch_array($query) && !$already_alerted)
					{
						 $this_alert['content'] = json_decode($this_alert['content'], true);
						 
						 // if an alert exists for this specific post then we have already alerted the user
						 if($this_alert['content']['pid'] == $pid)
						 {
							$already_alerted = true;
						 }
					}
				}
				
				// no duplicates
				if(!$already_alerted)
				{
					$Alerts->addAlert((int) $uid, 'mention', (int) $tid, (int) $edit_uid, array('pid' => $pid));
				}
			}
		}
	}
}

$plugins->add_hook('newreply_do_newreply_end', 'myalerts_alert_mentioned');
$plugins->add_hook('newthread_do_newthread_end', 'myalerts_alert_mentioned');

/* myalerts_alert_mentioned()
 *
 * check posts for mentions when they are initially created and create alerts accordingly
 */
function myalerts_alert_mentioned()
{
	global $mybb, $Alerts, $pid, $tid, $post, $thread;

	// If creating a new thread the message comes from POST
	if($mybb->input['action'] == "do_newthread" && $mybb->request_method == "post")
	{
		$message = $mybb->input['message'];
	}
	// otherwise the message comes from $post['message'] and the $tid comes from $thread['tid']
	else
	{	
		$message = $post['message'];
		$tid = (int) $thread['tid'];
	}

	// get all mentions
	$match = array();
	mention_find_in_post($message, $match);
	
	// no matches, no alerts
	if(!is_array($match))
	{
		return;
	}
	
	// avoid duplicate mention alerts
	$mentioned_already = array();
	
	// loop through all matches (if any)
	foreach($match as $val)
	{
		// if there are matches, create alerts
		if($val[0])
		{
			$uid = (int) $val[1];
			
			// create an alert if MyAlerts and mention alerts are enabled and prevent multiple alerts for duplicate mentions in the post and the user mentioning themselves.
			if ($mybb->user['uid'] != $uid && !$mentioned_already[$uid])
			{
				$mentioned_already[$uid] = true;
				$Alerts->addAlert((int) $uid, 'mention', $tid, (int) $mybb->user['uid'], array('pid' => (int) $pid)); 
			}
		}
	}	
}

$plugins->add_hook('myalerts_alerts_output_start', 'mention_alerts_output');

/* mention_alerts_output()
 *
 * Hook into MyAlerts to display alerts in the drop-down and in User CP
 *
 * @param - $alert by value is a valid Alert class object
 */
function mention_alerts_output(&$alert)
{
	global $mybb, $lang;

	if (!$lang->mention)
	{
		$lang->load('mention');
	}
	
	// If this is a mention alert and the user allows this type of alert then process and display it
	if ($alert['alert_type'] == 'mention' AND $mybb->user['myalerts_settings']['mention'])
	{
		// If this is a reply then a pid will be present,
		if($alert['content']['pid'])
		{
			$alert['postLink'] = $mybb->settings['bburl'] . '/' . get_post_link($alert['content']['pid'], $alert['tid']) . '#pid' . $alert['content']['pid'];
			$alert['message'] = $lang->sprintf($lang->myalerts_mention, $alert['user'], $alert['postLink'], $alert['dateline']);
		}
		else
		{
			// otherwise, just link to the new thread.
			$alert['threadLink'] = get_thread_link($alert['tid']);
			$alert['message'] = $lang->sprintf($lang->myalerts_mention, $alert['user'], $alert['threadLink'], $alert['dateline']);
		}
		
		$alert['rowType'] = 'mentionAlert';
	}
}

$plugins->add_hook('myalerts_possible_settings', 'mention_alerts_setting');

/* mention_alerts_setting()
 *
 * Add the setting in User CP
 *
 * @param - &$possible_settings is an array of UCP settings
 */
function mention_alerts_setting(&$possible_settings)
{
	global $lang;
	
	$possible_settings[] = 'mention';
	
	if (!$lang->mention)
	{
		$lang->load('mention');
	}
}

$plugins->add_hook('misc_help_helpdoc_start', 'mention_myalerts_helpdoc');

/* mention_myalerts_helpdoc()
 *
 * adds documentation for mention alerts
 */
function mention_myalerts_helpdoc()
{
	global $helpdoc, $lang, $mybb;

	if (!$lang->myalerts)
	{
		$lang->load('myalerts');
	}
	if (!$lang->mention)
	{
		$lang->load('mention');
	}

	if ($helpdoc['name'] == $lang->myalerts_help_alert_types)
	{
		if ($mybb->settings['myalerts_alert_mention']) 
		{
			$helpdoc['document'] .= $lang->myalerts_help_alert_types_mentioned;
		}
	}
}

/* mention_strip_quotes()
 *
 * strips all quotes and their content from the post
 *
 * @param - $message is the content of the post
 */
function mention_strip_quotes($message)
{
	global $lang, $templates, $theme, $mybb;

	// Assign pattern and replace values.
	$pattern = array(
		"#\[quote=([\"']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#esi",
		"#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si"
	);

	$replace = array(
		"",
		""
	);

	do
	{
		$previous_message = $message;
		$message = preg_replace($pattern, $replace, $message, -1, $count);
	} while($count);

	if(!$message)
	{
		$message = $previous_message;
	}

	$find = array(
		"#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#",
		"#(\r\n*|\n*)<\/blockquote>#"
	);

	$replace = array(
		"",
		""
	);
	$message = preg_replace($find, $replace, $message);
	
	return $message;
}

/* mention_find_in_post()
 *
 * find all mentions in the current post that are not within quote tags
 *
 * @param - $message
 * @param - &$match
 */
function mention_find_in_post($message, &$match)
{
	global $mybb;
	
	// Do the replacement in the message
	$message = mention_strip_quotes($message);
	
	$message =  preg_replace_callback('/@"([^<]+?)"|@([\w .]{' . (int) $mybb->settings['minnamelength'] . ',' . (int) $mybb->settings['maxnamelength'] . '})/', "Mention__filter", $message);

	// Then match all the mentions in this post
	$pattern = '#@<a id="mention_(.*?)"#is';
	preg_match_all($pattern, $message, $match, PREG_SET_ORDER);
}

?>
