<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this script is a task used to build a cache of user mention
 * data to conserve queries during normal forum operation
 */

/**
 * @param  int task id
 * @return void
 */
function task_mentiome_namecache($task)
{
	global $db, $mybb, $lang;

	if (!$lang->mention) {
		$lang->load('mention');
	}

	$cacheDays = (int) $mybb->settings['mention_cache_time'];

	// if the user has not set a valid amount of cache_days
	if ((int) $cacheDays <= 0) {
		// default to one week
		$cacheDays = 7;
	}

	$fieldList = 'uid, username, usergroup, displaygroup, additionalgroups, ignorelist';
	if ($mybb->settings['mention_show_avatars']) {
		$fieldList .= ', avatar';
	}

	// find all users that have been active within the specified amount of days
	$timesearch = TIME_NOW - (60 * 60 * 24 * $cacheDays);
	$query = $db->simple_select('users', $fieldList, "lastvisit > {$timesearch}", array("order_by" => 'lastvisit', "order_dir" => 'DESC'));

    $nameCache = array();
	if ($db->num_rows($query) > 0) {
		// if there are any results then build an array of data used for @mentions
		while ($user = $db->fetch_array($query)) {
			$nameCache[strtolower($user['username'])] = $user;
		}

        // get some stats
        $userCount = count($nameCache);
        $totalCacheSize = get_friendly_size(strlen(serialize($nameCache)));

		$report = $lang->sprintf($lang->mention_task_success, $cacheDays, $userCount, $totalCacheSize);
	} else {
		$report = $lang->mention_task_fail;
	}

    if (!class_exists('MentionMeCache')) {
		require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/MentionMeCache.php';
	}

	// store the resulting array in our cache (if it is empty even)
    MentionMeCache::getInstance()->update('namecache', $nameCache);

	// add an entry to the log
	add_task_log($task, $report);
}

?>
