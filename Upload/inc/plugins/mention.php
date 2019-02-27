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
define('MENTIONME_VERSION', '3.2.9');

// register custom class autoloader
spl_autoload_register('mentionMeClassAutoLoad');

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
	static $status = null;

	if ($status !== null) {
		return $status;
	}

	global $cache;

	$myalerts_plugins = $cache->read('mybbstuff_myalerts_alert_types');

	if (function_exists('myalerts_info') &&
		($myalerts_plugins['mention']['code'] == 'mention' && $myalerts_plugins['mention']['enabled'] == 1)) {
		$status = true;
	}

	return $status;
}

/**
 * class autoloader
 *
 * @param string the name of the class to load
 */
function mentionMeClassAutoLoad($className) {
	$path = MYBB_ROOT . "inc/plugins/MentionMe/classes/{$className}.php";

	if (file_exists($path)) {
		require_once $path;
	}
}

?>
