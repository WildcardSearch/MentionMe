<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file defines an interface for the caching class
 */

interface WildcardPluginCacheInterface010100
{
	public function read($key);
	public function update($key, $val, $hard = false);
	public function save();
	public function clear($hard = false);
	public function getVersion();
	public function setVersion($version);
}

?>
