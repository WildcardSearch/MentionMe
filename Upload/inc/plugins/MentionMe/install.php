<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file provides install routines for mention.php
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB') ||
	!defined('IN_MENTIONME')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/**
 * used by MyBB to provide relevant information about the plugin and
 * also link users to updates
 *
 * @return array plugin info
 */
function mention_info()
{
	global $db, $lang, $mybb, $cp_style;

	if (!$lang->mention) {
		$lang->load('mention');
	}

	$settingsLink = mentionMeBuildSettingsLink();

	// if MyAlerts is installed
	if ($settingsLink) {
		$settingsLink = <<<EOF
				<li style="list-style-image: url(styles/{$cp_style}/images/MentionMe/settings.gif); margin-top: 10px;">
					{$settingsLink}
				</li>
				<li style="list-style-image: url(styles/{$cp_style}/images/icons/run_task.png); margin-top: 10px;">
					<a href="index.php?module=config-plugins&amp;action=mentionme&amp;mode=rebuild_namecache">{$lang->mention_rebuild_name_cache_title}</a> &mdash; <span class="smalltext">{$lang->mention_rebuild_name_cache_description}</span>
				</li>
EOF;
		// check for MyAlerts
		if ($db->table_exists('alerts')) {
			if (mentionGetMyAlertsStatus()) {
				$myAlertsReport = <<<EOF
				<li style="list-style-image: url(styles/{$cp_style}/images/icons/success.png)">
					{$lang->mention_myalerts_successfully_integrated}
				</li>
EOF;
			} else {
				$myAlertsReport = <<<EOF
				<li style="list-style-image: url(styles/{$cp_style}/images/icons/warning.png)">{$lang->mention_myalerts_integration_message}
				</li>
				<li style="list-style-image: url(styles/{$cp_style}/images/icons/group.png)">
					<a href="index.php?module=config-plugins&amp;action=mentionme&amp;mode=myalerts_integrate">{$lang->mention_myalerts_integrate_link}</a>
				</li>
EOF;
			}
		}

		$buttonPic = "styles/{$cp_style}/images/MentionMe/donate.gif";
		$borderPic = "styles/{$cp_style}/images/MentionMe/pixel.gif";
		$mentionDescription = <<<EOF

<table style="width: 100%;">
	<tr>
		<td style="width: 75%;">
			{$lang->mention_description}
			<ul id="mm_options">
{$myAlertsReport}
{$settingsLink}
			</ul>
		</td>
		<td style="text-align: center;">
			<img src="styles/{$cp_style}/images/MentionMe/logo.png" alt="{$lang->mentionme_logo}"/><br /><br />
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
				<input type="hidden" name="cmd" value="_s-xclick">
				<input type="hidden" name="hosted_button_id" value="VA5RFLBUC4XM4">
				<input type="image" src="{$buttonPic}" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
				<img alt="" border="0" src="{$borderPic}" width="1" height="1">
			</form>
		</td>
	</tr>
</table>
EOF;
	} else {
		$mentionDescription = $lang->mention_description;
	}

	$name = "<span style=\"font-familiy: arial; font-size: 1.5em; color: #258329; text-shadow: 2px 2px 2px #006A00;\">MentionMe</span>";
	$author = "</a></small></i><a href=\"http://www.rantcentralforums.com\" title=\"Rant Central\"><span style=\"font-family: Courier New; font-weight: bold; font-size: 1.2em; color: #117eec;\">Wildcard</span></a><i><small><a>";

    // return the info
	return array(
        'name' => $name,
        'description' => $mentionDescription,
        'website' => 'https://github.com/WildcardSearch/MentionMe',
        'version' => MENTIONME_VERSION,
        'author' => $author,
        'authorsite' => 'http://www.rantcentralforums.com/',
		'compatibility' => '18*',
		'codename' => 'mention',
    );
}

/**
 * check to see if the plugin is installed
 *
 * @return bool true if installed, false if not
 */
function mention_is_installed()
{
	return mentionMeGetSettingsgroup();
}

/**
 * adds a settings group with one setting for advanced matching,
 * adds a setting to the MyAlerts setting group with on/off setting (if installed)
 * and enables mention alerts for every user by default (if MyAlerts is installed)
 *
 * @return void
 */
function mention_install()
{
	global $db, $lang;

	if (!$lang->mention) {
		$lang->load('mention');
	}

	MentionMeInstaller::getInstance()->install();

	if ($db->table_exists('alerts')) {
		mentionMeMyAlertsIntegrate();
	}
}

/**
 * edit the code buttons template, add or activate the task,
 * checks upgrade status by checking cached version info
 *
 * @return void
 */
