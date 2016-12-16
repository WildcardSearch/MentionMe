<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains language definitions for MentionMe (English)
 */

$l['mention'] = 'mention';
$l['mentionme'] = 'MentionMe';
$l['mentionme_logo'] = 'MentionMe Logo';

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

$l['mention_cache_time_title'] = 'Cache Cut-off Time';
$l['mention_cache_time_description'] = 'The task caches usernames based on when they were last active. In days, specify how far back to go. (Large forums should stick with low numbers to reduce the size of the namecache)';

$l['mention_auto_complete_title'] = 'Auto-Complete Mentions?';
$l['mention_auto_complete_description'] = 'YES (default) to autocomplete mentions as they are typed on showthread in Quick Reply and full post/edit pages';

$l['mention_add_codebutton_title'] = 'Add A Code Button?';
$l['mention_add_codebutton_description'] = 'YES (default) to add a code button to the full editor';

$l['mention_add_postbit_button_title'] = 'Add a Postbit Button?';
$l['mention_add_postbit_button_description'] = 'YES to add a button to each post allowing users to tag multiple members to mention (NO by default)';

$l['mention_multiple_title'] = 'Multiple Mentions?';
$l['mention_multiple_description'] = 'YES (default) to mimic the multi-quote feature or NO to instantly insert the mention on click<br /><br /><strong>The postbit button setting must be set to YES for this setting to take effect</strong>';

$l['mention_css_buttons_title'] = 'CSS Buttons?';
$l['mention_css_buttons_description'] = 'YES if your postbit buttons are styled with CSS NO (default) if you use image buttons<br /><br /><strong>The postbit button setting must be set to YES for this setting to take effect</strong>';

$l['mention_advanced_matching'] = 'Enable Advanced Matching?';
$l['mention_advanced_matching_desc'] = 'This option allows user names with whitespace to be processed by MentionMe without the necessity of enclosing user names in double quotes.<br /><br />This feature can greatly increase the server load and is not recommended for large forums.';

$l['mention_minify_js_title'] = 'Minify JavaScript?';
$l['mention_minify_js_desc'] = 'YES (default) to serve client-side scripts minified to increase performance, NO to serve beautiful, commented code ;)';

$l['mention_format_names_title'] = 'Format Usernames?';
$l['mention_format_names_desc'] = 'YES (default) to format user names according to their display group, NO to format mentions as plain links';

// MyAlerts
$l['mention_myalerts_acpsetting_description'] = 'Alerts for mentions?';
$l['mention_myalerts_integration_message'] = 'MyAlerts is detected as installed but has not yet been integrated with MentionMe! You must uninstall and reinstall the plugin to receive mention alerts.';
$l['mention_myalerts_successfully_integrated'] = 'MentionMe has been successfully integrated with MyAlerts';

// force enable
$l['mention_myalerts_force_enable_alerts'] = 'Force Enable Mention Alerts For All Users';
$l['mention_myalerts_force_enable_fail_myalerts'] = 'MyAlerts components missing. MyAlerts is either not installed or installed improperly!';
$l['mention_myalerts_force_enable_fail_not_installed'] = 'MentionMe not installed!';
$l['mention_myalerts_force_enable_fail_no_users'] = 'There were no users to enable alerts for!';
$l['mention_myalerts_force_enable_success'] = 'Mention alerts are enabled for all users';

?>
