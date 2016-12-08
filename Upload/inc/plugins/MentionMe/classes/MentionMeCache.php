<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file defines a wrapper class for the caching functions
 */

class MentionMeCache
{
	/*
	 * @var  string cache key
	 */
	protected $cache_key = 'wildcard_plugins';

	/*
	 * @var  string cache sub key
	 */
	protected $sub_key = 'mentionme';

	/*
	 * @var  array cache data
	 */
	protected $cache_data = array();

	/**
	 * return an instance of the child class
	 *
	 * @return void
	 */
	static public function get_instance()
	{
		static $instance;
		if (!isset($instance)) {
			$instance = new MentionMeCache;
		}
		return $instance;
	}

	/*
	 * create a new cache wrapper instance
	 *
	 * @return void
	 */
	public function __construct()
	{
		global $cache;
		$this->cache_data = $cache->read($this->cache_key);
	}

	/*
	 * retrieve an individual cache entry
	 *
	 * @param string the name of the entry
	 */
	public function read($key)
	{
		if ($this->sub_key &&
			isset($this->cache_data[$this->sub_key][$key])) {
			return $this->cache_data[$this->sub_key][$key];
		} elseif (isset($this->cache_data[$key])) {
			return $this->cache_data[$key];
		}
		return false;
	}

	/*
	 * update the value of a single cache entry
	 *
	 * @param string the name of the entry
	 * @param mixed the value of the entry
	 * @param bool true to save immediately or
	 * false (default) to wait till shut down
	 * @param bool true [default] to update the entire cache in the db
	 */
	public function update($key, $val, $hard = false)
	{
		if ($this->sub_key) {
			$this->cache_data[$this->sub_key][$key] = $val;
		} else {
			$this->cache_data[$key] = $val;
		}
		$this->has_changed($hard);
	}

	/*
	 * save the entire cache to the db
	 *
	 * return void
	 */
	public function save()
	{
		global $cache;
		$cache->update($this->cache_key, $this->cache_data);
	}

	/*
	 * clear the entire cache
	 *
	 * @param bool true to clear and save immediately or
	 * false (default) to wait till shut down
	 * return void
	 */
	public function clear($hard = false)
	{
		if ($this->sub_key) {
			$this->cache_data[$this->sub_key] = null;
		} else {
			$this->cache_data[$key] = null;
		}
		$this->has_changed($hard);
	}

	/*
	 * mark the cache as in need of saving if shut down functionality is
	 * enabled, or save immediately if not
	 *
	 * @param bool true to clear and save immediately or
	 * false (default) to wait till shut down
	 * return void
	 */
	protected function has_changed($hard = false)
	{
		global $mybb;
		if ($hard ||
			!$mybb->use_shutdown) {
			$this->save();
			return;
		}

		add_shutdown(array($this, 'save'));
	}
}

?>
