<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file provides functions for mention_install.php
 */

/*
 * mention_generate_postbit_buttons()
 *
 * @param - $hard - (bool) true to overwrite any existing images
 */
function mention_generate_postbit_buttons($hard = false)
{
	global $mybb, $db;

	$all_dirs = array();
	$query = $db->simple_select('themes', 'pid, properties');
	while($theme = $db->fetch_array($query))
	{
		$properties = unserialize($theme['properties']);
		if($theme['pid'] == 0)
		{
			$master_dir = $properties['imgdir'];
		}
		$all_dirs[] = $properties['imgdir'];
	}

	require_once MYBB_ROOT . 'inc/plugins/MentionMe/button_images.php';
	foreach($all_dirs as $dir)
	{
		$path = MYBB_ROOT . $dir . "/{$mybb->settings['bblanguage']}";
		if(is_dir($path))
		{
			foreach(array("postbit_button" => 'postbit_multi_mention.gif', "postbit_button_on" => 'postbit_multi_mention_on.gif') as $key => $filename)
			{
				$full_path = "{$path}/{$filename}";
				if(!file_exists($full_path) || $hard)
				{
					file_put_contents($full_path, $$key);
				}
			}
		}
	}
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
	global $cache;

	//get currently installed version, if there is one
	$wildcard_plugins = $cache->read('wildcard_plugins');
	if(trim($wildcard_plugins['mentionme']['version']))
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
	$info = mention_info();

	//update version cache to latest
	$wildcard_plugins = $cache->read('wildcard_plugins');
	$wildcard_plugins['mentionme']['version'] = $info['version'];
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
	$wildcard_plugins['mentionme'] = null;
	$cache->update('wildcard_plugins', $wildcard_plugins);

    return true;
}

/*
 * MyAlerts
 */

/*
 * mention_myalerts_integrate()
 *
 * build the single ACP setting and add it to the MyAlerts group
 */
function mention_myalerts_integrate()
{
	global $db, $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	// search for MyAlerts existing settings and add our custom ones
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

/* mention_get_myalerts_status()
 *
 * used by _info to verify the mention MyAlerts setting
 */
function mention_get_myalerts_status()
{
	global $db;

	if($db->table_exists('alert_settings'))
	{
		$query = $db->simple_select('alert_settings', "*", "code='mention'");
		return ($db->num_rows($query) == 1);
	}
	return false;
}

?>