function mention_activate()
{
	global $plugins, $db, $cache, $lang, $mmOldVersion;

	if (!$lang->mention) {
		$lang->load('mention');
	}

	require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';

	// version check
	$mmOldVersion = MentionMeCache::getInstance()->getVersion();
	if (version_compare($mmOldVersion, MENTIONME_VERSION, '<') &&
		$mmOldVersion != '' &&
		$mmOldVersion != 0) {
		require MYBB_ROOT . 'inc/plugins/MentionMe/upgrade.php';
    }

	// update the version (so we don't try to upgrade next round)
	MentionMeCache::getInstance()->setVersion(MENTIONME_VERSION);

	// edit the templates
	find_replace_templatesets('showthread', "#" . preg_quote('</head>') . "#i", '{$mentionScript}</head>');
	find_replace_templatesets('showthread_quickreply', "#" . preg_quote('<div class="editor_control_bar"') . "#i", '{$mentionQuickReply}<div class="editor_control_bar"');
	find_replace_templatesets('showthread_quickreply', "#" . preg_quote('<input type="hidden" name="lastpid"') . "#i", '{$mentionedIDs}<input type="hidden" name="lastpid"');
	find_replace_templatesets('postbit', "#" . preg_quote('{$post[\'button_multiquote\']}') . "#i", '{$post[\'button_multiquote\']}{$post[\'button_mention\']}');
	find_replace_templatesets('postbit_classic', "#" . preg_quote('{$post[\'button_multiquote\']}') . "#i", '{$post[\'button_multiquote\']}{$post[\'button_mention\']}');
	find_replace_templatesets('footer', '#^(.*?)$#s', '$1{$mentionAutocomplete}');

	require_once MYBB_ROOT.'/inc/functions_task.php';
	// have we already added our name caching task?
	$query = $db->simple_select('tasks', 'tid', "file='mentiome_namecache'", array('limit' => '1'));
    if ($db->num_rows($query) == 0) {
        $thisTask = array(
            "title" => $lang->mention_task_name,
            "file" => 'mentiome_namecache',
            "description" => $lang->mention_task_description,
            "minute" => 0,
            "hour" => 0,
            "day" => '*',
            "weekday" => '*',
            "month" => '*',
            "nextrun" => TIME_NOW + 3600,
            "lastrun" => 0,
            "enabled" => 1,
            "logging" => 1,
            "locked" => 0,
        );

        $taskID = (int) $db->insert_query('tasks', $thisTask);
        $nextrun = fetch_next_run($thisTask);
        $db->update_query('tasks', "nextrun='{$nextrun}', tid='{$taskID}'");

        $plugins->run_hooks('admin_tools_tasks_add_commit');
    } else {
        // we've already made the task, just get the id
		$tid = (int) $db->fetch_field($query, 'tid');

		$db->update_query('tasks', array('enabled' => 1, 'nextrun' => TIME_NOW + 3600), "file='mentiome_namecache'");
    }

	// run the task immediately so there is data to work with
	$cache->update_tasks();
	run_task($tid);
}

/**
 * stops the task from running if the plugin is inactive
 *
 * @return void
 */
function mention_deactivate()
{
	global $db;

	// disable the task
	$db->update_query('tasks', array('enabled' => 0), "file = 'mentiome_namecache'");

	// undo out template edits for the code button
	require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';
	find_replace_templatesets('showthread', "#" . preg_quote('{$mentionScript}') . "#i", '');
	find_replace_templatesets('showthread_quickreply', "#" . preg_quote('{$mentionQuickReply}') . "#i", '');
	find_replace_templatesets('showthread_quickreply', "#" . preg_quote('{$mentionedIDs}') . "#i", '');
	find_replace_templatesets('postbit', "#" . preg_quote('{$post[\'button_mention\']}') . "#i", '');
	find_replace_templatesets('postbit_classic', "#" . preg_quote('{$post[\'button_mention\']}') . "#i", '');
	find_replace_templatesets('footer', "#" . preg_quote('{$mentionAutocomplete}') . "#i", '');
}

/**
 * delete setting group and settings, templates,
 * and the style sheet
 *
 * undo MyAlerts integration and unset the cached version
 *
 * @return void
 */
function mention_uninstall()
{
	global $db, $cache;

	MentionMeInstaller::getInstance()->uninstall();

	// remove the task entry
	$db->delete_query('tasks', "file='mentiome_namecache'");

	// undo changes to MyAlerts if installed
	if ($db->table_exists('alerts')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertTypeManager->deleteByCode('mention');
	}

	MentionMeCache::getInstance()->clear();
}

/**
 * settings
 */

/**
 * add peekers
 *
 * @param  array peekers
 * @return array peekers
 */
