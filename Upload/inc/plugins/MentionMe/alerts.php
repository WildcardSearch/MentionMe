<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this script provides MyAlerts routines for mention.php
 */

// disallow direct access to this file for security reasons.
if (!defined('IN_MYBB') ||
	!defined('IN_MENTIONME')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/**
 * create alerts when users edit a post and add a new mention. try to avoid sending any duplicate alerts.
 *
 * @param  object post info
 * @return void
 */
$plugins->add_hook("datahandler_post_update", "mentionMeMyAlertsDatahandlerPostUpdate");
function mentionMeMyAlertsDatahandlerPostUpdate($this_post)
{
	global $db, $mybb, $post;

	// grab the post data
	$message = $this_post->data['message'];
	$fid = (int) $this_post->data['fid'];
	$tid = (int) $this_post->data['tid'];
	$pid = (int) $this_post->data['pid'];
	$postUID = (int) $post['uid'];
	$editUID = (int) $mybb->user['uid'];
	$subject = $post['subject'];

	// if another user is editing (mod) don't do alerts
	if ($editUID != $postUID) {
		return;
	}

	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('mention');
	$matches = array();
	$alerts = array();

	// get all mentions
	$matches = array();
	mentionMeFindInPost($message, $matches);

	// no results, no alerts
	if (!is_array($matches) ||
		empty($matches)) {
		return;
	}

	// avoid duplicate mention alerts
	$mentionedAlready = array();

	// loop through all matches (if any)
	foreach ($matches as $val) {
		// skip blank entries
		if (!$val[0]) {
            continue;
        }

        $uid = (int) $val[1];
        $username = $val[3];

        // prevent multiple alerts for duplicate mentions in the post and
        // the user mentioning themselves
        if ($mentionedAlready[$uid] ||
			$editUID == $uid ) {
            continue;
        }

        // if the user was already alerted for being mentioned in this post
        // do not create a duplicate
        $mentionedAlready[$uid] = true;

        if (!mentionMeCheckPermissions($username, $to_uid, $fromUID, $fid)) {
			continue;
		}

		$alert = new MybbStuff_MyAlerts_Entity_Alert((int) $postUID, $alertType, $tid);
        $alert->setExtraDetails(
            array(
                'thread_title' => $subject,
                'pid' => $pid,
                'tid' => $tid
			)
		);
        $alerts[] = $alert;
	}

    if (!empty($alerts)) {
        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlerts($alerts);
    }
}

/**
 * check posts for mentions when they are initially created and
 * create alerts if appropriate
 *
 * @return void
 */
$plugins->add_hook('newreply_do_newreply_end', 'mentionMeMyAlertsDoNewReplyEnd');
$plugins->add_hook('newthread_do_newthread_end', 'mentionMeMyAlertsDoNewReplyEnd');
function mentionMeMyAlertsDoNewReplyEnd()
{
	global $mybb, $pid, $tid, $post, $thread, $fid;

	$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('mention');
    $alerts = array();

    // if creating a new thread the message comes from $_POST
	if ($mybb->input['action'] == "do_newthread" &&
		$mybb->request_method == "post") {
		$message = $mybb->input['message'];
		$subject = $mybb->input['subject'];
	// otherwise the message comes from $post['message']
	// and the $tid comes from $thread['tid']
	} else {
		$message = $post['message'];
		$tid = (int) $thread['tid'];
		$subject = $thread['subject'];
	}

	$fromUser = (int) $mybb->user['uid'];

    // get all mentions
	$matches = array();
	mentionMeFindInPost($message, $matches);

	// no matches, no alerts
	if (!is_array($matches) ||
		empty($matches)) {
		return;
	}

	// avoid duplicate mention alerts
	$mentionedAlready = array();

	// loop through all matches (if any)
	foreach ($matches as $val) {
		// if there are matches, create alerts
		if (!$val[0]) {
            continue;
        }

        $uid = (int) $val[1];
        $username = $val[3];

        // create an alert if MyAlerts and mention alerts are enabled and prevent multiple alerts for duplicate mentions in the post and the user mentioning themselves.
        if ($mybb->user['uid'] == $uid ||
			$mentionedAlready[$uid]) {
            continue;
        }

        $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $uid, $alertType, $tid);
        $alert->setExtraDetails(
            array(
                'thread_title' => $subject,
                'pid' => $pid,
                'tid' => $tid
			)
		);
		$alert->setFromUserId($fromUser);
        $alerts[] = $alert;

        $mentionedAlready[$uid] = true;
	}

    if (!empty($alerts)) {
        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlerts($alerts);
    }
}

