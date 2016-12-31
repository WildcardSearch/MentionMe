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

	$cache_days = (int) $mybb->settings['mention_cache_time'];

	// if the user has not set a valid amount of cache_days
	if ((int) $cache_days <= 0) {
		// default to one week
		$cache_days = 7;
	}

	$fieldList = 'uid, username, usergroup, displaygroup, additionalgroups, ignorelist';
	if ($mybb->settings['mention_show_avatars']) {
		$fieldList .= ', avatar';
	}

	// find all users that have been active within the specified amount of days
	$timesearch = TIME_NOW - (60 * 60 * 24 * $cache_days);
	$query = $db->simple_select('users', $fieldList, "lastvisit > {$timesearch}", array("order_by" => 'lastvisit', "order_dir" => 'DESC'));

    $name_cache = array();
	if ($db->num_rows($query) > 0) {
		// if there are any results then build an array of data used for @mentions
		while ($user = $db->fetch_array($query)) {
			$name_cache[strtolower($user['username'])] = $user;
		}

        // get some stats
        $user_count = count($name_cache);
        $total_cache_size = get_friendly_size(strlen(serialize($name_cache)));

		$report = $lang->sprintf($lang->mention_task_success, $cache_days, $user_count, $total_cache_size);
	} else {
		$report = $lang->mention_task_fail;
	}

    if (!class_exists('MentionMeCache')) {
		require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/MentionMeCache.php';
	}

	// store the resulting array in our cache (if it is empty even)
    MentionMeCache::getInstance()->update('namecache', $name_cache);

	// add an entry to the log
	add_task_log($task, $report);
}

?>
