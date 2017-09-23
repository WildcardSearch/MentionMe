<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this is the main plugin file
 */

// disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

// checked by other plugin files
define('IN_MENTIONME', true);
define('MENTIONME_VERSION', '3.1.3');

if (!class_exists('MentionMeCache')) {
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/MentionMeCache.php';
}

// load install routines only if in ACP
if (defined('IN_ADMINCP')) {
	global $mybb;
	if ($mybb->input['module'] == 'config-plugins' ||
		$mybb->input['module'] == 'config-settings') {
		require_once MYBB_ROOT . 'inc/plugins/MentionMe/install.php';
	}
} else {
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/forum.php';
}

/**
 * used to verify the MyAlerts integration status
 *
 * @return bool true if integrated, false if not
 */
function mentionGetMyAlertsStatus()
{
	static $status = false, $checked = false;

	if ($checked) {
		return $status;
	}

	global $cache;
	$checked = true;
	$myalerts_plugins = $cache->read('mybbstuff_myalerts_alert_types');

	if ($myalerts_plugins['mention']['code'] == 'mention' &&
		$myalerts_plugins['mention']['enabled'] == 1) {
		return true;
    }
	return false;
}

?>
