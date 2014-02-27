<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains language definitions for MentionMe (English)
 */

$l['mention'] = 'mention';
$l['mentionme'] = 'MentionMe';

// task
$l['mention_task_name'] = 'MentionMe Name Caching';
$l['mention_task_description'] = 'caches active user names mention links to conserve queries during daily use';
$l['mention_task_success'] = 'The MentionMe name cache task ran successfully. Going back {1} days, {2} users were stored at a total cache size of {3}';
$l['mention_task_fail'] = 'MentionMe found no users to cache information for.';

// settings
$l['mention_description'] = 'Display @mentions with links (and MyAlerts if installed)';
$l['mention_plugin_settings'] = 'Plugin Settings';
$l['mention_plugin_settings_title'] = 'MentionMe Configuration';
$l['mention_settingsgroup_description'] = 'Enable or disable advanced matching';

// advanced name matching
$l['mention_advanced_matching'] = 'Enable Advanced Matching?';
$l['mention_advanced_matching_desc'] = 'This option allows user names with whitespace to be processed by MentionMe without the necessity of enclosing user names in double quotes.<br /><br />This feature can greatly increase the server load and is not recommended for large forums.';

$l['mention_cache_time_title'] = 'Cache Cut-off Time';
$l['mention_cache_time_description'] = 'The task caches usernames based on when they were last active. In days, specify how far back to go. (Large forums should stick with low numbers to reduce the size of the namecache)';

$l['mention_add_codebutton_title'] = 'Add A Code Button?';
$l['mention_add_codebutton_description'] = 'YES (default) to add a button to the editor';

$l['mention_add_postbit_button_title'] = 'Add a Postbit Button?';
$l['mention_add_postbit_button_description'] = 'YES to add a button to each post allowing users to tag multiple members to mention (NO by default)';

$l['mention_multiple_title'] = 'Multiple Mentions?';
$l['mention_multiple_description'] = 'YES (default) to mimic the multi-quote feature or NO to instantly insert the mention on click<br /><br /><strong>The above setting must be set to YES for this setting to take affect</strong>';

// MyAlerts
$l['mention_myalerts_acpsetting_description'] = 'Alerts for mentions?';
$l['mention_myalerts_integration_message'] = 'MyAlerts is detected as installed but has not yet been integrated with MentionMe! You must uninstall and reinstall the plugin to receive mention alerts.';
$l['mention_myalerts_successfully_integrated'] = 'MentionMe has been successfully integrated with MyAlerts';

// force enable
$l['mention_myalerts_force_enable_alerts'] = 'Force Enable Mention Alerts For All Users';
$l['mention_myalerts_force_enable_fail_myalerts'] = 'MyAlerts components missing. MyAlerts is either not installed or installed improperly!';
$l['mention_myalerts_force_enable_fail_not_installed'] = 'MentionMe not installed!';
$l['mention_myalerts_force_enable_fail_no_users'] = 'There were no users to enable alerts for!';
$l['mention_myalerts_force_enable_success'] = 'Alerts enabled for all users';

?>
