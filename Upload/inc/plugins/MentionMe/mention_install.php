<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file provides install routines for mention.php
 */

// Disallow direct access to this file for security reasons.
if(!defined('IN_MYBB') || !defined('IN_MENTIONME'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/*
 * mention_info()
 *
 * Used by MyBB to provide relevant information about the plugin and also link users to updates.
 */
function mention_info()
{
	global $db, $lang, $mybb;

	require_once MYBB_ROOT . 'inc/plugins/MentionMe/functions_install.php';

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	$settings_link = mention_build_settings_link();

	// if MyAlerts is installed
	if($settings_link)
	{
		$settings_link = <<<EOF
				<li style="list-style-image: url(../inc/plugins/MentionMe/images/settings.gif); margin-top: 10px;">
					{$settings_link}
				</li>
EOF;
		// check to see that we have created our setting
		if($db->table_exists('alerts'))
		{
			if(mention_get_myalerts_status())
			{
				// if so give them a success message
				$myalerts_report = <<<EOF
				<li style="list-style-image: url(../images/valid.gif)">
					{$lang->mention_myalerts_successfully_integrated}
				</li>
				<li style="list-style-image: url(styles/sharepoint/images/icons/group.gif)">
					<a href="index.php?module=config-plugins&amp;action=mention_mass_enable">{$lang->mention_myalerts_force_enable_alerts}</a>
				</li>
EOF;
			}
			else
			{
				// if not, warn them and provide instructions for integration
				$myalerts_report = <<<EOF
				<li style="list-style-image: url(styles/default/images/icons/warning.gif)">{$lang->mention_myalerts_integration_message}
				</li>
EOF;
			}
		}
	}

	$button_pic = $mybb->settings['bburl'] . '/inc/plugins/MentionMe/images/donate.gif';
	$border_pic = $mybb->settings['bburl'] . '/inc/plugins/MentionMe/images/pixel.gif';
	$mention_description = <<<EOF

<table style="width: 100%;">
	<tr>
		<td style="width: 75%;">
			{$lang->mention_description}
			<ul id="mm_options">
{$myalerts_report}
{$settings_link}
			</ul>
		</td>
		<td style="width: 25%; text-align: center;">
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
				<input type="hidden" name="cmd" value="_s-xclick">
				<input type="hidden" name="hosted_button_id" value="VA5RFLBUC4XM4">
				<input type="image" src="{$button_pic}" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
				<img alt="" border="0" src="{$border_pic}" width="1" height="1">
			</form>
		</td>
	</tr>
</table>
EOF;

	$name = "<span style=\"font-familiy: arial; font-size: 1.5em; color: #258329; text-shadow: 2px 2px 2px #006A00;\">MentionMe</span>";
	$author = "</a></small></i><a href=\"http://www.rantcentralforums.com\" title=\"Rant Central\"><span style=\"font-family: Courier New; font-weight: bold; font-size: 1.2em; color: #117eec;\">Wildcard</span></a><i><small><a>";

    // return the info
	return array(
        'name' => $name,
        'description' => $mention_description,
        'website' => 'https://github.com/WildcardSearch/MentionMe',
        'version' => '2.2.2',
        'author' => $author,
        'authorsite' => 'http://www.rantcentralforums.com/',
        'guid' => '273104cdd4918caf9554d1567954d2ef',
		'compatibility' => '16*'
    );
}

/* mention_is_installed()
 *
 * check to see if the plugin is installed
 */
function mention_is_installed()
{
	return mention_get_settingsgroup();
}

/* mention_install()
 *
 * Adds a settings group with one setting for advanced matching,
 * adds a setting to the MyAlerts setting group with on/off setting (if installed)
 * and enables mention alerts for every user by default (if MyAlerts is installed)
 */
function mention_install()
{
	global $db, $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	// do it all :D
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/functions_install.php';
	if(!class_exists('WildcardPluginInstaller'))
	{
		require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/installer.php';
	}
	$installer = new WildcardPluginInstaller(MYBB_ROOT . 'inc/plugins/MentionMe/install_data.php');
	$installer->install();

	if($db->table_exists('alerts'))
	{
		mention_myalerts_integrate();
	}
	mention_generate_postbit_buttons();
}

/*
 * mention_activate()
 *
 * edit the code buttons template, add or activate the task,
 * checks upgrade status by checking cached version info
 */
function mention_activate()
{
	global $plugins, $db, $cache, $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	// version check
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/functions_install.php';
	$info = mention_info();
	$old_version = mention_get_cache_version();
	if(version_compare($old_version, $info['version'], '<') && $old_version != '' && $old_version != 0)
	{
		// check everything and upgrade if necessary
		mention_install();
    }

	// update the version (so we don't try to upgrade next round)
	mention_set_cache_version();

	// add the code button template variable
	require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';
	find_replace_templatesets('codebuttons', "#" . preg_quote('<script type="text/javascript">') . "#i", '{$lang->mentionme_codebutton}<script type="text/javascript">');
	find_replace_templatesets('showthread', "#" . preg_quote('</head>') . "#i", '{$mention_script}</head>');
	find_replace_templatesets('showthread_quickreply', "#" . preg_quote('<div class="editor_control_bar"') . "#i", '{$mention_quickreply}<div class="editor_control_bar"');
	find_replace_templatesets('showthread_quickreply', "#" . preg_quote('<input type="hidden" name="lastpid"') . "#i", '{$mentioned_ids}<input type="hidden" name="lastpid"');
	find_replace_templatesets('postbit', "#" . preg_quote('{$post[\'button_multiquote\']}') . "#i", '{$post[\'button_multiquote\']}{$post[\'button_mention\']}');
	find_replace_templatesets('postbit_classic', "#" . preg_quote('{$post[\'button_multiquote\']}') . "#i", '{$post[\'button_multiquote\']}{$post[\'button_mention\']}');

	// have we already added our name caching task?
	$query = $db->simple_select('tasks', 'tid', "file='mentiome_namecache'", array('limit' => '1'));
    if($db->num_rows($query) == 0)
	{
        // if not then do so
		require_once MYBB_ROOT.'/inc/functions_task.php';

        $this_task = array(
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

        $task_id = (int) $db->insert_query('tasks', $this_task);
        $theTask = $db->fetch_array($db->simple_select('tasks', '*', 'tid = '.(int) $task_id, 1));
        $nextrun = fetch_next_run($this_task);
        $db->update_query('tasks', "nextrun='{$nextrun}', tid='{$task_id}'");

        $plugins->run_hooks('admin_tools_tasks_add_commit');

        // update the task and go ahead and run it right now so we have some data to work with immediately
		$cache->update_tasks();
		run_task($task_id);
    }
	else
	{
        // we've already made the task, just get the id
		$tid = (int) $db->fetch_field($query, 'tid');

		// update the next run and then run the task
		require_once MYBB_ROOT.'/inc/functions_task.php';
        $db->update_query('tasks', array('enabled' => 1, 'nextrun' => TIME_NOW + 3600), "file='mentiome_namecache'");
        $cache->update_tasks();
		run_task($tid);
    }
}

/*
 * mention_deactivate()
 *
 * stops the task from running if the plugin is inactive
 */
function mention_deactivate()
{
	global $db;

	// disable the task
	$db->update_query('tasks', array('enabled' => 0), "file = 'mentiome_namecache'");

	// undo out template edits for the code button
	require_once MYBB_ROOT . '/inc/adminfunctions_templates.php';
	find_replace_templatesets('codebuttons', "#" . preg_quote('{$lang->mentionme_codebutton}') . "#i", '');
	find_replace_templatesets('showthread', "#" . preg_quote('{$mention_script}') . "#i", '');
	find_replace_templatesets('showthread_quickreply', "#" . preg_quote('{$mention_quickreply}') . "#i", '');
	find_replace_templatesets('showthread_quickreply', "#" . preg_quote('{$mentioned_ids}') . "#i", '');
	find_replace_templatesets('postbit', "#" . preg_quote('{$post[\'button_mention\']}') . "#i", '');
}

/* mention_uninstall()
 *
 * delete setting group and settings,
 * delete MyAlerts mention setting (if applicable)
 * remove mention index from user settings
 */
function mention_uninstall()
{
	global $db;

	// remove all changes
	if(!class_exists('WildcardPluginInstaller'))
	{
		require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/installer.php';
	}
	$installer = new WildcardPluginInstaller(MYBB_ROOT . 'inc/plugins/MentionMe/install_data.php');
	$installer->uninstall();

	// undo changes to MyAlerts if installed
	if($db->table_exists('alerts'))
	{
		// delete setting
		$db->delete_query('settings', "name='myalerts_alert_mention'");
		$db->delete_query('alert_settings', "code='mention'");
	}

	require_once MYBB_ROOT . 'inc/plugins/MentionMe/functions_install.php';
	mention_unset_cache_version();
}

/*
 * mention_admin_load()
 *
 * handle our one ACP 'page'
 */
function mention_admin_load()
{
	global $page, $mybb;
	if($page->active_action == 'plugins' && $mybb->input['action'] == 'mention_mass_enable')
	{
		// if it is our time
		mention_mass_enable_alerts();
		exit;
	}
}

/*
 * mention_mass_enable_alerts()
 *
 * force all users to receive mention alerts
 */
function mention_mass_enable_alerts()
{
	global $db, $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	if(!$db->table_exists('alert_settings'))
	{
		flash_message($lang->mention_myalerts_force_enable_fail_myalerts, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	// get MentionMe's alert id number
	$id_query = $db->simple_select('alert_settings', 'id', "code='mention'");

	if($db->num_rows($id_query) == 0)
	{
		// otherwise give an error
		flash_message($lang->mention_myalerts_force_enable_fail_not_installed, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	// store it
	$mention_id = (int) $db->fetch_field($id_query, 'id');

	// delete all the values (if any)
	$db->delete_query('alert_setting_values', "setting_id='{$mention_id}'");

	// get all the users
	$query = $db->simple_select('users', 'uid');
	if($db->num_rows($query) == 0)
	{
		// otherwise give an error
		flash_message($lang->mention_myalerts_force_enable_fail_no_users, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	$settings = array();
	while($uid = $db->fetch_field($query, 'uid'))
	{
		$settings[] = array(
			"user_id" => $uid,
			"setting_id" => $mention_id,
			"value" => 1
		);
	}
	$db->insert_query_multiple('alert_setting_values', $settings);

	flash_message($lang->mention_myalerts_force_enable_success, 'success');
	admin_redirect('index.php?module=config-plugins');
}

/*
 * mention_get_settingsgroup()
 *
 * retrieves the plugin's settings group gid if it exists
 * attempts to cache repeat calls
 */
function mention_get_settingsgroup()
{
	static $mention_settings_gid;

	// if we have already stored the value
	if(isset($mention_settings_gid))
	{
		// don't waste a query
		$gid = (int) $mention_settings_gid;
	}
	else
	{
		global $db;

		// otherwise we will have to query the db
		$query = $db->simple_select("settinggroups", "gid", "name='mention_settings'");
		$gid = (int) $db->fetch_field($query, 'gid');
	}
	return $gid;
}

/*
 * mention_build_settings_url()
 *
 * builds the URL to modify plugin settings if given valid info
 *
 * @param - $gid is an integer representing a valid settings group id
 */
function mention_build_settings_url($gid)
{
	if($gid)
	{
		return "index.php?module=config-settings&amp;action=change&amp;gid=" . $gid;
	}
}

/*
 * mention_build_settings_link()
 *
 * builds a link to modify plugin settings if it exists
 */
function mention_build_settings_link()
{
	global $lang;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	$gid = mention_get_settingsgroup();

	// does the group exist?
	if($gid)
	{
		// if so build the URL
		$url = mention_build_settings_url($gid);

		// did we get a URL?
		if($url)
		{
			// if so build the link
			return <<<EOF
<a href="{$url}" title="{$lang->mention_plugin_settings}">{$lang->mention_plugin_settings}</a>
EOF;
		}
	}
	return false;
}

?>
