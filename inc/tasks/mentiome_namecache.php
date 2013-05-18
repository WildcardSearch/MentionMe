<?php
/**
 * MentionMe
 *
 * This script is a task used to build a cache of user mention HTML to conserve queries during normal forum operation
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

 /*
  * task_mentiome_namecache()
  *
  * @param - $task an integer represented the MyBB task id
  */
function task_mentiome_namecache($task)
{
	global $db, $cache, $mybb;

	// if the user has not set a valid amount of cache_days
	if(!isset($mybb->settings['mention_cache_time']) || empty($mybb->settings['mention_cache_time']) || (int) $mybb->settings['mention_cache_time'] == 0)
	{
		// default to one week
		$cache_days = 7;
	}
	else
	{
		// otherwise use their setting
		$cache_days = (int) $mybb->settings['mention_cache_time'];
	}

	// find all users that have been active within the specified amount of days
	$timesearch = TIME_NOW - (60 * 60 * 24 * $cache_days);
	$query = $db->simple_select('users', 'uid, username, usergroup, displaygroup', "lastvisit > {$timesearch}");

	if($db->num_rows($query) > 0)
	{
		$mentions = array();
		$total_cache_size = 0;

		// if there are any results then build an array of the HTML used for @mentions
		while($user = $db->fetch_array($query))
		{
			$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
			$link = get_profile_link($user['uid']);
			$mentions[strtolower($user['username'])] = "@<a id=\"mention_{$user['uid']}\" href=\"{$link}\">{$username}</a>";
			$total_cache_size += strlen($mentions[strtolower($user['username'])]);
		}
	}

	// store the resulting array in our cache
	$wildcard_plugins = $cache->read('wildcard_plugins');
	$wildcard_plugins['mentionme']['namecache'] = $mentions;
	$cache->update('wildcard_plugins', $wildcard_plugins);

	$user_count = count($mentions);
	$total_cache_size = format_bytes($total_cache_size);

	// add an entry to the log
	add_task_log($task, "The MentionMe name cache task successfully ran. Going back {$cache_days} days, {$user_count} users were stored at a total cache size of {$total_cache_size}");
}

/*
 * format_bytes($size, $precision = 2)
 *
 * http://stackoverflow.com/users/13/chris-jester-young
 */
function format_bytes($size, $precision = 2)
{
    $base = log($size) / log(1024);
    $suffixes = array('', 'k', 'M', 'G', 'T');

    return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
}

?>
