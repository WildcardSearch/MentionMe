<?php

function mention_is_installed ()
{
	global $db;
	
	$query = $db->simple_select('settings', "sid", "name='myalerts_alert_mention'");
	
	return $db->num_rows($query);
}

// Adds a settings group with on/off setting and enables mention alerts for every user by default.
function mention_install ()
{
	global $db, $lang;
	
	if (!$lang->mention)
	{
		$lang->load('mention');
	}

	// search for myalerts existing settings and add our custom ones
	$query = $db->simple_select("settinggroups", "gid", "name='myalerts'");
	$gid = intval($db->fetch_field($query, "gid"));
	
	$mention_setting_1 = array(
		"sid"			=> "NULL",
		"name"			=> "myalerts_alert_mention",
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

function mention_uninstall()
{
	global $db;

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

?>