<?php

if(!class_exists('WildcardPluginCache'))
{
	require_once MYBB_ROOT . 'inc/plugins/MentionMe/classes/WildcardPluginCache.php';
}

// concrete cache for MentionMe
class MentionMeCache extends WildcardPluginCache
{
	protected $cache_key = 'wildcard_plugins';
	protected $sub_key = 'mentionme';
}

?>
