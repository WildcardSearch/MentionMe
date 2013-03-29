<?php

define('IN_MYBB', 1);
require_once "../../../global.php";

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

$query = $db->simple_select('users', 'uid');

$settings = array();
if($db->num_rows($query) > 0)
{
	while($uid = $db->fetch_field($query, 'uid'))
	{
		$settings[] = array
		(
			"user_id"			=>	$uid,
			"setting_id"		=>	$mention_id,
			"value"				=>	1
		);
	}
}

$db->insert_query_multiple('alert_setting_values', $settings);

header("Location: ../../../" . $config['admin_dir'] . "/index.php?module=config-plugins");

?>
