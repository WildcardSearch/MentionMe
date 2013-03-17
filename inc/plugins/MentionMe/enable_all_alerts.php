<?php

define('IN_MYBB', 1);
require "../../../global.php";

global $db, $config;

$id_query = $db->simple_select('alert_settings', 'id', "code='mention'");

if($db->num_rows($id_query) == 1)
{
	$mention_id = $db->fetch_field($id_query, 'id');
}
else
{
	die('MentionMe not installed!');
}

$db->query("DELETE FROM " . TABLE_PREFIX . "alert_setting_values WHERE setting_id='{$mention_id}'");

$query = $db->simple_select('users');

if($db->num_rows($query) > 0)
{
	while($user = $db->fetch_array($query))
	{
		$settings_value = array
		(
			"user_id"			=>	$user['uid'],
			"setting_id"		=>	$mention_id,
			"value"				=>	1
		);

		$setting_query = $db->insert_query('alert_setting_values', $settings_value);
	}
}

header("Location: ../../../" . $config['admin_dir'] . "/index.php?module=config-plugins");

?>
