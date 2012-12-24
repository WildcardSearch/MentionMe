<?php
/**
 * Plugin Name: MentionMe v1.0 for MyBB 1.6.*
 * Copyright � 2012 Wildcard
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

function mention_is_installed ()
{
	global $db;
	
	$query = $db->simple_select("adminlog", "action, data", "module='config-plugins' AND data LIKE '%mention%'", array("order_by" => 'dateline', "order_dir" => 'DESC'));
	$row = $db->fetch_array($query);
	
	if($db->num_rows($query))
	{
		return $row['action'] == 'activate';
	}
	
	return false;
}

// Adds a settings group with on/off setting and enables mention alerts for every user by default.
function mention_install ()
{
	global $db, $lang;
	
	if($db->field_exists('myalerts_settings', 'users'))
	{
		if (!$lang->mention)
		{
			$lang->load('mention');
		}

		// search for myalerts existing settings and add our custom ones
		$query = $db->simple_select("settinggroups", "gid", "name='myalerts'");
		$gid = intval($db->fetch_field($query, "gid"));
		
		$mention_setting_1 = array(
			"sid"			=> "NULL",
			"name"			=> "myalerts_alert_mention", // creating this setting is necessary for the display of the user cp setting
			"title"			=> $lang->mention_acpsetting_description,
			"description"	=> "",
			"optionscode"	=> "yesno",
			"value"			=> '1',
			"disporder"		=> '100',
			"gid"			=> $gid,
		);

		$db->insert_query("settings", $mention_setting_1);
		rebuild_settings();
		
		// mention alerts on by default
		$possible_settings = array(
				'mention' => "on",
				);
		
		$query = $db->simple_select('users', 'uid, myalerts_settings', '', array());
		
		while($settings = $db->fetch_array($query))
		{
			// decode existing alerts with corresponding key values
			$alert_settings = json_decode($settings['myalerts_settings']);
			
			// merge our settings with existing ones...
			$my_settings = array_merge($possible_settings, (array) $alert_settings);
			
			// and update the table cell
			$db->update_query('users', array('myalerts_settings' => $db->escape_string(json_encode($my_settings))), 'uid='.(int) $settings['uid']);
		}
	}
}

function mention_uninstall()
{
	global $db;

	if($db->field_exists('myalerts_settings', 'users'))
	{
	
		// delete setting
		$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='myalerts_alert_mention'");
		
		// Remove myalerts_settings['mention'] from all users
		$query = $db->simple_select('users', 'uid, myalerts_settings', '', array());
		
		while($settings = $db->fetch_array($query))
		{
			// decode existing alerts with corresponding key values.
			$my_settings = (array) json_decode($settings['myalerts_settings']);
			
			// delete the mention index
			unset($my_settings['mention']);
			
			// and update the table cell
			$db->update_query('users', array('myalerts_settings' => $db->escape_string(json_encode($my_settings))), 'uid='.(int) $settings['uid']);
		}
	}
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

global $settings;

if ($settings['myalerts_enabled'] && $settings['myalerts_alert_mention']) {
	$plugins->add_hook('newreply_do_newreply_end', 'myalerts_alert_mentioned');
	$plugins->add_hook('newthread_do_newthread_end', 'myalerts_alert_mentioned');
}

// check posts for mentions when they are initially created and create alerts accordingly
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
		$tid = $thread['tid'];
	}

	// Do the replacement in the message
	$message = preg_replace_callback('/@"([^<]+?)"|@([^\s<)]+)/', "Mention__filter", $message);

	// Then match all the mentions in this post
	$pattern = '#@<a id="mention_(.*?)"#is';
	$match = array();
	preg_match_all($pattern, $message, $match, PREG_SET_ORDER);
	
	// loop through all matches (if any)
	foreach($match as $val)
	{
		// if there are matches, create alerts
		if($val[0])
		{
			$uid = $val[1];
			
			// Create an alert if enabled and the user hasn't mentioned themselves.
			if ($mybb->settings['myalerts_enabled'] AND $Alerts instanceof Alerts AND $mybb->user['uid'] != $uid)
			{
				$Alerts->addAlert((int) $uid, 'mention', (int) $tid, (int) $mybb->user['uid'], array('pid' => $pid)); 
			}
		}
	}	
}

if ($settings['myalerts_enabled'] && $settings['myalerts_alert_mention'])
{
	$plugins->add_hook('myalerts_alerts_output_start', 'mention_alerts_output');
}

// Hook into MyAlerts to display alerts in the drop-down and in User CP
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
			$alert['postLink'] = $mybb->settings['bburl'].'/'.get_post_link($alert['content']['pid'], $alert['tid']).'#pid'.$alert['content']['pid'];
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

if ($settings['myalerts_enabled'] && $settings['myalerts_alert_mention'])
{
	$plugins->add_hook('myalerts_possible_settings', 'mention_alerts_setting');
}

// Add the setting in User CP
function mention_alerts_setting(&$possible_settings)
{
	global $lang;
	
	$possible_settings[] = 'mention';
	
	if (!$lang->mention)
	{
		$lang->load('mention');
	}
}

if ($settings['myalerts_enabled'] && $settings['myalerts_alert_mention'])
{
	$plugins->add_hook('misc_help_helpdoc_start', 'mention_myalerts_helpdoc');
}
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

?>
