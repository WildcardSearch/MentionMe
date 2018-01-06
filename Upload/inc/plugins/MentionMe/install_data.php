<?php
/*
 * Plugin Name: MentionMe for MyBB 1.8.x
 * Copyright 2014 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains data used by WildcardPluginInstaller
 */

$settings = array(
	'mention_settings' => array(
		'group' => array(
			'name' => 'mention_settings',
			'title'  => $lang->mention_plugin_settings_title,
			'description' => $lang->mention_settingsgroup_description,
			'disporder'  => '102',
			'isdefault'  => 0,
		),
		'settings' => array(
			'mention_auto_complete' => array(
				'sid' => '0',
				'name' => 'mention_auto_complete',
				'title' => $lang->mention_auto_complete_title,
				'description' => $lang->mention_auto_complete_description,
				'optionscode' => 'yesno',
				'value' => '1',
				'disporder' => '10',
			),
			'mention_max_items' => array(
				'sid' => '0',
				'name' => 'mention_max_items',
				'title' => $lang->mention_max_items_title,
				'description' => $lang->mention_max_items_description,
				'optionscode' => 'text',
				'value' => '5',
				'disporder' => '20',
			),
			'mention_get_thread_participants' => array(
				'sid' => '0',
				'name' => 'mention_get_thread_participants',
				'title' => $lang->mention_get_thread_participants_title,
				'description' => $lang->mention_get_thread_participants_description,
				'optionscode' => 'yesno',
				'value' => '1',
				'disporder' => '30',
			),
			'mention_max_thread_participants' => array(
				'sid' => '0',
				'name' => 'mention_max_thread_participants',
				'title' => $lang->mention_max_thread_participants_title,
				'description' => $lang->mention_max_thread_participants_description,
				'optionscode' => 'text',
				'value' => '5',
				'disporder' => '40',
			),
			'mention_full_text_search' => array(
				'sid' => '0',
				'name' => 'mention_full_text_search',
				'title' => $lang->mention_full_text_search_title,
				'description' => $lang->mention_full_text_search_description,
				'optionscode' => 'yesno',
				'value' => '0',
				'disporder' => '50',
			),
			'mention_show_avatars' => array(
				'sid' => '0',
				'name' => 'mention_show_avatars',
				'title' => $lang->mention_show_avatars_title,
				'description' => $lang->mention_show_avatars_description,
				'optionscode' => 'yesno',
				'value' => '1',
				'disporder' => '60',
			),
			'mention_lock_selection' => array(
				'sid' => '0',
				'name' => 'mention_lock_selection',
				'title' => $lang->mention_lock_selection_title,
				'description' => $lang->mention_lock_selection_description,
				'optionscode' => 'yesno',
				'value' => '1',
				'disporder' => '70',
			),
			'mention_add_postbit_button' => array(
				'sid' => '0',
				'name' => 'mention_add_postbit_button',
				'title' => $lang->mention_add_postbit_button_title,
				'description' => $lang->mention_add_postbit_button_description,
				'optionscode' => 'yesno',
				'value' => '0',
				'disporder' => '80',
			),
			'mention_multiple' => array(
				'sid' => '0',
				'name' => 'mention_multiple',
				'title' => $lang->mention_multiple_title,
				'description' => $lang->mention_multiple_description,
				'optionscode' => 'yesno',
				'value' => '0',
				'disporder' => '90',
			),
			'mention_format_names' => array(
				'sid' => '0',
				'name' => 'mention_format_names',
				'title' => $lang->mention_format_names_title,
				'description' => $lang->mention_format_names_desc,
				'optionscode' => 'yesno',
				'value' => '1',
				'disporder' => '100',
			),
			'mention_display_symbol' => array(
				'sid' => '0',
				'name' => 'mention_display_symbol',
				'title' => $lang->mention_display_symbol_title,
				'description' => $lang->mention_display_symbol_desc,
				'optionscode' => 'text',
				'value' => '@',
				'disporder' => '110',
			),
			'mention_open_link_in_new_window' => array(
				'sid' => '0',
				'name' => 'mention_open_link_in_new_window',
				'title' => $lang->mention_open_link_in_new_window_title,
				'description' => $lang->mention_open_link_in_new_window_desc,
				'optionscode' => 'yesno',
				'value' => '0',
				'disporder' => '120',
			),
			'mention_cache_time' => array(
				'sid' => '0',
				'name' => 'mention_cache_time',
				'title' => $lang->mention_cache_time_title,
				'description' => $lang->mention_cache_time_description,
				'optionscode' => 'text',
				'value' => '7',
				'disporder' => '130',
			),
			'mention_minify_js' => array(
				'sid' => '0',
				'name' => 'mention_minify_js',
				'title' => $lang->mention_minify_js_title,
				'description' => $lang->mention_minify_js_desc,
				'optionscode' => 'yesno',
				'value' => '1',
				'disporder' => '140',
			),
			'mention_advanced_matching' => array(
				'sid' => '0',
				'name' => 'mention_advanced_matching',
				'title' => $lang->mention_advanced_matching,
				'description' => $lang->mention_advanced_matching_desc,
				'optionscode' => 'yesno',
				'value' => '0',
				'disporder' => '150',
			),
		)
	)
);

