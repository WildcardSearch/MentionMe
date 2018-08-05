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
$l['mention_task_description'] = 'Caches active user names mention links to conserve queries during daily use.';
$l['mention_task_success'] = 'The MentionMe name cache task ran successfully. Going back {1} days, {2} users were stored at a total cache size of {3}.';
$l['mention_task_fail'] = 'MentionMe found no users to cache information for.';

// settings
$l['mention_description'] = 'Display @mentions with links (and MyAlerts if installed)';
$l['mention_plugin_settings'] = 'Plugin Settings';
$l['mention_plugin_settings_title'] = 'MentionMe Settings';
$l['mention_settingsgroup_description'] = 'Enable or disable advanced matching';

$l['mention_auto_complete_title'] = 'Auto-Complete Mentions?';
$l['mention_auto_complete_description'] = 'YES (default) to autocomplete mentions as they are typed on showthread in Quick Reply and full post/edit pages';

$l['mention_max_items_title'] = 'Maximum Items In Popup';
$l['mention_max_items_description'] = 'If autocomplete is used, this setting will limit the size of the popup';

$l['mention_min_width_title'] = 'Minimum Width';
$l['mention_min_width_description'] = 'If autocomplete is used, this setting will provide a minimum width for the popup, in pixels';

$l['mention_cache_time_title'] = 'Cache Cut-off Time';
$l['mention_cache_time_description'] = 'The task caches usernames based on when they were last active. In days, specify how far back to go. (Large forums should stick with low numbers to reduce the size of the namecache)';

$l['mention_get_thread_participants_title'] = 'Retrieve Thread Participants?';
$l['mention_get_thread_participants_description'] = 'YES (default) to include and proritize highly names of users who have participated in the current thread';

$l['mention_max_thread_participants_title'] = 'Maximun Thread Participants?';
$l['mention_max_thread_participants_description'] = 'The maximum amount of recent posters to prioritize in the autocomplete popup';

$l['mention_full_text_search_title'] = 'Full Text Search?';
$l['mention_full_text_search_description'] = 'YES to match characters in the autocomplete popup anywhere in the username, NO (default) to search for usernames that start with the typed characters';

$l['mention_show_avatars_title'] = 'Show User Avatars?';
$l['mention_show_avatars_description'] = 'YES (default) to show user avatars in the autocomplete popup, NO to show usernames only';

$l['mention_lock_selection_title'] = 'Lock Selection To Top?';
$l['mention_lock_selection_description'] = 'YES (default) to keep the selected item on top when possible in the autocomplete popup, NO to scroll freely';

$l['mention_add_postbit_button_title'] = 'Add a Postbit Button?';
$l['mention_add_postbit_button_description'] = 'YES to add a button to each post allowing users to tag multiple members to mention (NO by default)';

$l['mention_multiple_title'] = 'Multiple Mentions?';
$l['mention_multiple_description'] = 'YES (default) to mimic the multi-quote feature or NO to instantly insert the mention on click<br /><br /><strong>The postbit button setting must be set to YES for this setting to take effect</strong>';

$l['mention_format_names_title'] = 'Format Usernames?';
$l['mention_format_names_desc'] = 'YES (default) to format user names according to their display group, NO to format mentions as plain links';

$l['mention_display_symbol_title'] = 'Display Symbol';
$l['mention_display_symbol_desc'] = 'Set this to @ or another symbol to use to prefix mentions, leave blank for no prefix';

$l['mention_open_link_in_new_window_title'] = 'Open Mentions In a New Window/Tab?';
$l['mention_open_link_in_new_window_desc'] = 'YES to add open mention links in a new window or tab, NO (default) to open mention links in the same window or tab';

$l['mention_minify_js_title'] = 'Minify JavaScript?';
$l['mention_minify_js_desc'] = 'YES (default) to serve client-side scripts minified to increase performance, NO to serve beautiful, commented code ;)';

// MyAlerts
$l['mention_myalerts_acpsetting_description'] = 'Alerts for mentions?';
$l['mention_myalerts_integration_message'] = 'MyAlerts is detected as installed but has not yet been integrated with MentionMe! Click the link below to integrate.';
$l['mention_myalerts_integrate_link'] = 'Integrate With MyAlerts';
$l['mention_myalerts_successfully_integrated'] = 'MentionMe has been successfully integrated with MyAlerts';

// rebuild name cache
$l['mention_rebuild_name_cache_description'] = 'rebuild the cached user data when users change names/display groups';
$l['mention_rebuild_name_cache_title'] = 'Rebuild Name Cache';
$l['mention_rebuild_name_cache_success'] = 'Successfully updated the name cache information.';
$l['mention_rebuild_name_cache_error'] = 'Could not successfully rebuild the name cache.';

?>
