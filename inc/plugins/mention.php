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

// deny outside access.
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// make sure we have access to ACP settings in the main script
global $settings;

// If MyAlerts isn't installed there is no need to install
if(defined("IN_ADMINCP") && $settings['myalerts_enabled'])
{
	require_once MYBB_ROOT . "inc/plugins/MentionMe/mention_install.php";
}

// Used by MyBB to provide relevant information about the plugin and also link users to updates.
function mention_info()
{
	global $lang, $settings, $mybb;
	
	if (!$lang->mention)
	{
		$lang->load('mention');
	}
	
	$mention_description = '';
	
	if($settings['myalerts_enabled'])
	{
		if(mention_get_setting())
		{
			$mention_description = "<ul><li style=\"list-style-image: url(../images/valid.gif)\">{$lang->mention_myalerts_working}</li></ul>";
		}
		else
		{		
			$mention_description = "<ul><li style=\"list-style-image: url(styles/default/images/icons/warning.gif)\">{$lang->mention_myalerts_integration_message}</li><br /><li><a href=\"{$mybb->settings['bburl']}/admin/index.php?module=config-plugins&amp;action=activate&amp;plugin=mention&amp;my_post_key={$mybb->post_code}\">{$lang->mention_myalerts_integrate}</a></li></ul>";
		}
	}

    return array(
        'name'			=> 'MentionMe',
        'description'	=> $lang->mention_description . $mention_description,
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

	// save the original name
	$origName = $match[0];
	
	// if the user entered the mention in quotes then it will be returned in @match[1],
	// if not it will be returned in $match[2]
	array_shift($match);
	while (strlen(trim($match[0])) == 0)
	{
		array_shift($match);
	}
		
	// generate a lowercase and DB-friendly username to search with
	$usernameLower = my_strtolower(html_entity_decode($match[0]));
	
	// if the name is already in the cache then simply return it and save the query
	if (isset($namecache[$usernameLower]))
	{
		return $namecache[$usernameLower];
	}
	else
	{
		// if not, query the db for the name entered
		$query = $db->simple_select("users", "uid, username, usergroup, displaygroup", "LOWER(username)='".$db->escape_string($usernameLower)."'", array('limit' => 1));
		if($db->num_rows($query) === 1)
		{
			$user = $db->fetch_array($query);
		}
		else
		{
			// if it isn't found then do nothing
			return $origName;
		}
		
		// set up the username link so that it displays correctly for the display group of the user
		$username = htmlspecialchars_uni($user['username']);
		$usergroup = $user['usergroup'];
		$uid = $user['uid'];
		$displaygroup = $user['displaygroup'];
		$username = format_name($username, $usergroup, $displaygroup);
		$link = get_profile_link($user['uid']);
		
		// and return the mention
		// the HTML id property is used to store the uid of the mentioned user for MyAlerts (if installed)
		return $namecache[$usernameLower] = "@<a id=\"mention_$uid\" href=\"{$link}\">{$username}</a>";
	}
}

// if MyAlerts is installed and alerts are enabled globally for mentions then require the alerts functions
if ($settings['myalerts_enabled'] && $settings['myalerts_alert_mention'])
{
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/mention_alerts.php';
}

// used by _info to verify the mention setting
function mention_get_setting()
{
	global $db;
	
	$query = $db->simple_select("settings", "sid", "name='myalerts_alert_mention'");
	return $db->fetch_field($query, 'sid');
}

?>