$templates = array(
	'mentionme' => array(
		'group' => array(
			'prefix' => 'mentionme',
			'title' => $lang->mentionme,
		),
		'templates' => array(
			'mentionme_postbit_button' => <<<EOF
<a href="{\$js}" style="display: none;" id="multi_mention_link_{\$post['pid']}" title="{\$lang->mention_title}" class="postbit_multimention"><span>{\$lang->mention_button}</span></a>
<script type="text/javascript">
    $('#multi_mention_link_{\$post['pid']}').show();
</script>
EOF
			,
			'mentionme_quickreply_notice' => <<<EOF
					<div class="editor_control_bar" style="width: 95%; padding: 4px; margin-top: 3px; display: none;" id="quickreply_multi_mention">
						<span class="smalltext">
							{\$lang->mention_posts_selected} <a href="./newreply.php?tid={\$tid}&amp;load_all_mentions=1" onclick="return MentionMe.multi.load();">{\$lang->mention_users_now}</a> {\$lang->or} <a href="javascript:MentionMe.multi.clear();">{\$lang->quickreply_multiquote_deselect}</a>.
						</span>
					</div>
EOF
			,
			'mentionme_popup' => <<<EOF
<div id="mentionme_master_popup" class="mentionme_popup" style="display: none;">
	<div class="mentionme_spinner">
		<img src="images/spinner.gif" />
		<span>{\$lang->mention_autocomplete_loading}</span>
	</div>
	<div class="mentionme_popup_input_container">
		<input class="mentionme_popup_input" type="text" autocomplete="off" />
	</div>
	<div class="mentionme_popup_body"></div>
</div>
EOF
		),
	),
);

