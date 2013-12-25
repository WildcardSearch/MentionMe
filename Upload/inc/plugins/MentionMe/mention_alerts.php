<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2013 WildcardSearch
 * http://www.wildcardsworld.com
 *
 * this script provides MyAlerts routines for mention.php
 */

// Disallow direct access to this file for security reasons.
if(!defined('IN_MYBB') || !defined('IN_MENTIONME'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if(!defined('MYALERTS_PLUGIN_PATH'))
{
	define('MYALERTS_PLUGIN_PATH', MYBB_ROOT . 'inc/plugins/MyAlerts/');
}

/* mention_myalerts_datahandler_post_update()
 *
 * create alerts when users edit a post and add a new mention. try to avoid sending any duplicate alerts.
 *
 * @param - $this_post is an object containing post info
 */
$plugins->add_hook("datahandler_post_update", "mention_myalerts_datahandler_post_update");
function mention_myalerts_datahandler_post_update($this_post)
{
	global $db, $mybb, $Alerts, $post;

	// Is the alerts class present?
	require_once MYALERTS_PLUGIN_PATH . 'Alerts.class.php';
	try
	{
		$Alerts = new Alerts($mybb, $db);
	}
	catch(Exception $e)
	{
		die($e->getMessage());
	}

	// grab the post data
	$message = $this_post->data['message'];
	$tid = (int) $this_post->data['tid'];
	$pid = (int) $this_post->data['pid'];
	$edit_uid = (int) $mybb->user['uid'];
	$subject = $post['subject'];

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

			// create an alert preventing multiple alerts for duplicate mentions in the post and the user mentioning themselves.
			if(!$mentioned_already[$uid] && $edit_uid != $uid )
			{
				// make sure if the user was already alerted for being mentioned in this post that a duplicate mention isn't created
				$mentioned_already[$uid] = true;
				$already_alerted = false;

				// check that we haven't already sent an alert for this mention
				$query = $db->simple_select('alerts', '*', "uid='$uid' AND from_id='$edit_uid' AND tid='$tid' AND alert_type='mention'");
				if($db->num_rows($query) > 0)
				{
					while($this_alert = $db->fetch_array($query))
					{
						if($this_alert['content'])
						{
							$this_alert['content'] = json_decode($this_alert['content'], true);

							// if an alert exists for this specific post then we have already alerted the user
							if($this_alert['content']['pid'] == $pid)
							{
								$already_alerted = true;
								break;
							}
						}
					}
				}

				// no duplicates
				if(!$already_alerted)
				{
					$Alerts->addAlert((int) $uid, 'mention', (int) $tid, (int) $edit_uid, array('pid' => $pid, 'subject' => $subject));
				}
			}
		}
	}
}

/* mention_myalerts_do_newreply_end()
 *
 * check posts for mentions when they are initially created and create alerts accordingly
 */
$plugins->add_hook('newreply_do_newreply_end', 'mention_myalerts_do_newreply_end');
$plugins->add_hook('newthread_do_newthread_end', 'mention_myalerts_do_newreply_end');
function mention_myalerts_do_newreply_end()
{
	global $mybb, $Alerts, $pid, $tid, $post, $thread;

	// If creating a new thread the message comes from POST
	if($mybb->input['action'] == "do_newthread" && $mybb->request_method == "post")
	{
		$message = $mybb->input['message'];
		$subject = $mybb->input['subject'];
	}
	// otherwise the message comes from $post['message'] and the $tid comes from $thread['tid']
	else
	{
		$message = $post['message'];
		$tid = (int) $thread['tid'];
		$subject = $thread['subject'];
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
			if($mybb->user['uid'] != $uid && !$mentioned_already[$uid])
			{
				$mentioned_already[$uid] = true;
				$Alerts->addAlert((int) $uid, 'mention', $tid, (int) $mybb->user['uid'], array('pid' => (int) $pid, 'subject' => $subject));
			}
		}
	}
}

/* mention_myalerts_output_start()
 *
 * hook into MyAlerts to display alerts in the drop-down and in User CP
 *
 * @param - $alert by value is a valid Alert class object
 */
$plugins->add_hook('myalerts_alerts_output_start', 'mention_myalerts_output_start');
function mention_myalerts_output_start(&$alert)
{
	global $mybb, $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	// if this is a mention alert and the user allows this type of alert then process and display it
	if($alert['alert_type'] == 'mention' && $mybb->user['myalerts_settings']['mention'])
	{
		// If this is a reply then a pid will be present,
		if($alert['content']['pid'])
		{
			$alert['postLink'] = $mybb->settings['bburl'] . '/' . get_post_link($alert['content']['pid'], $alert['tid']) . '#pid' . $alert['content']['pid'];
			$alert['message'] = $lang->sprintf($lang->myalerts_mention, $alert['user'], $alert['postLink'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline']);
		}
		else
		{
			// otherwise, just link to the new thread.
			$alert['threadLink'] = get_thread_link($alert['tid']);
			$alert['message'] = $lang->sprintf($lang->myalerts_mention, $alert['user'], $alert['threadLink'], htmlspecialchars_uni($alert['content']['subject']), $alert['dateline']);
		}
		$alert['rowType'] = 'mentionAlert';
	}
}

/* mention_myalerts_load_lang()
 *
 * loads custom language for alert settings
 */
$plugins->add_hook('myalerts_load_lang', 'mention_myalerts_load_lang');
function mention_myalerts_load_lang()
{
	global $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}
}

/* mention_myalerts_helpdoc_start()
 *
 * adds documentation for mention alerts
 */
$plugins->add_hook('misc_help_helpdoc_start', 'mention_myalerts_helpdoc_start');
function mention_myalerts_helpdoc_start()
{
	global $helpdoc, $lang, $mybb;

	if(!$lang->myalerts)
	{
		$lang->load('myalerts');
	}
	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	if($helpdoc['name'] == $lang->myalerts_help_alert_types)
	{
		if($mybb->settings['myalerts_alert_mention'])
		{
			$helpdoc['document'] .= $lang->myalerts_help_alert_types_mentioned;
		}
	}
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

	// dno alerts for mentions inside quotes so strip the quoted portions prior to detection
	$message = mention_strip_quotes($message);

	// do the replacement in the message
	$message =  mention_run($message);

	// match all the mentions in this post
	$pattern = '#@<a id="mention_(.*?)"#is';
	preg_match_all($pattern, $message, $match, PREG_SET_ORDER);
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

	// kill BB code quoted portions of the message
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

	// if we have deleted too much, back up a step
	if(!$message)
	{
		$message = $previous_message;
	}

	// kill HTML blockquoted portions of the message
	$find = array(
		"#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#",
		"#(\r\n*|\n*)<\/blockquote>#"
	);

	$replace = array(
		"",
		""
	);
	return preg_replace($find, $replace, $message);
}

?>
