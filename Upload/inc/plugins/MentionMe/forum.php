<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * forum-side routines
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB') ||
	!defined('IN_MENTIONME')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

mentionMeInitialize();

/**
 * use a regex to either match a double-quoted mention (@"user name")
 * or just grab the @ symbol and everything after it that qualifies as a
 * word and is within the name length range
 *
 * @param  string post contents
 * @return string the message
 */
function mentionMeParseMessage($message)
{
	global $mybb;

	// emails addresses cause issues, strip them before matching
	$emailRegex = "#\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b#i";
	preg_match_all($emailRegex, $message, $emails, PREG_SET_ORDER);
	$message = preg_replace($emailRegex, "<mybb-email>\n", $message);

	/**
	 * use function mentionDetect to repeatedly process mentions in the current post
	 *
	 * quoted
	 */
	$message = preg_replace_callback('#@([\'|"|`])(?P<quoted>[^<\n]+?)\1#u', 'mentionDetect', $message);

	/**
	 * unquoted
	 */
	$message = preg_replace_callback('#@(?P<unquoted>[\w.]{' . (int) $mybb->settings['minnamelength'] . ',' . (int) $mybb->settings['maxnamelength'] . '})#u', 'mentionDetect', $message);

	// now restore the email addresses
	foreach ($emails as $email) {
		$message = preg_replace("#\<mybb-email>\n?#", $email[0], $message, 1);
	}
	return $message;
}

/**
 * matches any mentions of existing users in the post
 *
 * advanced search routines rely on
 * $mybb->settings['mention_advanced_matching'], if set to true
 * mention will match user names with spaces in them without
 * necessitating the use of double quotes.
 *
 * @param  array generated by preg_replace_callback()
 * @return string mention HTML
 */
function mentionDetect($match)
{
	global $db, $mybb;
	static $nameCache, $myCache;
	$nameParts = array();
	$unquoted = 0;

	$cacheChanged = false;

	// cache names to reduce queries
	if ($myCache instanceof MentionMeCache == false) {
		$myCache = MentionMeCache::getInstance();
	}

	if (!isset($nameCache)) {
		$nameCache = $myCache->read('namecache');
	}

	/*
	 * if the user entered the mention in quotes then it
	 * will be returned in $match['quoted'], if not it will
	 * be returned in $match['unquoted']
	 */
	if (strlen(trim($match['quoted'])) >= $mybb->settings['minnamelength']) {
		$originalName = html_entity_decode($match['quoted']);
	} elseif (strlen(trim($match['unquoted'])) >= $mybb->settings['minnamelength']) {
		$originalName = html_entity_decode($match['unquoted']);
		$unquoted = true;
	} else {
		return $match[0];
	}

	$match[0] = trim(mb_strtolower($originalName));

	// if the name is already in the cache . . .
	if (isset($nameCache[$match[0]])) {
		return mentionBuild($nameCache[$match[0]]);
	}

	// if the array was shifted then no quotes were used
	if ($unquoted) {
		// no padding necessary
		$padding = 0;

		// split the string into an array of words
		$nameParts = explode(' ', $match[0]);

		// add the first part
		$username = $nameParts[0];

		// if the name part we have is shorter than the minimum user name length (set in ACP) we need to loop through all the name parts and keep adding them until we at least reach the minimum length
		while (strlen($username) < $mybb->settings['minnamelength'] &&
			!empty($nameParts)) {
			// discard the first part (we have it stored)
			array_shift($nameParts);
			if (strlen($nameParts[0]) == 0) {
				// no more parts?
				break;
			}

			// if there is another part add it
			$username .= ' ' . $nameParts[0];
		}

		if (strlen($username) < $mybb->settings['minnamelength']) {
			return $originalName;
		}
	} else {
		// @ and two double quotes
		$padding = 3;

		// grab the entire match
		$username = $match[0];
	}

	// if the name is already in the cache . . .
	if (isset($nameCache[$username])) {
		// . . . simply return it and save the query
		//  restore any surrounding characters from the original match
		return mentionBuild($nameCache[$username]) . substr($originalName, strlen($username) + $padding);
	}

	// lookup the user name
	$user = mentionTryName($username);

	// if the user name exists . . .
	if ($user['uid'] != 0) {
		$cacheChanged = true;

		// preserve any surrounding chars
		$trailingChars = substr($originalName, strlen($user['username']) + $padding);
	// if no match and advanced matching is enabled . . .
	} elseif ($mybb->settings['mention_advanced_matching'] &&
		$unquoted) {
		// we've already checked the first part, discard it
		array_shift($nameParts);

		// if there are more parts and quotes weren't used
		if (empty($nameParts) ||
			$padding == 3 ||
			strlen($nameParts[0]) <= 0) {
			// nothing else to try
			return "@{$originalName}";
		}

		// start with the first part . . .
		$nameToTry = $username;

		$isMatched = false;

		// . . . loop through each part and try them in serial
		foreach ($nameParts as $val) {
			// add the next part
			$nameToTry .= ' ' . $val;

			// check the cache for a match to save a query
			if (isset($nameCache[$nameToTry])) {
				// preserve any surrounding chars from the original match
				$trailingChars = substr($originalName, strlen($nameToTry) + $padding);
				return mentionBuild($nameCache[$nameToTry]) . $trailingChars;
			}

			// check the db
			$user = mentionTryName($nameToTry);

			// if there is no match . . .
			if ((int) $user['uid'] == 0) {
				// keep trying
				continue;
			}

			// cache the user name HTML
			$username = mb_strtolower($user['username']);

			// preserve any surrounding chars from the original match
			$trailingChars = substr($originalName, strlen($user['username']) + $padding);

			// and gtfo
			$isMatched = true;
			$cacheChanged = true;
			break;
		}

		if (!$isMatched) {
			// still no matches?
			return "@{$originalName}";
		}
	} else {
		// no match found and advanced matching is disabled
		return "@{$originalName}";
	}

	// store the mention
	$nameCache[$username] = $user;

	// if we had to query for this user's info then update the cache
	if ($cacheChanged) {
		$myCache->update('namecache', $nameCache);
	}

	// and return the mention
	return mentionBuild($user) . $trailingChars;
}

