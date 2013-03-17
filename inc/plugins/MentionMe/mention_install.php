<?php
/**
 * This file is part of MentionMe and provides install routines for mention.php
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
 
// Disallow direct access to this file for security reasons.
if(!defined('IN_MYBB') || !defined('IN_MENTIONME'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/*
 * mention_info()
 *
 * Used by MyBB to provide relevant information about the plugin and also link users to updates.
 */
function mention_info()
{
	global $db, $lang;
	
	if(!$lang->mention)
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
			$mention_description = "<ul><li style=\"list-style-image: url(../images/valid.gif)\">{$lang->mention_myalerts_working}</li><a href=\"../inc/plugins/MentionMe/enable_all_alerts.php\">Enable Mention Alerts For All Users</a></ul>";
		}
		else
		{		
			// if not, warn them and provide instructions for integration
			$mention_description = "<ul><li style=\"list-style-image: url(styles/default/images/icons/warning.gif)\">{$lang->mention_myalerts_integration_message}</li></ul>";
		}
	}

    // return the info
	return array(
        'name'				=> 'MentionMe',
        'description'	=> $lang->mention_description . $mention_description,
        'website'			=> 'http://www.rantcentralforums.com/',
        'version'			=> '1.6',
        'author'			=> 'Wildcard',
        'authorsite'		=> 'http://www.rantcentralforums.com/',
        'guid'				=> '273104cdd4918caf9554d1567954d2ef',
		'compatibility'	=> '16*'
    );
}

/* mention_is_installed()
 *
 * check to see if the plugin is installed
 */
function mention_is_installed()
{
	global $db;
	
	$query = $db->simple_select('settinggroups', "gid", "name='mention_settings'");
	
	return $db->num_rows($query);
}

/* mention_install()
 *
 * Adds a settings group with one setting for advanced matching,
 * adds a setting to the myalerts settinggroup with on/off setting (if installed)
 * and enables mention alerts for every user by default (if MyAlerts is installed)
 */
function mention_install()
{
	global $db;
	
	mention_build_settings();
	
	if($db->table_exists('alerts'))
	{
		build_myalerts_settings();
	}
}

/*
 * mention_activate()
 *
 * checks upgrade status by checking cached version info
 *
 * Derived from the work of pavemen in MyBB Publisher
 */
function mention_activate()
{
	$old_version = mention_get_cache_version() ;

	if(file_exists(MYBB_ROOT . '/inc/plugins/MentionMe/mention_upgrade.php'))
	{
		require_once MYBB_ROOT . '/inc/plugins/MentionMe/mention_upgrade.php';
    }

	mention_set_cache_version();
}

/* mention_uninstall()
 *
 * delete settinggroup and setting,
 * delete myalert mention setting (if applicable)
 * remove mention index from user settings
 */
function mention_uninstall()
{
	global $db;
	
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='mention_settings'");
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='mention_advanced_matching'");
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='mention_max_per_post'");
	
	if($db->table_exists('alerts'))
	{
		// delete setting
		$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='myalerts_alert_mention'");
		
		$myalerts_version = mention_get_myalerts_version();
		
		if(version_compare($myalerts_version, '1.0.4', '<'))
		{
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
		else
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."alert_settings WHERE code='mention'");
		}
	}
	
	mention_unset_cache_version();
}

/* mention_get_alert_setting()
 *
 * used by _info to verify the mention myalerts setting
 */
function mention_get_alert_setting()
{
	global $db;
	
	$query = $db->simple_select("settings", "sid", "name='myalerts_alert_mention'");
	return $db->fetch_field($query, 'sid');
}

function mention_get_myalerts_version()
{
	global $cache;

	//get currently installed version, if there is one
	$euantorPlugins = $cache->read('euantor_plugins');
	
	if(is_array($euantorPlugins))
	{
        return $euantorPlugins['myalerts']['version'];
	}
    return 0;
}

/*
 * versioning
 */

