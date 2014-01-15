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

	// is the MyAlerts class present?
	require_once MYALERTS_PLUGIN_PATH . 'Alerts.class.php';
	try {
		$Alerts = new Alerts($mybb, $db);
	} catch(Exception $e) {
		die($e->getMessage());
	}

    // grab the post data
	$message = $this_post->data['message'];
	$fid = (int) $this_post->data['fid'];
	$tid = (int) $this_post->data['tid'];
	$pid = (int) $this_post->data['pid'];
	$edit_uid = (int) $mybb->user['uid'];
	$subject = $post['subject'];

	// get all mentions
	$matches = array();
	mention_find_in_post($message, $matches);

	// no results, no alerts
	if(!is_array($matches) || empty($matches))
	{
		return;
	}

	// avoid duplicate mention alerts
	$mentioned_already = array();

	// loop through all matches (if any)
	foreach($matches as $val)
	{
		// skip blank entries
		if(!$val[0])
		{
            continue;
        }

        $uid = (int) $val[1];
        $username = $val[3];

        // prevent multiple alerts for duplicate mentions in the post and
        // the user mentioning themselves
        if($mentioned_already[$uid] || $edit_uid == $uid )
        {
            continue;
        }

        // if the user was already alerted for being mentioned in this post
        // do not create a duplicate
        $mentioned_already[$uid] = true;
        $already_alerted = false;

        // check that we haven't already sent an alert for this mention
        $query = $db->simple_select('alerts', '*', "uid='{$uid}' AND from_id='{$edit_uid}' AND tid='{$tid}' AND alert_type='mention'");
        if($db->num_rows($query) > 0)
        {
            while($this_alert = $db->fetch_array($query))
            {
                if(!$this_alert['content'])
                {
                    continue;
                }
                $this_alert['content'] = json_decode($this_alert['content'], true);

                // if an alert exists for this specific post then we have already alerted the user
                if($this_alert['content']['pid'] == $pid)
                {
                    $already_alerted = true;
                    break;
                }
            }
        }

        // no duplicates
        if(!$already_alerted)
        {
            // attempt to create the mention alert (does forum permissions check)
            mention_send_alert($username, $fid, (int) $uid, (int) $tid, (int) $edit_uid, array('pid' => $pid, 'subject' => $subject));
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
	global $mybb, $Alerts, $pid, $tid, $post, $thread, $fid;

	// if creating a new thread the message comes from $_POST
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
	$matches = array();
	mention_find_in_post($message, $matches);

	// no matches, no alerts
	if(!is_array($matches) || empty($matches))
	{
		return;
	}

	// avoid duplicate mention alerts
	$mentioned_already = array();

	// loop through all matches (if any)
	foreach($matches as $val)
	{
		// if there are matches, create alerts
		if(!$val[0])
		{
            continue;
        }

        $uid = (int) $val[1];
        $username = $val[3];

        // create an alert if MyAlerts and mention alerts are enabled and prevent multiple alerts for duplicate mentions in the post and the user mentioning themselves.
        if($mybb->user['uid'] == $uid || $mentioned_already[$uid])
        {
            continue;
        }

        // attempt to create the mention alert (does forum permissions check)
        mention_send_alert($username, $fid, (int) $uid, $tid, (int) $mybb->user['uid'], array('pid' => (int) $pid, 'subject' => $subject));
        $mentioned_already[$uid] = true;
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

	// if this is a mention alert and the user allows this type of alert then process and display it
	if($alert['alert_type'] != 'mention' || !$mybb->user['myalerts_settings']['mention'])
	{
        return;
    }

    if(!$lang->mention)
	{
		$lang->load('mention');
	}

	// If this is a reply then a pid will be present,
    if($alert['content']['pid'])
    {
        $post_link = get_post_link($alert['content']['pid'], $alert['tid']);
        $alert['postLink'] = "{$mybb->settings['bburl']}/{$post_link}#pid{$alert['content']['pid']}";
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

	if($helpdoc['name'] != $lang->myalerts_help_alert_types)
	{
        return;
    }

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

    if($mybb->settings['myalerts_alert_mention'])
    {
        $helpdoc['document'] .= $lang->myalerts_help_alert_types_mentioned;
    }
}

/* mention_find_in_post()
 *
 * find all mentions in the current post that are not within quote tags
 *
 * @param - $message
 * @param - &$matches
 */
function mention_find_in_post($message, &$matches)
{
	global $mybb;

	// no alerts for mentions inside quotes so strip the quoted portions prior to detection
	$message = mention_strip_quotes($message);

	// do the replacement in the message
	$message =  mention_run($message);

	// match all the mentions in this post
	$pattern = '#@<a id="mention_(.*?)" href="(.*?)">(.*?)</a>#is';
	preg_match_all($pattern, $message, $matches, PREG_SET_ORDER);
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

	do {
		$message = preg_replace($pattern, '', $message, -1, $count);
	} while($count);

	// kill HTML blockquoted portions of the message
	$find = array(
		"#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#",
		"#(\r\n*|\n*)<\/blockquote>#"
	);
	return preg_replace($find, '', $message);
}

/*
 * mention_send_alert()
 *
 * check forum permissions and send alert if valid
 *
 * @param - $username - (string) username of user for user by user :p
 * @param - $fid - (int) id of the forum in which the mention occurred
 * @param - $to_uid - (int) the user receiving the alert (potentially)
 * @param - $tid - (int) the id of the thread in which the mention occurred
 * @param - $from_uid - (int) the id of the user initiating the alert (potentially)
 * @param - $alert_info - (array) an indexed array of alert content info
 */
function mention_send_alert($username, $fid, $to_uid, $tid, $from_uid, $alert_info)
{
    // alertee has no permissions in the given forum?
    if(!mention_can_view($username, $to_uid, $fid))
    {
        // back away slowly . . .
        return;
    }

    // otherwise send the alert
    global $Alerts;
    $Alerts->addAlert((int) $to_uid, 'mention', (int) $tid, (int) $from_uid, $alert_info);
}

/*
 * mention_can_view()
 *
 * determine whether a sure can view a forum and ultimately whether they can receive
 * an alert initiated by a mention in the given forum
 *
 * @param - $username - (string) username
 * @param - $uid - (int) uid of the user receiving the alert (potentially)
 * @param - $fid - (int) id of the forum in which the mention occurred
 * @returns: (bool) true to allow, false to deny
 */
function mention_can_view($username, $uid, $fid)
{
    global $cache;
	static $name_cache;

	$cache_changed = false;

    // if there are no restrictions on the forum then anyone can view it
    $forum_permissions = $cache->read('forumpermissions');
    if(empty($forum_permissions[$fid]) || $forum_permissions[$fid] = 0)
    {
        return true;
    }

	// cache names to reduce queries
	if(!isset($name_cache) || empty($name_cache))
	{
		$wildcard_plugins = $cache->read('wildcard_plugins');
		$name_cache = $wildcard_plugins['mentionme']['namecache'];
	}

    $username = strtolower($username);

    /* if the username is in the cache (same one we use for mention
     * display in showthread.php) . . .
     */
    if(isset($name_cache[$username]))
    {
        // use the stored values
        $user = $name_cache[$username];
    }
    else
    {
        global $db;
        $query = $db->simple_select('users', 'uid, username, usergroup, displaygroup, additionalgroups', "uid='{$uid}'");
        $user = $db->fetch_array($query);
        $name_cache[$username] = $user;
        $cache_changed = true;
    }

    // if we had to query for this user's info then update the cache
    if($cache_changed)
    {
        $wildcard_plugins = $cache->read('wildcard_plugins');
        $wildcard_plugins['mentionme']['namecache'] = $name_cache;
        $cache->update('wildcard_plugins', $wildcard_plugins);
    }

    $users_groups = mention_compile_groups($user);

    // admins can see everything
    if(in_array(4, $users_groups))
    {
        return true;
    }

    // check for permissions in all the user's groups
    foreach($users_groups as $gid)
    {
        // empty 'displaygroup' and 'additionalgroups' are 0 or blank, skip them
        if((int) $gid == 0)
        {
            continue;
        }

        // the first 'yes' we here gets us out of the loop with a valid return
        if (!isset($forum_permissions[$fid][$gid]) ||
            $forum_permissions[$fid][$gid] == 0 ||
            $forum_permissions[$fid][$gid]['canview'] ||
            $forum_permissions[$fid][$gid]['canviewthreads']
        ) {
            return true;
        }
    }
    return false; // :( u can no lookie
}

/*
 * mention_compile_groups()
 *
 * indescriminately dump the user array's three group-related fields into a single array
 *
 * @param - $user - (array) an array of the user's info
 * @return: an uncleaned array of all the users associated group ids
 */
function mention_compile_groups($user)
{
    return array_merge(array($user['usergroup']), explode(',', $user['additionalgroups']), array($user['displaygroup']));
}

?>
