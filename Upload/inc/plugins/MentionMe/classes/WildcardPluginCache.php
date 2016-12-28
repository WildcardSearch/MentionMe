<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file defines a wrapper class for the caching functions
 */

abstract class WildcardPluginCache
{
	/*
	 * @const  version
	 */
	const VERSION = '1.1';

	/*
	 * @var  array cache data
	 */
	protected $cacheData = array();

	/*
	 * create a new cache wrapper instance
	 *
	 * @return void
	 */
	public function __construct()
	{
		global $cache;
		$this->cacheData = $cache->read($this->cacheKey);
	}

	/*
	 * retrieve an individual cache entry
	 *
	 * @param string the name of the entry
	 */
	public function read($key)
	{
		if ($this->subKey &&
			isset($this->cacheData[$this->subKey][$key])) {
			return $this->cacheData[$this->subKey][$key];
		} elseif (isset($this->cacheData[$key])) {
			return $this->cacheData[$key];
		}
		return false;
	}

	/*
	 * update the value of a single cache entry
	 *
	 * @param string the name of the entry
	 * @param mixed the value of the entry
	 * @param bool true to save immediately or
	 * 	false (default) to wait till shut down
	 * @param bool true [default] to update the entire cache in the db
	 */
	public function update($key, $val, $hard = false)
	{
		if ($this->subKey) {
			$this->cacheData[$this->subKey][$key] = $val;
		} else {
			$this->cacheData[$key] = $val;
		}
		$this->hasChanged($hard);
	}

	/*
	 * save the entire cache to the db
	 *
	 * return void
	 */
	public function save()
	{
		global $cache;
		$cache->update($this->cacheKey, $this->cacheData);
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
		if ($this->subKey) {
			$this->cacheData[$this->subKey] = null;
		} else {
			$this->cacheData = null;
		}
		$this->hasChanged($hard);
	}

	/*
	 * mark the cache as in need of saving if shut
	 * down functionality is enabled, or save immediately
	 * if not
	 *
	 * @param bool true to clear and save immediately or
	 * false (default) to wait till shut down
	 * return void
	 */
	protected function hasChanged($hard = false)
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