/**
 * load custom alert formatter
 *
 * @return void
 */
$plugins->add_hook('global_start', 'mentionMeMyAlertsDisplay');
function mentionMeMyAlertsDisplay() {
    global $mybb, $lang;

    if (!$mybb->user['uid']) {
		return;
    }

	require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/MentionMeFormatter.php';
	$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
	$formatterManager->registerFormatter(new MentionMeFormatter($mybb, $lang, 'mention'));
}

/**
 * find all mentions in the current post that are not within quote tags
 *
 * @param  string
 * @param  array a reference to an array to store matches in
 * @return void
 */
function mentionMeFindInPost($message, &$matches)
{
	global $mybb;

	// no alerts for mentions inside quotes so strip the quoted portions prior to detection
	$message = mentionMeStripQuotes($message);

	// do the replacement in the message
	$message =  mentionMeParseMessage($message);

	// match all the mentions in this post
	$pattern = '#@<a id="mention_(.*?)" href="(.*?)">(.*?)</a>#is';
	preg_match_all($pattern, $message, $matches, PREG_SET_ORDER);
}

/**
 * strips all quotes and their content from the post
 *
 * @param  string content of the post
 * @return string message
 */
function mentionMeStripQuotes($message)
{
	global $lang, $templates, $theme, $mybb;

	// kill BB code quoted portions of the message
	$pattern = array(
		"#\[quote=([\"']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#si",
		"#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si"
	);

	do {
		$message = preg_replace($pattern, '', $message, -1, $count);
	} while($count);

	// kill HTML block quoted portions of the message
	$find = array(
		"#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#",
		"#(\r\n*|\n*)<\/blockquote>#"
	);
	return preg_replace($find, '', $message);
}

/**
 * determine whether a user can view a forum and ultimately whether they can receive
 * an alert initiated by a mention in the given forum
 *
 * @param  string username
 * @param  int uid of the user receiving the alert (potentially)
 * @param  int uid of the user sending the alert (potentially)
 * @param  int id of the forum in which the mention occurred
 * @return bool true to allow, false to deny
 */
function mentionMeCheckPermissions($username, $uid, $fromUID, $fid)
{
    global $cache;
	static $nameCache, $myCache;

	// cache names to reduce queries
	if ($myCache instanceof MentionMeCache == false) {
		$myCache = MentionMeCache::getInstance();
	}

	if (!isset($nameCache)) {
		$nameCache = $myCache->read('namecache');
	}

    $username = mb_strtolower($username);

    /*
	 * if the user name is in the cache
	 * (the same one we use for mentions)
     * display in showthread.php...
     */
    if (isset($nameCache[$username])) {
        // use the stored values
        $user = $nameCache[$username];
    } else {
        global $db;
        $query = $db->simple_select('users', 'uid, username, usergroup, displaygroup, additionalgroups, ignorelist', "uid='{$uid}'");
        $user = $db->fetch_array($query);
        $nameCache[$username] = $user;
        $myCache->update('namecache', $nameCache);
    }

	// don't alert if mentioning user is on mentioned users ignore list
    $ignoreList = explode(',', $user['ignorelist']);
	if (in_array($fromUID, $ignoreList)) {
		return false;
	}

    $forum_permissions = $cache->read('forumpermissions');

    // if there are no restrictions on the forum then anyone can view it
    if (empty($forum_permissions[$fid]) ||
		$forum_permissions[$fid] = 0) {
        return true;
    }

	$userGroups = mentionMeCompileGroups($user);

    // admins can see everything
    if (in_array(4, $userGroups)) {
        return true;
    }

    // check for permissions in all the user's groups
    foreach ($userGroups as $gid) {
        // empty 'displaygroup' and 'additionalgroups' are 0 or blank, skip them
        if ((int) $gid == 0) {
            continue;
        }

        // the first 'yes' we hear gets us out of the loop with a valid return
        if (!isset($forum_permissions[$fid][$gid]) ||
            $forum_permissions[$fid][$gid] == 0 ||
            $forum_permissions[$fid][$gid]['canview'] ||
            $forum_permissions[$fid][$gid]['canviewthreads']) {
            return true;
        }
    }
    return false; // :( u can no lookie
}

/**
 * indiscriminately dump the user array's three group-related fields into a single array
 *
 * @param  array an array of the user's info
 * @return array all the users associated group ids
 */
function mentionMeCompileGroups($user)
{
    return array_merge(array($user['usergroup']), explode(',', $user['additionalgroups']), array($user['displaygroup']));
}

?>
