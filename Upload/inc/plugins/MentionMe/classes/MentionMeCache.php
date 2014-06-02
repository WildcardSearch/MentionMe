<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file defines a wrapper class for the caching functions
 */

class MentionMeCache
{
	protected $cache_key = 'wildcard_plugins';
	protected $sub_key = 'mentionme';
	protected $cache_data = array();

	/**
	 * get_instance()
	 *
	 * return an instance of the child class
	 *
	 * @return: n/a
	 */
	static public function get_instance()
	{
		static $instance;
		if(!isset($instance))
		{
			$instance = new MentionMeCache;
		}
		return $instance;
	}

	/*
	 * __construct()
	 *
	 * create a new cache wrapper instance
	 *
	 * @return: n/a
	 */
	public function __construct()
	{
		global $cache;
		$this->cache_data = $cache->read($this->cache_key);
	}

	/*
	 * read()
	 *
	 *	retrieve an individual cache entry
	 *
	 * @param - $key - (string) the name of the entry
	 */
	public function read($key)
	{
		if($this->sub_key && isset($this->cache_data[$this->sub_key][$key]))
		{
			return $this->cache_data[$this->sub_key][$key];
		}
		elseif(isset($this->cache_data[$key]))
		{
			return $this->cache_data[$key];
		}
		return false;
	}

	/*
	 * update()
	 *
	 * update the value of a single cache entry
	 *
	 * @param - $key (string) the name of the entry
	 * @param - $val (mixed) the value of the entry
	 * @param - $hard (bool) true to save immediately or
	 * false (default) to wait till shut down
	 * @param - $store - (bool) true [default] to update the entire cache in the db
	 */
	public function update($key, $val, $hard = false)
	{
		if($this->sub_key)
		{
			$this->cache_data[$this->sub_key][$key] = $val;
		}
		else
		{
			$this->cache_data[$key] = $val;
		}
		$this->has_changed($hard);
	}

	/*
	 * save()
	 *
	 * save the entire cache to the db
	 *
	 * @return  n/a
	 */
	public function save()
	{
		global $cache;
		$cache->update($this->cache_key, $this->cache_data);
	}

	/*
	 * clear()
	 *
	 * clear the entire cache
	 *
	 * @param - $hard (bool) true to clear and save immediately or
	 * false (default) to wait till shut down
	 * @return  n/a
	 */
	public function clear($hard = false)
	{
		if($this->sub_key)
		{
			$this->cache_data[$this->sub_key] = null;
		}
		else
		{
			$this->cache_data[$key] = null;
		}
		$this->has_changed($hard);
	}

	/*
	 * has_changed()
	 *
	 * mark the cache as in need of saving if shut down functionality is
	 * enabled, or save immediately if not
	 *
	 * @param - $hard (bool) true to clear and save immediately or
	 * false (default) to wait till shut down
	 * @return  n/a
	 */
	protected function has_changed($hard = false)
	{
		global $mybb;
		if($hard || !$mybb->use_shutdown)
		{
			$this->save();
			return;
		}

		add_shutdown(array($this, 'save'));
	}
}

?>
