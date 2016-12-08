<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this script provides MyAlerts routines for mention.php
 */

// disallow direct access to this file for security reasons.
if(!defined('IN_MYBB') || !defined('IN_MENTIONME'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if(!defined('MYALERTS_PLUGIN_PATH'))
{
    define('MYALERTS_PLUGIN_PATH', MYBB_ROOT . 'inc/plugins/MyAlerts/');
}


/* mention_myalerts_datahandler_post_update()
 *
 * create alerts when users edit a post and add a new mention. try to avoid sending any duplicate alerts.
 *
 * @param - $this_post is an object containing post info
 * @return: n/a
 */
$plugins->add_hook("datahandler_post_update", "mention_myalerts_datahandler_post_update");
function mention_myalerts_datahandler_post_update($this_post)
{
    global $db, $mybb, $post;

    // grab the post data
    $message = $this_post->data['message'];
    $fid = (int) $this_post->data['fid'];
    $tid = (int) $this_post->data['tid'];
    $pid = (int) $this_post->data['pid'];
    $post_uid = (int) $post['uid'];
    $edit_uid = (int) $mybb->user['uid'];
    $subject = $post['subject'];

    // if another user is editing (mod) don't do alerts
    if ($edit_uid != $post_uid) {
        return;
    }

    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('mention');
    $alerts = array();

    // get all mentions
    $matches = array();
    mention_find_in_post($message, $matches);

    // no results, no alerts
    if(!is_array($matches) || empty($matches))
    {
        return;
    }

    // avoid duplicate mention alerts
    $mentioned_already = array();

    // loop through all matches (if any)
    foreach($matches as $val)
    {
        // skip blank entries
        if(!$val[0])
        {
            continue;
        }

        $uid = (int) $val[1];
        $username = $val[3];

        // prevent multiple alerts for duplicate mentions in the post and
        // the user mentioning themselves
        if($mentioned_already[$uid] || $edit_uid == $uid )
        {
            continue;
        }

        // if the user was already alerted for being mentioned in this post
        // do not create a duplicate
        $mentioned_already[$uid] = true;
        $already_alerted = false;

        //Check to see if user has permission to see this alert
        if(!mention_can_view($username, $to_uid, $from_uid, $fid))
        {
            // back away slowly . . .
            return;
        }

        $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $post_uid, $alertType, $tid);
        $alert->setExtraDetails(
            array(
                'thread_title' => $subject,
                'pid' => $pid,
                'tid' => $tid
                )
            );
        $alerts[] = $alert;
    }
    
    if (!empty($alerts)) {
        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlerts($alerts);
    }
}

/* mention_myalerts_do_newreply_end()
 *
 * check posts for mentions when they are initially created and
 * create alerts if appropriate
 *
 * @return: n/a
 */
$plugins->add_hook('newreply_do_newreply_end', 'mention_myalerts_do_newreply_end');
$plugins->add_hook('newthread_do_newthread_end', 'mention_myalerts_do_newreply_end');
function mention_myalerts_do_newreply_end()
{
    global $mybb, $pid, $tid, $post, $thread, $fid;

    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('mention');
    $alerts = array();

    // if creating a new thread the message comes from $_POST
    if($mybb->input['action'] == "do_newthread" && $mybb->request_method == "post")
    {
        $message = $mybb->input['message'];
        $subject = $mybb->input['subject'];
    }
    // otherwise the message comes from $post['message'] and the $tid comes from $thread['tid']
    else
    {
        $message = $post['message'];
        $tid = (int) $thread['tid'];
        $subject = $thread['subject'];
    }

    $fromUser = (int) $mybb->user['uid'];

    // get all mentions
    $matches = array();
    mention_find_in_post($message, $matches);


    // no matches, no alerts
    if(!is_array($matches) || empty($matches))
    {
        return;
    }

    // avoid duplicate mention alerts
    $mentioned_already = array();

    // loop through all matches (if any)
    foreach($matches as $val)
    {
        // if there are matches, create alerts
        if(!$val[0])
        {
            continue;
        }

        $uid = (int) $val[1];
        $username = $val[3];

        // create an alert if MyAlerts and mention alerts are enabled and prevent multiple alerts for duplicate mentions in the post and the user mentioning themselves.
        if($mybb->user['uid'] == $uid || $mentioned_already[$uid])
        {
            continue;
        }

        // attempt to create the mention alert (does forum permissions check)

        $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $uid, $alertType, $tid);
        $alert->setExtraDetails(
            array(
                'thread_title' => $subject,
                'pid' => $pid,
                'tid' => $tid
                )
            );
        $alerts[] = $alert;

        $mentioned_already[$uid] = true;
    }

    if (!empty($alerts)) {
        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlerts($alerts);
    }
}

$plugins->add_hook('global_start','mention_myalerts_display');
function mention_myalerts_display() {
    global $mybb, $lang;

    if($mybb->user['uid']) {
    
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        $formatterManager->registerFormatter(new MybbStuff_MyAlerts_Formatter_MentionFormatter($mybb, $lang, 'mention'));
    }
}

