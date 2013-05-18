<?php
/**
 * This file contains language definitions for MentionMe (English)
 *
 * Copyright © 2013 Wildcard
 * http://www.rantcentralforums.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses
 */

$l['mention_description'] = 'Display @mentions with links (and MyAlerts if installed)';

// advanced name matching
$l['mention_advanced_matching'] = 'Enable Advanced Matching?';
$l['mention_advanced_matching_desc'] = 'This option allows usernames with whitespace to be processed by MentionMe without the necessity of enclosing usernames in double quotes.<br /><br />This feature can greatly increase the server load and is not recommended for large forums.';
$l['mention_settingsgroup_description'] = 'Enable or disable advanced matching';

// task
$l['mention_task_name'] = 'MentionMe Name Caching';
$l['mention_task_description'] = 'caches active usernames mention links to conserve queries during daily use';

// MyAlerts
$l['myalerts_mention'] = '{1} mentioned you in this thread: <a href="{2}">{3}</a>. ({4})';
$l['myalerts_setting_mention'] = 'Receive alert when mentioned in a post?';
$l['mention_myalerts_acpsetting_description'] = 'Alerts for mentions?';
$l['myalerts_help_alert_types_mentioned'] = '<strong>Mentioned in a post</strong>
<p>
	This alert type is received whenever another member of the site mentions you within a post anywhere on the site using <a href="http://mods.mybb.com/view/mentionme"><span style="color: #32CD32;"><strong>MentionMe</strong></span></a> Twitter-style mention tags.
</p>';
$l['mention_myalerts_integration_message'] = 'MyAlerts is detected as installed but has not yet been integrated with MentionMe! You must uninstall and reinstall the plugin to receive mention alerts.';
$l['mention_myalerts_working'] = 'MentionMe has been successfully integrated with MyAlerts';

?>