/*
 * mention_get_cache_version()
 *
 * check cached version info
 *
 * Derived from the work of pavemen in MyBB Publisher
 */
function mention_get_cache_version()
{
	global $cache, $mybb, $db;

	//get currently installed version, if there is one
	$wildcard_plugins = $cache->read('wildcard_plugins');
	if(is_array($wildcard_plugins))
	{
        return $wildcard_plugins['mentionme']['version'];
	}
    return 0;
}

/*
 * mention_set_cache_version()
 *
 * set cached version info
 *
 * Derived from the work of pavemen in MyBB Publisher
 *
 */
function mention_set_cache_version()
{
	global $cache;

	//get version from this plugin file
	$mentionme_info = mention_info();

	//update version cache to latest
	$wildcard_plugins = $cache->read('wildcard_plugins');
	$wildcard_plugins['mentionme']['version'] = $mentionme_info['version'];
	$cache->update('wildcard_plugins', $wildcard_plugins);

    return true;
}

/*
 * mention_unset_cache_version()
 *
 * remove cached version info
 *
 * Derived from the work of pavemen in MyBB Publisher
 */
function mention_unset_cache_version()
{
	global $cache;

	$wildcard_plugins = $cache->read('wildcard_plugins');
	unset($wildcard_plugins['mentionme']['version']);
	$cache->update('wildcard_plugins', $wildcard_plugins);

    return true;
}

function mention_build_settings()
{
	global $db, $lang;
	
	if(!$lang->mention)
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
	}
	else
	{
		$gid = $db->fetch_field($query, 'gid');
	}
	
	if($gid)
	{	
		$mention_setting_1 = array(
			"sid"					=> "NULL",
			"name"				=> "mention_advanced_matching",
			"title"					=> $lang->mention_advanced_matching,
			"description"		=> $lang->mention_advanced_matching_desc,
			"optionscode"	=> "yesno",
			"value"				=> '0',
			"disporder"			=> '1',
			"gid"					=> intval($gid)
		);
		
		$query = $db->simple_select('settings', "sid", "name='mention_advanced_matching'");
		
		if($db->num_rows($query) == 1)
		{
			unset($mention_setting_1['sid']);
			$db->update_query("settings", $mention_setting_1, "name='mention_advanced_matching'");
		}
		else
		{
			$db->insert_query("settings", $mention_setting_1);
		}
	}
}

function build_myalerts_settings()
{
	global $db, $lang;
	
	if(!$lang->mention)
	{
		$lang->load('mention');
	}
	
	// search for myalerts existing settings and add our custom ones
	$query = $db->simple_select("settinggroups", "gid", "name='myalerts'");
	$gid = (int) $db->fetch_field($query, "gid");
	
	if($gid)
	{
		$mention_setting_1 = array
		(
			"sid"					=> "NULL",
			"name"				=> "myalerts_alert_mention",
			"title"					=> $lang->mention_myalerts_acpsetting_description,
			"description"		=> "",
			"optionscode"	=> "yesno",
			"value"				=> '1',
			"disporder"			=> '100',
			"gid"					=> $gid,
		);

		$query = $db->simple_select('settings', "sid", "name='myalerts_alert_mention'");
			
		if($db->num_rows($query) == 1)
		{
			unset($mention_setting_1['sid']);
			$db->update_query("settings", $mention_setting_1, "name='myalerts_alert_mention'");
		}
		else
		{
			$db->insert_query("settings", $mention_setting_1);
		}
		rebuild_settings();
		
		$myalerts_version = mention_get_myalerts_version();
		
		if(version_compare($myalerts_version, '1.0.4', '<'))
		{
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
		else
		{
			if($db->table_exists('alert_settings') && $db->table_exists('alert_setting_values'))
			{
				$query = $db->simple_select('alert_settings', "*", "code='mention'");
				
				if($db->num_rows($query) == 0)
				{			
					$db->insert_query('alert_settings', array('code' => 'mention'));
				}
			}
		}
	}
}

?>