/**
 * build  mention from user info
 *
 * @param  array an associative array of user info
 * @return string mention HTML
 */
function mentionBuild($user)
{
	if (!is_array($user) ||
		empty($user) ||
		strlen($user['username']) == 0) {
		return false;
	}

	global $mybb;

	$username = htmlspecialchars_uni($user['username']);
	if ($mybb->settings['mention_format_names']) {
		// set up the user name link so that it displays correctly for the display group of the user
		$username = format_name($username, $user['usergroup'], $user['displaygroup']);
	}
	$url = $mybb->settings['bburl'] . "/" . get_profile_link($user['uid']);

	// the HTML id property is used to store the uid of the mentioned user for MyAlerts (if installed)
	return <<<EOF
{$mybb->settings['mention_display_symbol']}<a id="mention_{$user['uid']}" href="{$url}" class="mentionme_mention">{$username}</a>
EOF;
}

/**
 * searches the db for a user by name
 *
 * return an array containing user id, user name, user group and display group upon success
 * return false on failure
 *
 * @param  string user name to try
 * @return mixed array the user data or bool false on no match
 */
function mentionTryName($username = '')
{
	/**
	 * create another name cache here to save queries if names
	 * with spaces are used more than once in the same post
	 */
	static $nameList;

	if (!is_array($nameList)) {
		$nameList = array();
	}

	// no user name supplied
	if (!$username) {
		return false;
	}

	$username = mb_strtolower($username);

	// if the name is in this cache (has been searched for before)
	if ($nameList[$username]) {
		// . . . just return the data and save the query
		return $nameList[$username];
	}

	global $db, $mybb;

	$searchname = $db->escape_string($username);

	$fieldList = 'uid, username, usergroup, displaygroup, additionalgroups, ignorelist';
	if ($mybb->settings['mention_show_avatars']) {
		$fieldList .= ', avatar';
	}

	// query the db
	$query = $db->simple_select('users', $fieldList, "LOWER(username)='{$searchname}'", array('limit' => 1));

	// result?
	if ($db->num_rows($query) !== 1) {
		// no matches
		return false;
	}

	// cache the name
	$nameList[$username] = $db->fetch_array($query);

	// and return it
	return $nameList[$username];
}

/**
 * add hooks and include functions only when appropriate
 *
 * return void
 */
