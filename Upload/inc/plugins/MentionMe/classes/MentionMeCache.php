<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper to handle our plugin's cache
 */

class MentionMeCache extends WildcardPluginCache010100
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