$styleSheets = array(
	'forum' => array(
		'mentionme' => array(
			'attachedto' => '',
			'stylesheet' => <<<EOF
div.mentionme_popup {
	position: absolute;
	overflow: hidden;
	z-index: 999;
	min-width: 120px;

	background: white;
	color: black;

	border: 1px solid #dddddd;
	-webkit-border-radius: 3px;
	-moz-border-radius: 3px;
	border-radius: 3px;

	-moz-box-shadow: 0 0 5px rgba(0,0,0,.1);
	-webkit-box-shadow: 0 0 5px rgba(0,0,0,.1);
	box-shadow: 0 0 5px rgba(0,0,0,.1);
	-ms-filter: "progid:DXImageTransform.Microsoft.Shadow(Strength=1, Direction=135, Color='#818181')";
	filter: progid:DXImageTransform.Microsoft.Shadow(Strength=1, Direction=135, Color='#818181');
}

div.mentionme_popup_body {
	font-family: Arial, Helvetica, sans-serif;
	font-size: 15px;
	overflow-y: scroll;
	font-weight: normal !important;
	min-width: 150px;
}

div.mentionme_popup_input_container {
	border-bottom: 1px solid #DDD;
}

input.mentionme_popup_input {
	border: none;
	width: 100%;
	height: 25px;
	font-size: 15px;
	padding-left: 3px;
}

div.mentionme_popup_item {
	padding: 2px 3px 2px 3px !important;
	border-bottom: 1px solid #DDD;
}

div.mentionme_popup_item_on {
	background: #3366FF;
	color: white;
}

span.mentionme_popup_instructions {
	color: grey;
	font-style: italic;
}

span.mentionme_typed_text {
	padding-left: 3px;
}

span.mention_name_highlight {
	color: #3366FF;
	font-weight: bolder;
}

span.mention_name_highlight_on {
	color: white;
	font-weight: bolder;
}

img.mention_user_avatar {
	background: white;
	vertical-align: middle;
	height: 30px;
	width: 30px;
	margin: 2px 10px 2px 5px;

	border: 1px solid #DDD;
	-webkit-border-radius: 50%;
	-moz-border-radius: 50%;
	border-radius: 50%;
}

div.mentionme_spinner {
	font-weight: bold;
	font-style: italic;
	color: #3D3D3D;
	padding-left: 5px;
}

div.mentionme_spinner img {
	float: right;
	padding-right: 5px;
}

.postbit_buttons a.postbit_multimention span {
	background-image: url(images/MentionMe/postbit_multimention.png);
}

.postbit_buttons a.postbit_multimention_on span {
	background-image: url(images/MentionMe/postbit_multimention_on.png);
}

a.mentionme_mention {
	/* style mention links here */
}
EOF
		),
	),
);

