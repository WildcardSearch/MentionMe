<?php
/**
 * Plugin Name: MentionMe v1.0 for MyBB 1.6.*
 * Copyright © 2012 Wildcard
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

// Deny outside access.
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

global $settings;

if($settings['myalerts_enabled'] || mention_get_setting())
{
	require_once MYBB_ROOT . "inc/plugins/MentionMe/mention_install.php";
}

// Used by MyBB to provide relevant information about the plugin and also link users to updates.
function mention_info()
{
	global $lang;
	
	if (!$lang->mention)
	{
		$lang->load('mention');
	}

    return array(
        'name'			=> 'MentionMe',
        'description'	=> $lang->mention_description,
        'website'		=> 'http://www.rantcentralforums.com/',
        'version'		=> '1.0',
        'author'			=> 'Wildcard',
        'authorsite'	=> 'http://www.rantcentralforums.com/',
        'guid'				=> '273104cdd4918caf9554d1567954d2ef',
		'compatibility'	=> '16*'
    );
}

$plugins->add_hook("parse_message_end", "mention_run");
 
function mention_run($message)
{
	// use function Mention__filter to repeatedly process mentions in the current post
	return preg_replace_callback('/@"([^<]+?)"|@([^\s<)]+)/', "Mention__filter", $message);
}

function Mention__filter(array $match)
{
	global $db;
	static $namecache = array();

	$origName = $match[0];
	array_shift($match);
	while (strlen(trim($match[0])) == 0)
		array_shift($match);
		
	$usernameLower = my_strtolower(html_entity_decode($match[0]));
	
	if (isset($namecache[$usernameLower]))
	{
		return $namecache[$usernameLower];
	}
	else
	{
		$query = $db->simple_select("users", "uid, username, usergroup, displaygroup", "LOWER(username)='".$db->escape_string($usernameLower)."'", array('limit' => 1));
		if($db->num_rows($query) === 1)
		{
			$user = $db->fetch_array($query);
		}
		else
		{
			return $origName;
		}
		$username = htmlspecialchars_uni($user['username']);
		$usergroup = $user['usergroup'];
		$uid = $user['uid'];
		$displaygroup = $user['displaygroup'];
		$username = format_name($username, $usergroup, $displaygroup);
		$link = get_profile_link($user['uid']);
		
		return $namecache[$usernameLower] = "@<a id=\"mention_$uid\" href=\"{$link}\">{$username}</a>";
	}
}

if ($settings['myalerts_enabled'] && $settings['myalerts_alert_mention'])
{
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/mention_alerts.php';
}

function mention_get_setting()
{
	global $db;
	
	$query = $db->simple_select("settings", "sid", "name='myalerts_alert_mention'");
	return $db->fetch_field($query, 'sid');
}

?>
