<?php
/**
 * This is an upgrade script for MentionMe
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

	// versioning is introduced in 1.6 so just check everything
	if(version_compare($old_version, '1.6', '<') || $old_version == '' || $old_version == 0)
	{
		global $db;
		
		// check settings
		mention_build_settings();

		// if MyAlerts is installed . . .
		if($db->table_exists('alerts') && !mention_get_alert_setting())
		{
			// make sure those settings are up-to-date
			build_myalerts_settings();
		}

		// just in case
		rebuild_settings();
	}

?>
