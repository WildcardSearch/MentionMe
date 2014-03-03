<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains data used by classes/installer.php
 */

$settings = array(
	"mention_settings" => array	(
		"group" => array(
			"name" => "mention_settings",
			"title"  => $lang->mention_plugin_settings_title,
			"description" => $lang->mention_settingsgroup_description,
			"disporder"  => "120",
			"isdefault"  => 0
		),
		"settings" => array(
			"mention_add_codebutton" => array(
				"sid" => "NULL",
				"name" => "mention_add_codebutton",
				"title" => $lang->mention_add_codebutton_title,
				"description" => $lang->mention_add_codebutton_description,
				"optionscode" => 'yesno',
				"value" => '1',
				"disporder" => '10'
			),
			"mention_add_postbit_button" => array(
				"sid" => "NULL",
				"name" => "mention_add_postbit_button",
				"title" => $lang->mention_add_postbit_button_title,
				"description" => $lang->mention_add_postbit_button_description,
				"optionscode" => 'yesno',
				"value" => '0',
				"disporder" => '20'
			),
			"mention_multiple" => array(
				"sid" => "NULL",
				"name" => "mention_multiple",
				"title" => $lang->mention_multiple_title,
				"description" => $lang->mention_multiple_description,
				"optionscode" => 'yesno',
				"value" => '0',
				"disporder" => '30'
			),
			"mention_css_buttons" => array(
				"sid" => "NULL",
				"name" => "mention_css_buttons",
				"title" => $lang->mention_css_buttons_title,
				"description" => $lang->mention_css_buttons_description,
				"optionscode" => 'yesno',
				"value" => '0',
				"disporder" => '40'
			),
			"mention_cache_time" => array(
				"sid" => "NULL",
				"name" => "mention_cache_time",
				"title" => $lang->mention_cache_time_title,
				"description" => $lang->mention_cache_time_description,
				"optionscode" => "text",
				"value" => '7',
				"disporder" => '50'
			),
			"mention_advanced_matching" => array(
				"sid" => "NULL",
				"name" => "mention_advanced_matching",
				"title" => $lang->mention_advanced_matching,
				"description" => $lang->mention_advanced_matching_desc,
				"optionscode" => "yesno",
				"value" => '0',
				"disporder" => '60'
			),
		)
	)
);

$templates = array(
	"mentionme" => array(
		"group" => array(
			"prefix" => 'mentionme',
			"title" => $lang->mentionme,
		),
		"templates" => array(
			"mentionme_popup" => <<<EOF
<html>
<head>
<title>{\$mybb->settings['bbname']} - {\$lang->mentionme_popup_title}</title>
{\$headerinclude}
</head>
<body>
<br />
<table border="0" cellspacing="{\$theme['borderwidth']}" cellpadding="{\$theme['tablespace']}" class="tborder">
	<tr>
		<td class="trow1" style="padding: 20px">
			<strong>{\$lang->mentionme_popup_title}</strong><br />{\$lang->mentionme_popup_description}<br />
			<form action="misc.php" method="post">
				<input type="hidden" name="my_post_key" value="{\$mybb->post_code}" />
				<input type="hidden" name="action" value="mentionme" />
				<input type="hidden" name="mode" value="popup" />
				<br /><br />
				<input type="text" class="textbox" name="username" id="username" size="35" maxlength="150" style="width: 95%" />
				<br /><br />
				<div style="text-align: center;">
					<input type="submit" class="button" value="{\$lang->mention_popup_submit}" onclick="if($('username').value == '') { window.close(); return false; }"/>
				</div><br />
				[ <a href="javascript:window.close();">{\$lang->mentionme_popup_close}</a> ]
			</form>
<script type="text/javascript" src="jscripts/autocomplete.js?ver=140"></script>
<script type="text/javascript">
	new autoComplete
	(
		"username",
		"xmlhttp.php?action=get_users",
		{
			valueSpan: "username"
		}
	);
</script>
		</td>
	</tr>
</table>
</body>
</html>
EOF
			,
			"mentionme_postbit_button" => <<<EOF
<a href="{\$js}" style="display: none;" id="multi_mention_link_{\$post['pid']}"><img src="{\$theme['imglangdir']}/postbit_multi_mention.gif" alt="{\$lang->mention_title}" title="{\$lang->mention_title}" id="multi_mention_{\$post['pid']}" /></a>
<script type="text/javascript">
//<!--
	$('multi_mention_link_{\$post['pid']}').style.display = '';
// -->
</script>
EOF
			,
			"mentionme_postbit_button_css" => <<<EOF
<a href="{\$js}" style="display: none;" id="multi_mention_link_{\$post['pid']}" title="{\$lang->mention_title}" class="postbit_multimention">{\$lang->mention_title}</a>
<script type="text/javascript">
    $('multi_mention_link_{\$post['pid']}').style.display = '';
</script>
EOF
			,
			"mentionme_quickreply_notice" => <<<EOF
					<div class="editor_control_bar" style="width: 95%; padding: 4px; margin-top: 3px; display: none;" id="quickreply_multi_mention">
						<span class="smalltext">
							{\$lang->mention_posts_selected} <a href="./newreply.php?tid={\$tid}&amp;load_all_mentions=1" onclick="return MentionMe.loadMultiMentioned();">{\$lang->mention_users_now}</a> {\$lang->or} <a href="javascript:MentionMe.clearMultiMentioned();">{\$lang->quickreply_multiquote_deselect}</a>.
						</span>
					</div>
EOF
		),
	),
);

?>
