<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file provides upgrade routines for mention.php
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB') ||
	!defined('IN_MENTIONME')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $db, $mmOldVersion;

MentionMeInstaller::getInstance()->install();

$removedAdminFolders = $removedForumFolders = $removedAdminFiles = $removedForumFiles = $removedSettings = array();

/* 3.1 */
if (version_compare($mmOldVersion, '3.1', '<')) {
	$removedForumFiles = array(
		'jscripts/MentionMe/autocomplete.sceditor.js',
		'jscripts/MentionMe/autocomplete.sceditor.min.js',
		'inc/plugins/MentionMe/classes/installer.php',
	);

	$removedForumFolders[] = 'jscripts/js_cursor_position';
}

/* 3.2 */
if (version_compare($mmOldVersion, '3.2', '<')) {
	$removedForumFiles = array_merge($removedForumFiles, array(
		'inc/plugins/MentionMe/classes/WildcardPluginInstaller.php',
		'inc/plugins/MentionMe/classes/WildcardPluginCache.php',
	));
}

/* 3.2.3 */
if (version_compare($mmOldVersion, '3.2.3', '<')) {
	$removedSettings = array(
		'mention_advanced_matching',
	);
}

if (!empty($removedForumFiles)) {
	foreach ($removedForumFiles as $file) {
		@unlink(MYBB_ROOT . $file);
	}
}

if (!empty($removedForumFolders)) {
	foreach ($removedForumFolders as $folder) {
		@my_rmdir_recursive(MYBB_ROOT . $folder);
		@rmdir(MYBB_ROOT . $folder);
	}
}

if (!empty($removedAdminFiles)) {
	foreach ($removedAdminFiles as $file) {
		@unlink(MYBB_ADMIN_DIR . $file);
	}
}

if (!empty($removedAdminFolders)) {
	foreach ($removedAdminFolders as $folder) {
		@my_rmdir_recursive(MYBB_ADMIN_DIR . $folder);
		@rmdir(MYBB_ADMIN_DIR . $folder);
	}
}

if (!empty($removedSettings)) {
	$deleteList = "'" . implode("','", (array) $removedSettings) . "'";
	$db->delete_query($table, "name IN ({$deleteList})");
}

?>