$plugins->add_hook('admin_settings_print_peekers', 'mentionMeAddPeekers');
function mentionMeAddPeekers($peekers)
{
	global $mybb;

	if ($mybb->input['module'] != 'config-settings' ||
		$mybb->input['action'] != 'change' ||
		$mybb->input['gid'] != mentionMeGetSettingsgroup()) {
		return;
	}

	$peekers[] = 'new Peeker($(".setting_mention_auto_complete"), $("#row_setting_mention_max_items, #row_setting_mention_min_width, #row_setting_mention_get_thread_participants, #row_setting_mention_max_thread_participants, #row_setting_mention_full_text_search, #row_setting_mention_show_avatars, #row_setting_mention_lock_selection"), 1, true)';

	$peekers[] = 'new Peeker($(".setting_mention_add_postbit_button"), $("#row_setting_mention_multiple"), 1, true)';

	$peekers[] = 'new Peeker($(".setting_mention_get_thread_participants"), $("#row_setting_mention_max_thread_participants"), 1, true)';

	return $peekers;
}

/**
 * retrieves the plugin's settings group gid if it exists
 * attempts to cache repeat calls
 *
 * @return int setting group id
 */
function mentionMeGetSettingsgroup()
{
	static $gid;

	// if we have already stored the value
	if (!isset($gid)) {
		global $db;

		// otherwise we will have to query the db
		$query = $db->simple_select("settinggroups", "gid", "name='mention_settings'");
		$gid = (int) $db->fetch_field($query, 'gid');
	}
	return $gid;
}

/**
 * builds the URL to modify plugin settings if given valid info
 *
 * @param - $gid is an integer representing a valid settings group id
 * @return string setting group URL
 */
function mentionMeBuildSettingsURL($gid)
{
	if ($gid) {
		return "index.php?module=config-settings&amp;action=change&amp;gid=" . $gid;
	}
}

/**
 * builds a link to modify plugin settings if it exists
 *
 * @return setting group link HTML
 */
function mentionMeBuildSettingsLink()
{
	global $lang;

	if (!$lang->mention) {
		$lang->load('mention');
	}

	$gid = mentionMeGetSettingsgroup();

	// does the group exist?
	if ($gid) {
		// if so build the URL
		$url = mentionMeBuildSettingsURL($gid);

		// did we get a URL?
		if ($url) {
			// if so build the link
			return <<<EOF
<a href="{$url}" title="{$lang->mention_plugin_settings}">{$lang->mention_plugin_settings}</a>
EOF;
		}
	}
	return false;
}

/*
 * MyAlerts
 */

/**
 * integrate with MyAlerts
 *
 * @return void
 */
$plugins->add_hook('admin_load', 'mentionMeAdminLoad');
function mentionMeAdminLoad()
{
	global $mybb, $db, $page, $lang;
	if ($page->active_action != 'plugins' ||
		$mybb->input['action'] != 'mentionme') {
		return;
	}

	if (!$lang->mention) {
		$lang->load('mention');
	}

	if ($mybb->input['mode'] == 'myalerts_integrate') {
		// if it is our time
		mentionMeMyAlertsIntegrate();
		flash_message($lang->mention_myalerts_successfully_integrated, 'success');
		admin_redirect('index.php?module=config-plugins');
	} elseif ($mybb->input['mode'] == 'rebuild_namecache') {
		require_once MYBB_ROOT.'/inc/functions_task.php';
		$query = $db->simple_select('tasks', 'tid', "file='mentiome_namecache'", array('limit' => '1'));
		if ($db->num_rows($query) == 0) {
			flash_message($lang->mention_rebuild_name_cache_error, 'error');
			admin_redirect('index.php?module=config-plugins');
		}

		$tid = $db->fetch_field($query, 'tid');
		if (run_task($tid)) {
			flash_message($lang->mention_rebuild_name_cache_success, 'success');
		} else {
			flash_message($lang->mention_rebuild_name_cache_error, 'error');
		}
		admin_redirect('index.php?module=config-plugins');
	}
	exit;
}

/*
 * build the single ACP setting and add it to the MyAlerts group
 *
 * @return void
 */
function mentionMeMyAlertsIntegrate()
{
	global $db, $lang, $cache;

	if (!$lang->mention) {
		$lang->load('mention');
	}

	if (!function_exists("myalerts_info")) {
		return;
	}

	$myAlertsVersion = myalerts_info()['version'];
	if ((int) $myAlertsVersion === 0 ||
		version_compare($myAlertsVersion, '2.0.0', '<') ||
		isset($cache->read('mybbstuff_myalerts_alert_types')['mention']['code'])) {
		return;
	}

	$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
	$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
	$alertType->setCode("mention");
	$alertType->setEnabled(true);
	$alertTypeManager->add($alertType);
}

?>
