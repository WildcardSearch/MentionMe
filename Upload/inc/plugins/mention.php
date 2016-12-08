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

if (!class_exists('MentionMeCache')) {
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/MentionMeCache.php';
}

// load install routines only if in ACP
if (defined('IN_ADMINCP')) {
	global $mybb;
	if ($mybb->input['module'] == 'config-plugins') {
		require_once MYBB_ROOT . 'inc/plugins/MentionMe/install.php';
	}
} else {
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/forum.php';
}

?>
