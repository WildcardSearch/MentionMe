<?php
/**
 * MentionMe v1.0 for MyBB 1.6.*
 * Copyright © 2012 Wildcard
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

// disallow direct access to this file for security reasons.
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

// checked by other plugin files
define("IN_MENTIONME", true);

// make sure we have access to ACP settings in the main script
global $settings;

// load install routines only if in ACP
if(defined("IN_ADMINCP"))
{
	require_once MYBB_ROOT . "inc/plugins/MentionMe/mention_install.php";
}

// load the alerts functions only if MyAlerts and mention alerts are enabled
if ($settings['myalerts_enabled'] && $settings['myalerts_alert_mention'])
{
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/mention_alerts.php';
}

// main hook
$plugins->add_hook("parse_message_end", "mention_run");
 
/*
 * mention_run()
 *
 * use a regex to either match a double-quoted mention (@"user name") or just grab the @ symbol and everything after it that is qualifies as a word
 */
function mention_run($message)
{
	global $mybb;
	
	// use function Mention__filter to repeatedly process mentions in the current post
	return preg_replace_callback('/@"([^<]+?)"|@([\w .]{' . (int) $mybb->settings['minnamelength'] . ',' . (int) $mybb->settings['maxnamelength'] . '})/', "Mention__filter", $message);
}

/*
 * Mention__filter()
 *
 * matches any mentions of existing user in the post
 *
 * advanced search routines rely on $mybb->settings['mention_advanced_matching'], if set to true mention will match user names with spaces in them without necessitating the use of double quotes.
 */
function Mention__filter(array $match)
{
	global $db, $settings;
	
	// cache names to reduce queries
	static $namecache = array();
	$name_parts = array();
	$shift_count = 0;
	
	// save the original name
	$origName = $match[0];
	
	// if the user entered the mention in quotes then it will be returned in @match[1],
	// if not it will be returned in $match[2]
	array_shift($match);
	while (strlen(trim($match[0])) == 0)
	{
		array_shift($match);
		++$shift_count;
	}
	
	// if the array was shifted then no quotes were used
	if($shift_count)
	{
		// padding is only needed for the @
		$shift_pad = 1;
		
		// split the string into an array of words
		$name_parts = explode(' ', $match[0]);
		
		// and start with first one
		$usernameLower = $name_parts[0];
	}
	else
	{
		// @ and two double quotes
		$shift_pad = 3;
		
		// grab the entire match
		$usernameLower = $match[0];
	}

	// generate a lowercase and db-friendly username to search with
	$usernameLower = my_strtolower(html_entity_decode(trim($usernameLower)));
	
	// if the name is already in the cache . . .
	if (isset($namecache[$usernameLower]))
	{
		// . . . simply return it and save the query
		//  restore any surrounding characters from the original match
		return $namecache[$usernameLower] . substr($origName, strlen($usernameLower) + $shift_pad);
	}
	else
	{
		// lookup the username
		$user = mention_try_name($usernameLower);
		
		// if the username exists . . .
		if($user['uid'] != 0)
		{
			// preserve any surrounding chars
			$left_over = substr($origName, strlen($user['username']) + $shift_pad);
		}
		else
		{
			// if no match and advanced matching is enabled . . .
			if($settings['mention_advanced_matching'])
			{
				// we've already checked the first part, discard it
				array_shift($name_parts);
				
				// if there are more parts and quotes weren't used
				if(!empty($name_parts) && $shift_pad != 3)
				{
					// start with the first part . . .
					$try_this = $usernameLower;

					// . . . loop through each part and try them in serial
					foreach($name_parts as $val)
					{
						// add the next part
						$try_this .= ' ' . $val;
						
						// check the cache for a match to save a query
						if(isset($namecache[$try_this]))
						{
							// preserve any surrounding chars from the original match
							$left_over = substr($origName, strlen($try_this) + $shift_pad);
							
							return $namecache[$try_this] . $left_over;
						}
						
						// check the db
						$user = mention_try_name($try_this);
						
						// if there is a match . . .
						if($user['uid'] != 0)
						{
							// cache the username HTML
							$usernameLower = strtolower($user['username']);
							
							// preserve any surrounding chars from the original match
							$left_over = substr($origName, strlen($user['username']) + $shift_pad);
							
							// and gtfo
							break;
						}
					}
				}
				else
				{
					return $origName;
				}
			}
			else
			{
				return $origName;
			}
		}
		
		// set up the username link so that it displays correctly for the display group of the user
		$username = htmlspecialchars_uni($user['username']);
		$usergroup = $user['usergroup'];
		$uid = $user['uid'];
		$displaygroup = $user['displaygroup'];
		$username = format_name($username, $usergroup, $displaygroup);
		$link = get_profile_link($user['uid']);
		
		// and return the mention
		// the HTML id property is used to store the uid of the mentioned user for MyAlerts (if installed)
		$namecache[$usernameLower] = "@<a id=\"mention_$uid\" href=\"{$link}\">{$username}</a>";
		return $namecache[$usernameLower] . $left_over;
	}
}

/*
 * mention_try_name()
 *
 * 
 */
function mention_try_name($username = '')
{
	global $db;
	
	static $name_list = array();
	
	if($username)
	{
		$user_query = $db->simple_select("users", "uid, username, usergroup, displaygroup", "LOWER(username)='" . $db->escape_string(strtolower($username)) . "'", array('limit' => 1));
		
		if($db->num_rows($user_query) === 1)
		{
			return $db->fetch_array($user_query);
		}
	}
	return false;
}

?>