$images = array(
	'folder' => 'MentionMe',
	'forum' => array(
		'postbit_multimention.png' => array(
			'image' => <<<EOF
iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AwFFyIAlmVfxAAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAAC0klEQVQ4y12TS2hUVxjHf+cxryQzmZhpHE00JmEUOzS18bWqTUDaLMSFCHahKaULIalCVwVF3Lso6FLadNEKiuJCUUrppgUrIWlEMsTI+MI6yaQzGWYyN5nMvXPnujlJRw98cB6c3/l//+98gneHMKFMSLMGcE3UTXg0HK7PVf/XAwP+Zt9ZqcUgiA7P85brNXe6Ulz7ZfbazH1gDbCBWiNAAGrf6P4xpPzCtqqTS7O5h4uzi4VwLByJ9Xd8HNkaOerhWek7T8+W58s5oALUNiTv+/bAOWfFKT3++dFdIAD4G1IQgEwc3fVZSzx86NHVqTGgBFQ0IJKnkrulkqdnb6cOA/7EkV0HWndEzwmf7Har7lMdUB8KIfTED38P7h3d/1XvcGLoxW/pPwBXAirU2nzGtuxbTsnx9Rzu2bMp0T7uOm7+yfWZ4wuT/14WQmiTarScKd+PdEVOAC1AQAJKSDlUeFb4C9CbEh98h0Bm/5m/YS1Y9sLkwvMGo1vyc0spHfINAEEgKAEplOhcTOWygFBB1Q+Qn1vKG7dLjZWyMqW80LQDGvD9X+fahqEawFmpugaw0gDwVJOSwqNmDFYaIFhd+6/7e/umr6sv3tT5ksq9XkKxlnAlZ218mHUF0e3RuOt4mfUNCXhlV/8pkMGDnx8il2kGYNun2wZD7aG2/m8GLjQAVLS77aCzaj82YFe0XvT/uDnkDBNRncljPaTG0xlLRLcECmvEqnsKpdelq61d4RHl113rlMzEm9E3D14/BBbFzks8S44k+t7rCVI/pTPp85wEHMAHhE1/rALLwBKQ18WtbX1T468AiH/ZRPb6KqJQz5cJzShlW67rOkbusmFXjbFloKI3t/WKpumANx+bBEAXNR31T2IdNTcpzogHCDQeFa/uFaauTAwBllFhA44A+OjeXlamix7pLGTjv774/flFIGSkS/OyYy4XjYIa4L0FgC0UQRwBc6gAAAAASUVORK5CYII=
EOF
		),
		'postbit_multimention_on.png' => array(
			'image' => <<<EOF
iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4AwFFyIvPbRinQAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUHAAACuklEQVQ4y12T32tURxTHPzNzb7Kb/ZFNNlmiJmrEJVhhrflVfFATUFpKCH0Q2pdSRUFJ2oCvKf4BvggVodBC+1AKFYIPLRUfCgVBRRIN0ZAq0T4Ys0nM7uLu3nVz9+7s7ctEbz3whZk5M1++5zvnCP4fwkAZSLMH0AYNA59AcnutMmf6+5si9pS0xAiIlO/7pUZdP6y+3vpl6dfHN4EtoAbUgwQCUIMTQ5NI+XHNcWfzS5v3NpY2CrGOWLwjkzoU3xkf9/Gd5d+fTpWz5U2gCtTfSh78enjaq3jFhZ/n/wCagaZACQKQ6fG+49Gu2LH5H+YmgSJQtQBx8MuDB6SS55duLJ4AmtJjfcOtexPTwpZ7tKufWs3qAyGEdf/K3ZGBiaGv9n2SHv331vJfgJaACrdGvqk5tRmv6Nm9J3o/bE8nf9Kezv3z2+NTa7Mr3wkhLFNqorxavhnvjn8ORIFmCSgh5WjhWeE2YLWnOy8ikOsPstedNae2Nrv2PGB0NPckv2iF7X4gBIQkIIUSuzYWN9cBoUIqA5B7ks8Zt4vBn3JWizlhkQQswH73z/W3hloAXsXVhqASIPBVi5LCp24MVhKgof1sMp3cAeDrRh4g3BGNBRtmW0Fid6JLe/7q9oEEfO3qO219yWEAt1y7C9BztGcknAy3Zc72XwoQqMSeto+8N7UFQ6wVoCKd4VfxntZvs3OrM+WXpfnYrtZUJNVyMpXpGnfWnb+bInavVDLefaTns+ZEaODVo41rpZXiC6Akrlr2927dG91qsTuFbtTDhDoAXLeyMg2nAQ+wgZiZjzdACcgDOcuT4sK5T8d4Py7f+hMlpKO19ozckkm5xtgyUBVfdIkfowX73PsE2cPxyuaR/TYCC5+q3/ALc1fvjwKOUVEDvOAwWab/I6bLwka6NHc88/i1UVAH/P8AjEIC4bJ3CYoAAAAASUVORK5CYII=
EOF
		),
	),
	'acp' => array(
		'donate.gif' => array(
			'image' => <<<EOF
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
EOF
		),
		'pixel.gif' => array(
			'image' => <<<EOF
R0lGODlhAQABAID/AMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==
EOF
		),
		'settings.gif' => array(
			'image' => <<<EOF
R0lGODlhEAAQAOMLAAAAAAMDAwYGBgoKCg0NDRoaGh0dHUlJSVhYWIeHh5aWlv///////////////////yH5BAEKAA8ALAAAAAAQABAAAARe8Mn5lKJ4nqRMOtmDPBvQAZ+IIQZgtoAxUodsEKcNSqXd2ahdwlWQWVgDV6JiaDYVi4VlSq1Gf87L0GVUsARK3tBm6LAAu4ktUC6yMueYgjubjHrzVJ2WKKdCFBYhEQA7
EOF
		),
		'logo.png' => array(
			'image' => <<<EOF
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
EOF
		),
	),
);

?>
