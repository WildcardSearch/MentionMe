<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file defines a formatter for MyAlerts
 */

class MentionMeFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
	/*
	 * @const  version
	 */
	const VERSION = '1';

	/**
	 * creates a printable version of the mention alert
	 *
	 * @param  object the alert
	 * @param  array the details
	 * @return string the alert text
	 */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
    {
        global $lang;

		$alertContent = $alert->getExtraDetails();

        return $this->lang->sprintf(
            $this->lang->myalerts_mention_alert,
            $outputAlert['from_user'],
            $alertContent['thread_title'],
            $outputAlert['dateline']
            );
    }

	/**
	 * load the language variables
	 */
    public function init()
    {
        if (!$this->lang->mention) {
            $this->lang->load('mention');
        }
    }

	/**
	 * build the post link
	 *
	 * @param  object the alert
	 * @return string the link HTML
	 */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
    {
        $alertContent = $alert->getExtraDetails();

        $postLink = $this->mybb->settings['bburl'] . '/' . get_post_link((int) $alertContent['pid'], (int) $alertContent['tid']) .
			'#pid' .
			(int) $alertContent['pid'];

        return $postLink;
    }
}

?>