function mentionMeInitialize()
{
	global $mybb, $plugins, $lang, $templates, $mentionAutocomplete, $templatelist;

	if (!$lang->mention) {
		$lang->load('mention');
	}

	if (mentionGetMyAlertsStatus()) {
		require_once MYBB_ROOT . 'inc/plugins/MentionMe/alerts.php';
	}

	$addTemplates = '';
	if ($mybb->settings['mention_auto_complete']) {
		$plugins->add_hook('global_intermediate', mentionMeBuildPopup);

		$addTemplates .= ',mentionme_popup';
		if ($mybb->settings['mention_minify_js']) {
			$min = '.min';
		}

		$mentionAutocomplete = <<<EOF
<!-- MentionMe Autocomplete Scripts -->
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/Caret.js/jquery.caret{$min}.js"></script>
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/MentionMe/autocomplete{$min}.js"></script>
<script type="text/javascript">
<!--
	MentionMe.autoComplete.setup({
		lang: {
			instructions: '{$lang->mention_autocomplete_instructions}',
		},
		minLength: '{$mybb->settings['minnamelength']}',
		maxLength: '{$mybb->settings['maxnamelength']}',
		maxItems: '{$mybb->settings['mention_max_items']}',
		tid: '{$mybb->input['tid']}',
		fullText: '{$mybb->settings['mention_full_text_search']}',
		showAvatars: '{$mybb->settings['mention_show_avatars']}',
	});
// -->
</script>
EOF;
	}

	// only add the showthread hook if we are there and we are adding a postbit multi-mention button
	if (THIS_SCRIPT == 'showthread.php' &&
		$mybb->settings['mention_add_postbit_button']) {
		$plugins->add_hook('showthread_start', 'mentionMeShowThreadStart');
		$plugins->add_hook('postbit', 'mentionMePostbit');
		$addTemplates .= ',mentionme_postbit_button,mentionme_quickreply_notice';
	}

	// only add the xmlhttp hook if required
	if (THIS_SCRIPT == 'xmlhttp.php' &&
		$mybb->input['action'] == 'mentionme') {
		$plugins->add_hook('xmlhttp', 'mentionMeXMLHTTP');
	}

	$plugins->add_hook('parse_message', 'mentionMeParseMessage');

	$templatelist .= $addTemplates;
}

/**
 * build the multi-mention postbit button
 *
 * @param  array passed from pluginSystem::run_hooks,
 * an array of the post data
 * @return void
 */
function mentionMePostbit(&$post)
{
	global $mybb, $theme, $lang, $templates, $forumpermissions,
	$fid, $post_type, $thread, $forum;

	if ($mybb->settings['quickreply'] == 0 ||
		$mybb->user['suspendposting'] == 1 ||
		$forumpermissions['canpostreplys'] == 0 ||
		($thread['closed'] == 1 && !is_moderator($fid)) ||
		$forum['open'] == 0 ||
		$post_type ||
		$mybb->user['uid'] == $post['uid']) {
		return;
	}

	// tailor JS to postbit setting
	$js = "javascript:MentionMe.insert('{$post['username']}');";
	if ($mybb->settings['mention_multiple']) {
		$js = "javascript:MentionMe.multi.mention({$post['pid']});";
	}

	eval("\$post['button_mention'] = \"" . $templates->get('mentionme_postbit_button') . "\";");
}

/**
 * handles AJAX for MentionMe
 *
 * @return void
 */
function mentionMeXMLHTTP()
{
	global $mybb;

	$ajaxFunction = "mentionMeXMLHTTP{$mybb->input['mode']}";
	if ($mybb->input['action'] != 'mentionme' ||
		!function_exists($ajaxFunction)) {
		return;
	}

	$ajaxFunction();
	return;
}

/**
 * search for usernames beginning with search text and echo JSON
 *
 * @return void
 */
function mentionMeXMLHTTPnameSearch()
{
	global $mybb, $db, $cache;

	if (!$mybb->input['search']) {
		exit;
	}

	$originalName = trim($mybb->input['search']);
	$name = $db->escape_string($originalName);
	$name = strtr($name,
		array(
			'%' => '=%',
			'=' => '==',
			'_' => '=_')
		);

	$fieldList = 'username';
	if ($mybb->settings['mention_show_avatars']) {
		$fieldList .= ', avatar';
	}

	$fullText = '';
	if ($mybb->settings['mention_full_text_search']) {
		$fullText = '%';
	}

	$query = $db->simple_select('users', $fieldList, "username LIKE '{$fullText}{$name}%' ESCAPE '='");

	if ($db->num_rows($query) == 0) {
		exit;
	}

	$names = array();
	while ($user = $db->fetch_array($query)) {
		$username = mb_strtolower($user['username']);
		if (($fullText === '' &&
			substr($username, 0, strlen($originalName)) === $originalName) ||
			($fullText &&
			strpos($username, $originalName) !== -1)) {
			$names[$username] = $user;
		}
	}

	if (empty($names)) {
		exit;
	}
	$json = json_encode($names);

	// send our headers.
	header('Content-type: application/json');
	echo($json);
	exit;
}

/**
 * retrieve the name cache and echo JSON
 *
 * @return void
 */
