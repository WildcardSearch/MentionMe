<?php
/**
 * MentionMe
 *
 * This script force-enables mention alerts for all users.
 *
 * Portions of this code written by Shade
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

define('IN_MYBB', 1);
require_once "../../../global.php";
global $db, $config;

if(!$db->table_exists('alert_settings'))
{
	die('MyAlerts not installed!');
}

// get MentionMe's alert id number
$id_query = $db->simple_select('alert_settings', 'id', "code='mention'");

// if it exists . . .
if($db->num_rows($id_query) == 1)
{
	// store it
	$mention_id = $db->fetch_field($id_query, 'id');
}
else
{
	// otherwise die
	die('MentionMe not installed!');
}

// delete all the values (if any)
$db->query("DELETE FROM " . TABLE_PREFIX . "alert_setting_values WHERE setting_id='{$mention_id}'");

// get all the users
$query = $db->simple_select('users', 'uid');
if($db->num_rows($query) > 0)
{
	$settings = array();

	while($uid = $db->fetch_field($query, 'uid'))
	{
		$settings[] = array
		(
			"user_id"			=>	$uid,
			"setting_id"		=>	$mention_id,
			"value"				=>	1
		);
	}

	$db->insert_query_multiple('alert_setting_values', $settings);

	header("Location: ../../../" . $config['admin_dir'] . "/index.php?module=config-plugins");
}
else
{
	die("Nothing to do.");
}

?>
