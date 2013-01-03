<?php
/**
 * This file is part of MentionMe and provide install routines for mention.php
 *
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
 
// Disallow direct access to this file for security reasons.
if(!defined('IN_MYBB') || !defined('IN_MENTIONME'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

// Used by MyBB to provide relevant information about the plugin and also link users to updates.
function mention_info()
{
	global $db, $lang;
	
	if (!$lang->mention)
	{
		$lang->load('mention');
	}
	
	$mention_description = '';
	
	// if MyAlerts is installed 
	if($db->table_exists('alerts') && mention_is_installed())
	{
		// check to see that we have created our setting
		if(mention_get_alert_setting())
		{
			// if so give them a success message
			$mention_description = "<ul><li style=\"list-style-image: url(../images/valid.gif)\">{$lang->mention_myalerts_working}</li></ul>";
		}
		else
		{		
			// if not, warn them and provide instructions for integration
			$mention_description = "<ul><li style=\"list-style-image: url(styles/default/images/icons/warning.gif)\">{$lang->mention_myalerts_integration_message}</li></ul>";
		}
	}

    // return the info
	return array(
        'name'			=> 'MentionMe',
        'description'	=> $lang->mention_description . $mention_description,
        'website'		=> 'http://www.rantcentralforums.com/',
        'version'		=> '1.5.2',
        'author'			=> 'Wildcard',
        'authorsite'	=> 'http://www.rantcentralforums.com/',
        'guid'				=> '273104cdd4918caf9554d1567954d2ef',
		'compatibility'	=> '16*'
    );
}

// check to see if the plugin is installed
function mention_is_installed ()
{
	global $db;
	
	$query = $db->simple_select('settinggroups', "gid", "name='mention_settings'");
	
	return $db->num_rows($query);
}

/* 
 * Adds a settings group with one setting for advanced matching,
 * adds a setting to the myalerts settinggroup with on/off setting (if installed)
 * and enables mention alerts for every user by default (if MyAlerts is installed)
 */
function mention_install ()
{
	global $db, $lang;
	
	if (!$lang->mention)
	{
		$lang->load('mention');
	}
	
	$query = $db->simple_select('settinggroups', "gid", "name='mention_settings'");
	
	if($db->num_rows($query) == 0)
	{
		// settings group and settings
		$mention_group = array(
			"gid" 				=> "NULL",
			"name" 			=> "mention_settings",
			"title" 				=> "MentionMe",
			"description" 	=> $lang->mention_settingsgroup_description,
			"disporder" 		=> "120",
			"isdefault" 		=> "no",
		);
		$db->insert_query("settinggroups", $mention_group);
		$gid = $db->insert_id();
		$mention_setting_1 = array(
			"sid"					=> "NULL",
			"name"				=> "mention_advanced_matching",
			"title"				=> $lang->mention_advanced_matching,
			"description"		=> $lang->mention_advanced_matching_desc,
			"optionscode"	=> "yesno",
			"value"				=> '0',
			"disporder"		=> '1',
			"gid"					=> intval($gid),
		);
		$db->insert_query("settings", $mention_setting_1);
	}
	
	if($db->table_exists('alerts') && !mention_get_alert_setting())
	{
		// search for myalerts existing settings and add our custom ones
		$query = $db->simple_select("settinggroups", "gid", "name='myalerts'");
		$gid = intval($db->fetch_field($query, "gid"));
		
		$mention_setting_1 = array(
			"sid"			=> "NULL",
			"name"			=> "myalerts_alert_mention",
			"title"			=> $lang->mention_myalerts_acpsetting_description,
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

/*
 * delete settinggroup and setting,
 * delete myalert mention setting (if applicable)
 * remove mention index from user settings
 */
function mention_uninstall()
{
	global $db;
	
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='mention_settings'");
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='mention_advanced_matching'");
	
	if($db->table_exists('alerts'))
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

// used by _info to verify the mention myalerts setting
function mention_get_alert_setting()
{
	global $db;
	
	$query = $db->simple_select("settings", "sid", "name='myalerts_alert_mention'");
	return $db->fetch_field($query, 'sid');
}

?>