class MybbStuff_MyAlerts_Formatter_MentionFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
    {
        $alertContent = $alert->getExtraDetails();

        return $this->lang->sprintf(
            $this->lang->mention_alert,
            $outputAlert['from_user'],
            $alertContent['thread_title'],
            $outputAlert['dateline']
            );
    }

    public function init()
    {
        if (!$this->lang->mention) {
            $this->lang->load('mention');
        }
    }

    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
    {
        $alertContent = $alert->getExtraDetails();

        $postLink = $this->mybb->settings['bburl'] . '/' . get_post_link(
                (int) $alertContent['pid'],
                (int) $alertContent['tid']
            ) . '#pid' . (int) $alertContent['pid'];

        return $postLink;

    }
}

/* mention_find_in_post()
 *
 * find all mentions in the current post that are not within quote tags
 *
 * @param - $message - (string)
 * @param - &$matches - (array) a reference to an array to store matches in
 * @return: n/a
 */
function mention_find_in_post($message, &$matches)
{
    global $mybb;

    // no alerts for mentions inside quotes so strip the quoted portions prior to detection
    $message = mention_strip_quotes($message);

    // do the replacement in the message
    $message =  mention_run($message);

    // match all the mentions in this post
    $pattern = '#@<a id="mention_(.*?)" href="(.*?)">(.*?)</a>#is';
    preg_match_all($pattern, $message, $matches, PREG_SET_ORDER);

}

/* mention_strip_quotes()
 *
 * strips all quotes and their content from the post
 *
 * @param - $message is the content of the post
 * @return: (string) the altered message
 */
function mention_strip_quotes($message)
{
    global $lang, $templates, $theme, $mybb;

    // kill BB code quoted portions of the message
    $pattern = array(
        "#\[quote=([\"']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#si",
        "#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si"
    );

    do {
        $message = preg_replace($pattern, '', $message, -1, $count);
    } while($count);

    // kill HTML block quoted portions of the message
    $find = array(
        "#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#",
        "#(\r\n*|\n*)<\/blockquote>#"
    );
    return preg_replace($find, '', $message);
}

/*
 * mention_can_view()
 *
 * determine whether a user can view a forum and ultimately whether they can receive
 * an alert initiated by a mention in the given forum
 *
 * @param - $username - (string) username
 * @param - $uid - (int) uid of the user receiving the alert (potentially)
 * @param - $from_uid - (int) uid of the user sending the alert (potentially)
 * @param - $fid - (int) id of the forum in which the mention occurred
 * @return: (bool) true to allow, false to deny
 */
function mention_can_view($username, $uid, $from_uid, $fid)
{
    global $cache;
    static $name_cache, $mycache;

    // cache names to reduce queries
    if($mycache instanceof MentionMeCache == false)
    {
        $mycache = MentionMeCache::get_instance();
    }

    if(!isset($name_cache))
    {
        $name_cache = $mycache->read('namecache');
    }

    $username = strtolower($username);

    /* if the user name is in the cache (same one we use for mention
     * display in showthread.php) . . .
     */
    if(isset($name_cache[$username]))
    {
        // use the stored values
        $user = $name_cache[$username];
    }
    else
    {
        global $db;
        $query = $db->simple_select('users', 'uid, username, usergroup, displaygroup, additionalgroups, ignorelist', "uid='{$uid}'");
        $user = $db->fetch_array($query);
        $name_cache[$username] = $user;
        $mycache->update('namecache', $name_cache);
    }

    // don't alert if mentioning user is on mentioned users ignore list
    $ignore_list = explode(',', $user['ignorelist']);
    if(in_array($from_uid, $ignore_list))
    {
        return false;
    }

    $forum_permissions = $cache->read('forumpermissions');

    // if there are no restrictions on the forum then anyone can view it
    if(empty($forum_permissions[$fid]) || $forum_permissions[$fid] = 0)
    {
        return true;
    }

    $users_groups = mention_compile_groups($user);

    // admins can see everything
    if(in_array(4, $users_groups))
    {
        return true;
    }

    // check for permissions in all the user's groups
    foreach($users_groups as $gid)
    {
        // empty 'displaygroup' and 'additionalgroups' are 0 or blank, skip them
        if((int) $gid == 0)
        {
            continue;
        }

        // the first 'yes' we hear gets us out of the loop with a valid return
        if (!isset($forum_permissions[$fid][$gid]) ||
            $forum_permissions[$fid][$gid] == 0 ||
            $forum_permissions[$fid][$gid]['canview'] ||
            $forum_permissions[$fid][$gid]['canviewthreads'])
        {
            return true;
        }
    }
    return false; // :( u can no lookie
}

/*
 * mention_compile_groups()
 *
 * indiscriminately dump the user array's three group-related fields into a single array
 *
 * @param - $user - (array) an array of the user's info
 * @return: an unclean array of all the users associated group ids
 */
function mention_compile_groups($user)
{
    return array_merge(array($user['usergroup']), explode(',', $user['additionalgroups']), array($user['displaygroup']));
}

?>
