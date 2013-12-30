<?php
/*
 * Plugin Name: MentionMe for MyBB 1.6.x
 * Copyright 2013 WildcardSearch
 * http://www.wildcardsworld.com
 *
 * this file contains data used by classes/installer.php
 */

$settings = array
(
	"mention_settings" => array
	(
		"group" => array
		(
			"name" 				=> "mention_settings",
			"title" 					=> $lang->mention_plugin_settings_title,
			"description" 		=> $lang->mention_settingsgroup_description,
			"disporder" 			=> "120",
			"isdefault" 			=> "no"
		),
		"settings" => array
		(
			"mention_advanced_matching" => array
			(
				"sid"						=> "NULL",
				"name"					=> "mention_advanced_matching",
				"title"					=> $lang->mention_advanced_matching,
				"description"			=> $lang->mention_advanced_matching_desc,
				"optionscode"		=> "yesno",
				"value"					=> '0',
				"disporder"			=> '10'
			),
			"mention_cache_time" => array
			(
				"sid"						=> "NULL",
				"name"					=> "mention_cache_time",
				"title"					=> $lang->mention_cache_time_title,
				"description"			=> $lang->mention_cache_time_description,
				"optionscode"		=> "text",
				"value"					=> '7',
				"disporder"			=> '20'
			),
			"mention_add_codebutton" => array
			(
				"sid"						=> "NULL",
				"name"					=> "mention_add_codebutton",
				"title"					=> $lang->mention_add_codebutton_title,
				"description"			=> $lang->mention_add_codebutton_description,
				"optionscode"		=> 'yesno',
				"value"					=> '1',
				"disporder"			=> '30'
			),
			"mention_add_postbit_button" => array
			(
				"sid"						=> "NULL",
				"name"					=> "mention_add_postbit_button",
				"title"						=> $lang->mention_add_postbit_button_title,
				"description"			=> $lang->mention_add_postbit_button_description,
				"optionscode"		=> 'yesno',
				"value"					=> '0',
				"disporder"			=> '40'
			),
			"mention_multiple" => array
			(
				"sid"						=> "NULL",
				"name"					=> "mention_multiple",
				"title"						=> $lang->mention_multiple_title,
				"description"			=> $lang->mention_multiple_description,
				"optionscode"		=> 'yesno',
				"value"					=> '0',
				"disporder"			=> '50'
			)
		)
	)
);

$templates = array
(
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
);

?>
