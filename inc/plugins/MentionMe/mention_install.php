<?php
/**
 * MentionMe
 *
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
		if(mention_get_myalerts_status())
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

	$mention_description = <<<EOF
<table style="width: 100%;">
	<tr>
		<td style="width: 50%;">{$lang->mention_description}{$mention_description}</td>
		<td style="width: 50%; text-align: center;">
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
				<input type="hidden" name="cmd" value="_s-xclick">
				<input type="hidden" name="hosted_button_id" value="VA5RFLBUC4XM4">
				<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
				<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
			</form>
		</td>
	</tr>
</table>
EOF;

	$name = "<span style=\"font-familiy: arial; font-size: 1.5em; color: #258329; text-shadow: 2px 2px 2px #006A00;\">MentionMe</span>";
	$author = "</a></small></i><a href=\"http://www.rantcentralforums.com\" title=\"Rant Central\"><span style=\"font-family: Courier New; font-weight: bold; font-size: 1.2em; color: #117eec;\">Wildcard</span></a><i><small><a>";

    // return the info
	return array(
        'name'				=> $name,
        'description'		=> $mention_description,
        'website'			=> 'https://github.com/WildcardSearch/MentionMe',
        'version'			=> '2.0',
        'author'				=> $author,
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
		mention_myalerts_integrate();
	}
}

/*
 * mention_activate()
 *
 * checks upgrade status by checking cached version info
 *
 * derived from the work of pavemen in MyBB Publisher
 */
function mention_activate()
{
	global $plugins, $db, $cache, $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	// store the current cached version number (if it exists)
	$old_version = mention_get_cache_version() ;

	// if the upgrade script is in tact
	if(file_exists(MYBB_ROOT . '/inc/plugins/MentionMe/mention_upgrade.php'))
	{
		// require it (and it will do its thing auto
		require_once MYBB_ROOT . '/inc/plugins/MentionMe/mention_upgrade.php';
    }

	// now update the version (so we don't try to upgrade next round)
	mention_set_cache_version();

	// have we already added our name cacheing task?
	$query = $db->simple_select('tasks', 'tid', "file='mentiome_namecache'", array('limit' => '1'));
    if($db->num_rows($query) == 0)
	{
        // if not then do so
		require_once MYBB_ROOT.'/inc/functions_task.php';

        $this_task = array(
            "title"				=> $lang->mention_task_name,
            "file"					=> 'mentiome_namecache',
            "description"		=> $lang->mention_task_description,
            "minute"			=> 0,
            "hour"				=> 0,
            "day"				=> '*',
            "weekday"		=> '*',
            "month"			=> '*',
            "nextrun"			=> TIME_NOW + 3600,
            "lastrun"			=> 0,
            "enabled"			=> 1,
            "logging"			=> 1,
            "locked"			=> 0,
        );

        $task_id = (int) $db->insert_query('tasks', $this_task);
        $theTask = $db->fetch_array($db->simple_select('tasks', '*', 'tid = '.(int) $task_id, 1));
        $nextrun = fetch_next_run($this_task);
        $db->update_query('tasks', "nextrun='{$nextrun}', tid='{$task_id}'");

        $plugins->run_hooks('admin_tools_tasks_add_commit');

        // update the task and go ahead and run it right now so we have some data to work with immediately
		$cache->update_tasks();
		run_task($task_id);
    }
	else
	{
        // we've already made the task, just get the id
		$tid = (int) $db->fetch_field($query, 'tid');

		// update the nextrun and then run the task
		require_once MYBB_ROOT.'/inc/functions_task.php';
        $db->update_query('tasks', array('enabled' => 1, 'nextrun' => TIME_NOW + 3600), "file='mentiome_namecache'");
        $cache->update_tasks();
		run_task($tid);
    }
}

/*
 * mention_deactivate()
 *
 * stops the task from running if the plugin is inactive
 */
function mention_deactivate()
{
	global $db;

	// remove the task
	$db->update_query('tasks', array('enabled' => 0), 'file = \'mentiome_namecache\'');
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

		$db->query("DELETE FROM ".TABLE_PREFIX."alert_settings WHERE code='mention'");
	}

	$db->delete_query('tasks', "file='mentiome_namecache'");

	mention_unset_cache_version();
}

/* mention_get_myalerts_status()
 *
 * used by _info to verify the mention myalerts setting
 */
function mention_get_myalerts_status()
{
	global $db;

	if($db->table_exists('alert_settings') && $db->table_exists('alert_setting_values'))
	{
		$query = $db->simple_select('alert_settings', "*", "code='mention'");

		if($db->num_rows($query) == 1)
		{
			return true;
		}
	}
	return false;
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
	unset($wildcard_plugins['mentionme']);
	$cache->update('wildcard_plugins', $wildcard_plugins);

    return true;
}

/*
 * mention_build_settings()
 *
 * builds all settings and insert them or if they already exist update them
 */
function mention_build_settings()
{
	global $db, $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	// if our group isn't already made
	$query = $db->simple_select('settinggroups', "gid", "name='mention_settings'");
	if($db->num_rows($query) == 0)
	{
		// make it
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
		// otherwise just store the GID
		$gid = $db->fetch_field($query, 'gid');
	}

	// if we have a good GID
	if($gid)
	{
		// build the settings and insert/update them
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

		$mention_setting_2 = array(
			"sid"					=> "NULL",
			"name"				=> "mention_cache_time",
			"title"				=> 'How far back to cache names?',
			"description"		=> 'The task caches usernames based on when they were last active. In days, specify how far back to go. (Large forums should stick with low numbers to reduce the size of the namecache)',
			"optionscode"	=> "text",
			"value"				=> '7',
			"disporder"		=> '2',
			"gid"					=> intval($gid)
		);
		$query = $db->simple_select('settings', "sid", "name='mention_cache_time'");

		if($db->num_rows($query) == 1)
		{
			unset($mention_setting_2['sid']);
			$db->update_query("settings", $mention_setting_2, "name='mention_cache_time'");
		}
		else
		{
			$db->insert_query("settings", $mention_setting_2);
		}
	}
}

/*
 * mention_myalerts_integrate()
 *
 * build the single ACP setting and add it to the myalerts group
 */
function mention_myalerts_integrate()
{
	global $db, $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	// search for myalerts existing settings and add our custom ones
	$query = $db->simple_select("settinggroups", "gid", "name='myalerts'");
	$gid = (int) $db->fetch_field($query, "gid");

	// MyAlerts installed?
	if($gid)
	{
		// if so add a setting to Euan's group (he hates it when I do that :P )
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

		// now add our mention type
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

?>
