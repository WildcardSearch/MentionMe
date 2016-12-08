<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file provides functions for mention_install.php
 */

/*
 * mention_generate_postbit_buttons()
 *
 * @param - $hard - (bool) true to overwrite any existing images
 * @return: n/a
 */
function mention_generate_postbit_buttons($hard = false)
{
	global $mybb, $db;

	$all_dirs = array();
	$query = $db->simple_select('themes', 'pid, properties');
	while($theme = $db->fetch_array($query))
	{
		$properties = unserialize($theme['properties']);
		if($theme['pid'] == 0)
		{
			$master_dir = $properties['imgdir'];
		}
		$all_dirs[] = $properties['imgdir'];
	}

	require_once MYBB_ROOT . 'inc/plugins/MentionMe/button_images.php';
	foreach($all_dirs as $dir)
	{
		$path = MYBB_ROOT . $dir . "/{$mybb->settings['bblanguage']}";
		if(is_dir($path))
		{
			foreach(array("postbit_button" => 'postbit_multi_mention.gif', "postbit_button_on" => 'postbit_multi_mention_on.gif') as $key => $filename)
			{
				$full_path = "{$path}/{$filename}";
				if(!file_exists($full_path) || $hard)
				{
					file_put_contents($full_path, $$key);
				}
			}
		}
	}
}

/*
 * versioning
 */

/*
 * mention_get_cache_version()
 *
 * check cached version info
 *
 * derived from the work of pavemen in MyBB Publisher
 *
 * @return: (int/string) the version
 */
function mention_get_cache_version()
{
	// get currently installed version, if there is one
	$version = MentionMeCache::get_instance()->read('version');
	if(trim($version))
	{
        return trim($version);
	}
    return 0;
}

/*
 * mention_set_cache_version()
 *
 * set cached version info
 *
 * derived from the work of pavemen in MyBB Publisher
 *
 * @return: (bool) true on success
 */
function mention_set_cache_version()
{
	// get version from this plugin file
	$info = mention_info();

	// update version cache to latest
	MentionMeCache::get_instance()->update('version', $info['version']);
    return true;
}

/*
 * mention_unset_cache_version()
 *
 * remove cached version info
 *
 * derived from the work of pavemen in MyBB Publisher
 *
 * @return: (bool) true on success
 */
function mention_unset_cache_version()
{
	MentionMeCache::get_instance()->clear();
    return true;
}

/*
 * MyAlerts
 */

/*
 * mention_myalerts_integrate()
 *
 * build the single ACP setting and add it to the MyAlerts group
 *
 * @return: n/a
 */
function mention_myalerts_integrate()
{
	global $db, $lang, $cache;

	if(!$lang->mention)
	{
		$lang->load('mention');
	}

	
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode("mention");
        $alertType->setEnabled(true);
        $alertTypeManager->add($alertType);

}

/* mention_get_myalerts_status()
 *
 * used by _info to verify the mention MyAlerts setting
 *
 * @return: (bool) true if MyAlerts installed, false if not
 */
function mention_get_myalerts_status()
{
	global $db;

	if($db->table_exists('alert_types'))
	{
		$query = $db->simple_select('alert_types', "*", "code='mention'");
		return ($db->num_rows($query) == 1);
	}
	return false;
}

?>
