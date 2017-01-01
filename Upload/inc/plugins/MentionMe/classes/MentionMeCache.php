<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper to handle our plugin's cache
 */

if (!class_exists('WildcardPluginCache')) {
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/WildcardPluginCache.php';
}

class MentionMeCache extends WildcardPluginCache
{
	/**
	 * @var  string cache key
	 */
	protected $cacheKey = 'wildcard_plugins';

	/**
	 * @var  string cache sub key
	 */
	protected $subKey = 'mentionme';

	/**
	 * @return instance of the child class
	 */
	static public function getInstance()
	{
		static $instance;
		if (!isset($instance)) {
			$instance = new MentionMeCache;
		}
		return $instance;
	}
}

?>