function mentionMeXMLHTTPgetNameCache()
{
	global $mybb, $db;

	$nameCache = MentionMeCache::getInstance()->read('namecache');

	$names = array(
		"cached" => array(),
		"inThread" => array(),
	);
	foreach ($nameCache as $key => $data) {
		$names['cached'][$key] = $data;
	}

	$tid = (int) $mybb->input['tid'];
	if ($tid &&
		$mybb->settings['mention_get_thread_participants']) {
		if ($mybb->settings['mention_show_avatars']) {
			$query = $db->write_query("
				SELECT p.username, u.avatar
				FROM {$db->table_prefix}posts p
				LEFT JOIN {$db->table_prefix}users u ON (p.uid=u.uid)
				WHERE p.tid='{$tid}'
				GROUP BY p.username
				ORDER BY p.dateline DESC
			");
		} else {
			$query = $db->simple_select('posts', 'username', "tid='{$tid}'", array("order_by" => 'dateline', "order_dir" => 'DESC', "group_by" => 'username'));
		}

		if ($db->num_rows($query) > 0) {
			while ($user = $db->fetch_array($query)) {
				$key = mb_strtolower($user['username']);
				$names['inThread'][$key] = $user;
			}
		}
	}

	// send our headers.
	header('Content-type: application/json');
	echo(json_encode($names));
	exit;
}

/**
 * retrieve the mentioned user names and echo HTML
 *
 * @return void
 */
function mentionMeXMLHTTPgetMultiMentioned()
{
	global $mybb, $db, $charset;

	// if the cookie does not exist, exit
	if (!array_key_exists('multi_mention', $mybb->cookies)) {
		exit;
	}
	// divide up the cookie using our delimiter
	$mentioned = explode('|', $mybb->cookies['multi_mention']);

	// no values - exit
	if (!is_array($mentioned)) {
		exit;
	}

	// loop through each post ID and sanitize it before querying
	foreach ($mentioned as $post) {
		$mentionedPosts[$post] = (int) $post;
	}

	// join the post IDs back together
	$mentionedPosts = implode(',', $mentionedPosts);

	// fetch unviewable forums
	$unviewableForums = get_unviewable_forums();
	if ($unviewableForums) {
		$unviewableForums = " AND fid NOT IN ({$unviewableForums})";
	}
	$message = '';

	// are we loading all mentioned posts or only those not in the current thread?
	$fromTID = '';
	if (!$mybb->input['load_all']) {
		$tid = (int) $mybb->input['tid'];
		$fromTID = "tid != '{$tid}' AND ";
	}

	// query for any posts in the list which are not within the specified thread
	$mentioned = array();
	$query = $db->simple_select('posts', 'username, fid, visible', "{$fromTID}pid IN ({$mentionedPosts}){$unviewableForums}", array("order_by" => 'dateline'));
	while ($mentionedPost = $db->fetch_array($query)) {
		if (!is_moderator($mentionedPost['fid']) &&
			$mentionedPost['visible'] == 0) {
			continue;
		}

		if ($mentioned[$mentionedPost['username']] != true) {
			$mentionedPost['username'] = html_entity_decode($mentionedPost['username']);

			// find an appropriate quote character based on whether or not the
			// mentioned name includes that character
			$quote = '';
			if (strpos($mentionedPost['username'], '"') === false) {
				$quote = '"';
			} elseif (strpos($mentionedPost['username'], "'") === false) {
				$quote = "'";
			} elseif (strpos($mentionedPost['username'], "`") === false) {
				$quote = "`";
			}

			$message .= "@{$quote}{$mentionedPost['username']}{$quote} ";
			$mentioned[$mentionedPost['username']] = true;
		}
	}

	// send our headers.
	header("Content-type: text/plain; charset={$charset}");
	echo $message;
	exit;
}

/**
 * add the script, the Quick Reply notification <div> and the hidden input
 *
 * @return void
 */
function mentionMeShowThreadStart()
{
	global $mybb, $mentionScript, $mentionQuickReply,
	$mentionedIDs, $lang, $tid, $templates;

	// we only need the extra JS and Quick Reply additions if we are allowing multiple mentions
	if ($mybb->settings['mention_multiple']) {
		$multi = '_multi';
		eval("\$mentionQuickReply = \"" . $templates->get('mentionme_quickreply_notice') . "\";");

		$mentionedIDs = <<<EOF

	<input type="hidden" name="mentioned_ids" value="" id="mentioned_ids" />
EOF;
	}

	if ($mybb->settings['mention_minify_js']) {
		$min = '.min';
	}

	$mentionScript = <<<EOF
<script type="text/javascript" src="jscripts/MentionMe/thread{$multi}{$min}.js"></script>

EOF;
}

/**
 * output the popup HTML
 *
 * @return void
 */
function mentionMeBuildPopup() {
	global $templates, $mentionAutocomplete;

	eval("\$popup = \"" . $templates->get('mentionme_popup') . "\";");
	$mentionAutocomplete .= $popup;
}

?>
