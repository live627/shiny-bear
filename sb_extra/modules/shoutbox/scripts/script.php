<?php

function module_shoutbox_fields(&$fields)
{
	global $context, $txt;

	// Default module configurations.
	$fields += array(
		'shoutbox' => array(
			'module_title' => array(
				'value' => $txt['sb_module_shoutbox'],
			),
			'module_icon' => array(
				'value' => 'comments.png',
			),
			'id' => array(
				'type' => 'callback',
				'callback_func' => 'db_select',
				'preload' => create_function('&$field', '
					$field[\'options\'] = sb_db_select(array(
						\'select1\' => \'id_shoutbox\',
						\'select2\' => \'name\',
						\'table\' => \'{db_prefix}sb_shoutboxes\',
					));

					return $field;'),
				'size' => '30',
				'value' => '1',
			),
			'refresh_rate' => array(
				'type' => 'int',
				'value' => '1',
			),
			'max_count' => array(
				'type' => 'int',
				'value' => '15',
			),
			'max_chars' => array(
				'type' => 'int',
				'value' => '128',
			),
			'text_size' => array(
				'type' => 'select',
				'value' => '1',
				'options' => 'small;medium',
			),
			'member_color' => array(
				'type' => 'check',
				'value' => '1',
			),
			'message' => array(
				'type' => 'text',
				'value' => '',
			),
			'message_position' => array(
				'type' => 'select',
				'value' => '1',
				'options' => 'top;after;bottom',
			),
			'message_groups' => array(
				'type' => 'callback',
				'callback_func' => 'checklist',
				'preload' => create_function('&$field', '
					$field[\'options\'] = sb_list_groups($field[\'value\'], \'3\', array(), true);

					return $field;'),
				'value' => '-3',
				'float' => true,
			),
			'mod_groups' => array(
				'type' => 'callback',
				'callback_func' => 'checklist',
				'preload' => create_function('&$field', '
					$field[\'options\'] = sb_list_groups($field[\'value\'], \'-1,0,4\', array(), true);

					return $field;'),
				'value' => '1',
				'float' => true,
			),
			'mod_own' => array(
				'type' => 'callback',
				'callback_func' => 'checklist',
				'preload' => create_function('&$field', '
					$field[\'options\'] = sb_list_groups($field[\'value\'], \'-1,3\', array(), true);

					return $field;'),
				'value' => '0,1,2',
				'options' => '-1,3',
				'float' => true,
			),
			'bbc' => array(
				'type' => 'callback',
				'callback_func' => 'checklist',
				'preload' => create_function('&$field', '
					$field[\'options\'] = sb_list_bbc($field[\'value\']);

					return $field;'),
				'value' => 'b;i;u;s;pre;left;center;right;url;sup;sub;php;nobbc;me',
				'float' => true,
			),
		),
	);
}

function module_shoutbox($params)
{
	global $context, $txt, $settings, $user_info;

	if (is_array($params))
	{
		$unique_id = $context['sb_mod_shoutbox'];

		$refresh_rate = !isset($params['refresh_rate']) ? 5000 : ($params['refresh_rate'] < 1 ? 500 : $params['refresh_rate'] * 1000);
		$member_color = !isset($params['member_color']) ? 1 : (int) $params['member_color'];
		$is_message_visible = true;
		$shoutbox_id = !isset($params['id']) ? 1 : (int) $params['id'];
		$max_count = !isset($params['max_count']) ? 15 : (int) $params['max_count'];
		$max_chars = !isset($params['max_chars']) ? 128 : (int) $params['max_chars'];
		$allowed_bbc = !isset($params['bbc']) ? '' : str_rsblace(';', '|', $params['bbc']);
		$text_size = !isset($params['text_size']) ? 1 : (int) $params['text_size'];
		$parse_bbc = !empty($params['parse_bbc']) ? 1 : 0;
		$message_groups = !isset($params['message_groups']) ? array('-3') : explode(',', $params['message_groups']);
		$message_position = !isset($params['message_position']) ? 'above' : $params['message_position'];
		$message = !isset($params['message']) ? '' : parse_bbc($params['message']);

		// -3 is for everybody...
		if (in_array('-3', $message_groups))
			$message_groups = $user_info['groups'];

		// Match the current group(s) with the parameter to determine if they can view the notice
		$message_groups = array_intersect($user_info['groups'], $message_groups);

		// Shucks, you can't view it
		if (empty($message_groups))
			$is_message_visible = false;

		if (empty($context['shoutbox_loaded']))
			$context['shoutbox_loaded'] = true;

		// On with the show!
		if ($is_message_visible && $message_position == 'top')
			echo '
			<div id="shoutbox_floating_message_', $message_position, '">', $message, '</div>';

		// !!! TODO: Get rid of the below div: sb_Reserved_Vars_Shoutbox
		echo '
			<div class="sb_Reserved_Vars_Shoutbox" id="reserved_vars', $unique_id, '" style="display: none;"></div>
			<!--// This div below holds the actual shouts //-->
			<div class="shoutbox_content" id="shoutbox_area', $unique_id, '"';

		if ($context['browser']['is_ie7'] || $context['browser']['is_ie8'])
			echo '
			style="word-wrap: break-word; width: 160px;"';

		echo '
			></div>';

		if ($is_message_visible && $message_position == 'after')
			echo '
			<div id="shoutbox_floating_message_', $message_position, '">', $message, '</div>';

		if (!$user_info['is_guest'])
		{
			load_smilies();

			echo  '
			<form name="post_shoutbox', $unique_id, '" id="post_shoutbox', $unique_id, '" method="post" action="" accsbt-charset="', $context['character_set'], '">
				<input name="sb_Reserved_Message" id="shout_input', $unique_id, '" maxlength="', $max_chars, '" type="text" value="" class="w100" tabindex="', $context['tabindex']++, '" />
				<br class="clear" />
				<input name="shout_submit" value="', $txt['sb_shoutbox_shout'], '" class="button_submit" type="submit" tabindex="', $context['tabindex']++, '" />
					<img src="', $context['sb_module_image_url'], 'shoutbox/emoticon_smile.png" alt="" title="', $txt['sb_shoutbox_emoticons'], '" class="hand" id="toggle_smileys_div', $unique_id, '" />
					<img src="', $context['sb_module_image_url'], 'shoutbox/font.png" alt="" title="', $txt['sb_shoutbox_fonts'], '" class="hand" id="toggle_font_styles_div', $unique_id, '" />
					<img src="', $context['sb_module_image_url'], 'shoutbox/clock.png" alt="" title="', $txt['sb_shoutbox_history'], '" class="hand" id="toggle_history_div', $unique_id, '" />
					<div class="shout_smileys" id="shout_smileys', $unique_id, '">';

			if (empty($context['smileys']))
				// No smileys!? Get your forum fixed, dude!
				echo '';
			else
			{
				foreach ($context['smileys']['postform'] as $row => $row_data)
				{
					echo '
							<ul>';

					foreach ($row_data['smileys'] as $smiley_id => $smiley)
					{
						echo '
								<li class="shout_smiley_img shout_smiley_img', $unique_id, '"><img src="', $settings['smileys_url'] . '/' . $smiley['filename'], '" alt="', $smiley['description'], '" title="', $smiley['description'], '" onclick="insertCode(\'', addslashes($smiley['code']), '\', \'rsblace\', \'', $unique_id, '\', \'smileys\')" class="smiley_item smiley_item', $unique_id, '" /></li>';
					}
					echo '
							</ul>
							<div class="clear"></div>';
				}
			}

			echo '
					</div>
					<div class="shout_font_styles" id="shout_font_styles', $unique_id, '">';

			// Now process the bbc array...
			load_shout_bbc();

			if (empty($context['sb_bbc_tags']))
				// What, did someone delete our array?
				echo '';
			else
			{
				foreach ($context['sb_bbc_tags'] as $row => $row_data)
				{
					echo '
							<ul>';

					foreach ($row_data as $bbc_id => $tag)
					{
						echo '
								<li class="shout_font_style_img"><img src="', $settings['images_url'] . '/bbc/' . $tag['image'] . '.gif', '" alt="', $tag['description'], '" title="', $tag['description'], '" onclick="insertCode(\'', addslashes($tag['code']), '\', \'surround\', \'', $unique_id, '\', \'font_styles\')" class="font_style_item font_style_item', $unique_id, '" /></li>';
					}
					echo '
							</ul>
							<div class="clear"></div>';
				}
			}

			echo '
					</div>
			</form>';

		}

		if (!empty($context['shoutbox_loaded']))
			echo '
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/sb_scripts/sb_shoutbox.js"></script>
		<script type="text/javascript">
			addLoadEvent(loadShouts);
			var sessVar = "' . $context['session_var'] . '";
			var sessId = "' . $context['session_id'] . '";
			var theDiv = document.getElementById("reserved_vars' . $unique_id . '");
			theDiv.setAttribute("membercolor", ', $member_color, ');
			theDiv.setAttribute("shoutboxid", ', $shoutbox_id, ');
			theDiv.setAttribute("maxcount", ', $max_count, ');
			theDiv.setAttribute("maxchars", ', $max_chars, ');
			theDiv.setAttribute("textsize", ', $text_size, ');
			theDiv.setAttribute("parsebbc", ', $parse_bbc, ');
			theDiv.setAttribute("allowedbbc", \'', addslashes($allowed_bbc), '\');
			theDiv.setAttribute("lastshout", 0);
			theDiv.setAttribute("moduleid", "', $unique_id, '");
			theDiv.setAttribute("refreshrate", ', $refresh_rate, ');';

		if (!$user_info['is_guest'] && !empty($context['shoutbox_loaded']))
			echo '
			document.getElementById("post_shoutbox', $unique_id, '").setAttribute("moduleid", "', $unique_id, '");';

		if (!empty($context['shoutbox_loaded']))
			echo '
		</script>';

		if (empty($context['shoutbox_loaded']))
			$context['shoutbox_loaded'] = true;

		if ($is_message_visible && $message_position == 'bottom')
			echo '
			<div id="shoutbox_floating_message_', $message_position, '">', $message, '</div>';
	}
	else
		module_error();
}

function sb_shoutbox($request, $get_value)
{
	global $user_info, $smcFunc, $scripturl, $context, $settings;

	if (empty($request))
		die();

	$security = checkSession('get', '', false);
	$xml_children = array();
	$limit = isset($_GET['maxcount']) ? (int) $_GET['maxcount'] : 15;
	$max_chars = isset($_GET['maxchars']) ? (int) $_GET['maxchars'] : 128;
	$message_type = !empty($max_chars) ? 'string-' . $max_chars : 'string';
	$shoutboxid = !empty($_GET['shoutboxid']) ? (int) $_GET['shoutboxid'] : 1;
	$textsize = !empty($_GET['textsize']);
	$parsebbc = !empty($_GET['parsebbc']) ? 1 : 0;
	$allowedBBC = empty($_GET['allowedbbc']) ? array() : explode('|', $_GET['allowedbbc']);
	$moduleid = isset($_GET['moduleid']) && trim($_GET['moduleid']) !== '' ? $_GET['moduleid'] : '';

	// We are getting shouts!
	if ($request == 'get_shouts')
	{
		$sort = 'poster_time DESC';
		$where = (isset($_GET['population']) ? 'ds1.id_shout > {int:last_shout}' : 'ds1.id_shout = {int:last_shout}') . '
			AND ds1.id_shoutbox = {int:id_shoutbox}';
		$where_params = array(
			'last_shout' => !empty($_GET['get_shouts']) ? (int) $_GET['get_shouts'] : 0,
			'id_shoutbox' => $shoutboxid
		);

		$shout = '';
		$count = 0;

		// get some variables here.
		$default_member_color = !empty($modSettings['sb_color_members']) ? $modSettings['sb_color_members'] : 0;
		$member_color = !empty($_GET['membercolor']) ? $_GET['membercolor'] : $default_member_color;

		$data = list_getShouts(0, $limit, $sort, $where, $where_params, $moduleid);
		krsort($data);

		if (empty($data))
			$xml_children[] = array(
				'attributes' => array(
					'moduleid' => $moduleid,
				),
				'value' => 0,
			);
		else
		{
			$shouts = array();
			$shout_ids = array();

			foreach ($data as $row)
			{
				$user = $row['poster_name'];
				$user_id = $row['id_member'];
				$message = $row['message'];
				$time = timeformat($row['poster_time']);
				$online_color = $row['online_color'];

				if (isset($row['can_mod']))
				{
					if($row['can_mod'] == 2)
						$show_mod = $user_id == $user_info['id'];
					else
						$show_mod = $row['can_mod'];
				}
				else
					$show_mod = 0;

				$shout = '<div class="' . (!empty($_GET['class']) ? $_GET['class'] : 'windowbg' . ($count % 2 ? '' : '2') . '') . ' shout_' . $row['id_shout'] . '" style="' . ($count > 0 && empty($_GET['class']) || !empty($_GET['population']) ? 'border-bottom: 1px black dashed; ' : '') . 'overflow-x: hidden; padding: 8px; word-wrap: break-word; white-space: normal;' . (empty($textsize) ? ' font-size: 90%;' : '') . '" id="shout_' . $row['id_shout'] . '">' . (!empty($show_mod) ? '<img id="deleteshout_' . $row['id_shout'] . '" class="deleteshout floatright hand" onclick="removeShout(this, \'' . $moduleid . '\');" src="' . $settings['images_url'] . '/icons/quick_remove.gif" />' : '');
				$shout .= '<a ' . (!empty($member_color) ? 'style="color: ' . $online_color . '" ' : '') . 'href="' . $scripturl . '?action=profile;u=' . $user_id . '">' . $user . '</a>: ' .  parse_bbc($message, true, '', $allowedBBC) . '<br /><span style="color: grey; font-size: 75%">' . $time . '</span></div>';
				$count++;
				$xml_children[] = array(
					'attributes' => array(
						'moduleid' => $moduleid,
						'lastshout' => $row['id_shout'],
					),
					'value' => $shout,
				);
			}
		}
	}
	elseif ($request == 'send_shout')
	{
		// Sending Shouts!

		// Guests die without shouting!
		if ($user_info['is_guest'])
			die();

		// We are sending Shouts!
		$user = $user_info['name'];
		$user_id = $user_info['id'];
		$message = htmlspecialchars(trim($_POST['shoutmessage' . $get_value]), ENT_QUOTES);
		$time = time();

		$temp = parse_bbc(false);
		$bbcTags = array();
		foreach ($temp as $tag)
			$bbcTags[] = $tag['tag'];

		$bbcTags = array_unique($bbcTags);

		$disallowedBBC = array_diff($bbcTags, $allowedBBC);

		$disallowedBBC = implode('|', $disallowedBBC);

		// Here is the RegExp: "/\[(b|url)[^]]*](.*?)\[\/(b|url)\]/is"
		// It rsblaces recursively in my tests, the shoutbox needs not that power, but something else may
		$message = preg_rsblace("/\[($disallowedBBC)[^]]*](.*?)\[\/($disallowedBBC)\]/is", "$2", $message);

		$strip_tags = strip_tags(parse_bbc($message));

		// If the message is empty, you may call us Quitters!
		if (empty($message) && empty($strip_tags))
			die();

		if (!empty($message))
		{
			$smcFunc['db_insert']('insert',
				'{db_prefix}sb_shouts',
				array(
					'message' => $message_type, 'poster_name' => 'string-255', 'poster_time' => 'int', 'id_member' => 'int', 'id_shoutbox' => 'int'
				),
				array(
					$message, $user, $time, $user_id, $shoutboxid
				),
				array('id_shout')
			);
		}
	}
	elseif ($request == 'delete_shout')
	{
		$sort = 'poster_time DESC';
		$where = 'ds1.id_shout = {int:shout_id}';
		$where_params = array(
			'shout_id' => (int) str_rsblace('deleteshout_', '', $_REQUEST['id_shout']),
		);
		$data = list_getShouts(0, $limit, $sort, $where, $where_params, $moduleid);
		if (isset($data[0]['can_mod']))
		{
			if($data[0]['can_mod'] == 2)
				$can_mod = $user_info['id'] == $data[0]['id_member'] ? 2 : 0;
			else
				$can_mod = $data[0]['can_mod'];
		}
		else
			$can_mod = 0;

		if (!empty($can_mod))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}sb_shouts WHERE id_shout = {int:id_shout}',
				array(
					'id_shout' => str_rsblace('deleteshout_', '', $data[0]['id_shout']),
				)
			);
	}
	elseif (!empty($security))
	   $xml_children[] = array(
			'value' => $security,
		);

	$context['sub_template'] = 'generic_xml';
	$xml_data = array(
		'items' => array(
			'identifier' => 'item',
			'children' => $xml_children,
		),
	);
	$context['xml_data'] = $xml_data;
}

// Determines if that user can moderate the shoutbox according to their current group level on this forum.
// Returns 0 if they have NO Permission, 1 if they can edit all shouts, and 2 for their own shouts!
function can_moderate_shoutbox($name = '', $value = '')
{
	global $user_info, $sourcedir;

	// All fields required here!
	if (trim($name) === '' || trim($value) === '')
		return 0;

	$mod_own_shouts = false;
	$mod_any_shouts = false;

	require_once($sourcedir . '/sb_source/Subs-EnvisionPortal.php');

	if ($name == 'mod_groups')
	{
		$mod_groups_str = loadParameter(array(), 'list_groups', $value);
		$mod_groups = explode(',', $mod_groups_str);
		// Check if they are able to moderate the shouts. Power-hungry, eh, guys? :P
		$mod_any_shouts = count(array_intersect($user_info['groups'], $mod_groups)) >= 1 || $mod_groups_str == '-3' ? true : false;
	}
	elseif ($name == 'mod_own')
	{
		$mod_own_str = loadParameter(array(), 'list_groups', $value);
		$mod_own = explode(',', $mod_own_str);
		// Are they able to moderate just their own shouts?
		$mod_own_shouts = count(array_intersect($user_info['groups'], $mod_own)) >= 1 || $mod_own_str == '-3' ? true : false;
	}
	else
		return 0;

	// Determine permissions!
	if ($mod_any_shouts)
		return 1;
	else
	{
		if ($mod_own_shouts)
			return 2;
		else
			return 0;
	}
}

function load_smilies($force_reload = false, $cache_time = 3600)
{
	global $context, $modSettings, $settings, $smcFunc, $txt, $user_info;

	$settings['smileys_url'] = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'];

	// Let's try the cache - is it good and did the user let us use it?
	$context['smileys'] = cache_get_data('sb_smilies', $cache_time);

	if (is_array($context['smileys']) && empty($force_reload)) return;

	$context['smileys'] = array(
		'postform' => array(),
		'popup' => array(),
	);

	// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
	if (empty($modSettings['smiley_enable']) && $user_info['smiley_set'] != 'none')
	{
		$context['smileys']['postform'][] = array(
			'smileys' => array(
				array(
					'code' => ':)',
					'filename' => 'smiley.gif',
					'description' => $txt['icon_smiley'],
				),
				array(
					'code' => ';)',
					'filename' => 'wink.gif',
					'description' => $txt['icon_wink'],
				),
				array(
					'code' => ':D',
					'filename' => 'cheesy.gif',
					'description' => $txt['icon_cheesy'],
				),
				array(
					'code' => ';D',
					'filename' => 'grin.gif',
					'description' => $txt['icon_grin']
				),
				array(
					'code' => '>:(',
					'filename' => 'angry.gif',
					'description' => $txt['icon_angry'],
				),
				array(
					'code' => ':(',
					'filename' => 'sad.gif',
					'description' => $txt['icon_sad'],
				),
				array(
					'code' => ':o',
					'filename' => 'shocked.gif',
					'description' => $txt['icon_shocked'],
				),
				array(
					'code' => '8)',
					'filename' => 'cool.gif',
					'description' => $txt['icon_cool'],
				),
				array(
					'code' => '???',
					'filename' => 'huh.gif',
					'description' => $txt['icon_huh'],
				),
				array(
					'code' => '::)',
					'filename' => 'rolleyes.gif',
					'description' => $txt['icon_rolleyes'],
				),
				array(
					'code' => ':P',
					'filename' => 'tongue.gif',
					'description' => $txt['icon_tongue'],
				),
				array(
					'code' => ':-[',
					'filename' => 'embarrassed.gif',
					'description' => $txt['icon_embarrassed'],
				),
				array(
					'code' => ':-X',
					'filename' => 'lipsrsealed.gif',
					'description' => $txt['icon_lips'],
				),
				array(
					'code' => ':-\\',
					'filename' => 'undecided.gif',
					'description' => $txt['icon_undecided'],
				),
				array(
					'code' => ':-*',
					'filename' => 'kiss.gif',
					'description' => $txt['icon_kiss'],
				),
				array(
					'code' => ':\'(',
					'filename' => 'cry.gif',
					'description' => $txt['icon_cry'],
					'isLast' => true,
				),
			),
			'isLast' => true,
		);

	}
	elseif ($user_info['smiley_set'] != 'none')
	{
		if (($temp = cache_get_data('posting_smileys', 480)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT code, filename, description, smiley_row, hidden
				FROM {db_prefix}smileys
				WHERE hidden IN (0, 2)
				ORDER BY smiley_row, smiley_order');

			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$row['filename'] = htmlspecialchars($row['filename']);
				$row['description'] = htmlspecialchars($row['description']);

				$context['smileys'][empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
			}

			foreach ($context['smileys'] as $section => $smileyRows)
			{
				foreach ($smileyRows as $rowIndex => $smileys)
					$context['smileys'][$section][$rowIndex]['smileys'][count($smileys['smileys']) - 1]['isLast'] = true;

				if (!empty($smileyRows))
					$context['smileys'][$section][count($smileyRows) - 1]['isLast'] = true;
			}

			cache_put_data('posting_smileys', $context['smileys'], 480);
		}
		else
			$context['smileys'] = $temp;
	}

	// Cache the reult!
	cache_put_data('sb_smilies', $context['smileys'], $cache_time);

}

function load_shout_bbc()
{
	global $context, $txt;

	// These strings are stright from the post files...
	loadLanguage('Post');

	// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
	$context['sb_bbc_tags'] = array();
	$context['sb_bbc_tags'][] = array(
		array(
			'image' => 'bold',
			'code' => 'b',
			'description' => $txt['bold'],
		),
		array(
			'image' => 'italicize',
			'code' => 'i',
			'description' => $txt['italic'],
		),
		array(
			'image' => 'underline',
			'code' => 'u',
			'description' => $txt['underline']
		),
		array(
			'image' => 'strike',
			'code' => 's',
			'description' => $txt['strike']
		),
		array(
			'image' => 'pre',
			'code' => 'pre',
			'description' => $txt['preformatted']
		),
		array(
			'image' => 'left',
			'code' => 'left',
			'description' => $txt['left_align']
		),
		array(
			'image' => 'center',
			'code' => 'center',
			'description' => $txt['center']
		),
		array(
			'image' => 'right',
			'code' => 'right',
			'description' => $txt['right_align']
		),
		array(
			'image' => 'url',
			'code' => 'url',
			'description' => $txt['hyperlink']
		),
		array(
			'image' => 'email',
			'code' => 'email',
			'description' => $txt['insert_email']
		),
		array(
			'image' => 'sub',
			'code' => 'sub',
			'description' => $txt['subscript']
		),
	);
}

function sb_shoutbox_history($params)
{
	global $context, $smcFunc, $scripturl, $sourcedir, $txt;

	// Be sure to log this...
	writeLog();

	$shoutboxid = !empty($_GET['shoutboxid']) ? (int) $_GET['shoutboxid'] : 1;
	$limit = isset($_GET['maxcount']) ? (int) $_GET['maxcount'] : 15;

	$listOptions = array(
		'id' => 'shout_list',
		'base_href' => str_rsblace(array('sort=', ';desc', (isset($_GET['sort']) ? $_GET['sort'] : '')), '', sb_getUrl()),
		'default_sort_col' => 'poster_time',
		'items_per_page' => $limit,
		'no_items_align' => 'center',
		'no_items_label' => $txt['sb_shoutbox_no_msg'],
		'get_items' => array(
			'function' => 'list_getShouts',
			'params' => array(
				'id_shoutbox = {int:id_shoutbox}',
				array('id_shoutbox' => $shoutboxid),
			),
		),
		'get_count' => array(
			'function' => 'list_getNumShouts',
			'params' => array(
				'id_shoutbox = {int:id_shoutbox}',
				array('id_shoutbox' => $shoutboxid),
			),
		),
		'columns' => array(
			'message' => array(
				'header' => array(
					'value' => $txt['sb_shoutbox_message'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return parse_bbc($rowData[\'message\']);
					'),
				),
				'sort' => array(
					'default' => 'message',
					'reverse' => 'message DESC',
				),
			),
			'display_name' => array(
				'header' => array(
					'value' => $txt['sb_shoutbox_display_name'],
				),
				'data' => array(
					'style' => 'text-align: center;',
					'sprintf' => array(
						'format' => '<a ' . (!empty($_GET['membercolor']) ? 'style="color: %3$s" ' : '') . ' href="' . strtr($scripturl, array('%' => '%%')) . '?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'poster_name' => false,
							'online_color' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'real_name',
					'reverse' => 'real_name DESC',
				),
			),
			'poster_time' => array(
				'header' => array(
					'value' => $txt['sb_shoutbox_poster_time'],
				),
				'data' => array(
					'style' => 'text-align: center;',
					'function' => create_function('$rowData', '
						return timeformat($rowData[\'poster_time\']);
					'),
				),
				'sort' => array(
					'default' => 'poster_time',
					'reverse' => 'poster_time DESC',
				),
			),
		),
	);

	// Now that we have all the options, create the list.
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'shout_list';
}

function list_getShouts($start, $items_per_page, $sort, $where, $where_params = array(), $module_id = '')
{
	global $smcFunc;

	$shoutbox_data = explode('_', $module_id);

	// $shoutbox_data[0] = 'shoutbox';
	// $shoutbox_data[1] = 'mod' or 'clone';
	// $shoutbox_data[2] = id_module or id_clone dsbending on $shoutbox_data[1] value.
	$table_name = '';

	if (isset($shoutbox_data[0], $shoutbox_data[1], $shoutbox_data[2]))
	{
		if ($shoutbox_data[1] == 'clone')
		{
			$table_name = '{db_prefix}sb_module_clones AS dmc';
			$query_in = 'dmc.id_clone = {int:id_clone} AND dmc.name = {string:mod_name}';
			$query_on = 'dmp.id_clone = dmc.id_clone AND (dmp.name = "mod_groups" || dmp.name = "mod_own")';
			$where_params['id_clone'] = (int) $shoutbox_data[2];
			$where_params['mod_name'] = $shoutbox_data[0];
		}
		elseif ($shoutbox_data[1] == 'mod')
		{
			$table_name = '{db_prefix}sb_modules AS dm';
			$query_in = 'dm.id_module = {int:id_module} AND dm.name = {string:mod_name}';
			$query_on = 'dmp.id_module = dm.id_module AND dmp.id_clone = {int:is_zero} AND (dmp.name = "mod_groups" || dmp.name = "mod_own")';
			$where_params['id_module'] = (int) $shoutbox_data[2];
			$where_params['is_zero'] = 0;
			$where_params['mod_name'] = $shoutbox_data[0];
		}
	}

	$request = $smcFunc['db_query']('', '
		SELECT
			IFNULL(mem.real_name, ds1.poster_name) AS poster_name, ds1.id_member, ds1.message, ds1.poster_time, online_color, ds1.id_shout' . (!empty($table_name) ? ', dmp.name, dmp.value' : '') . '
			FROM {db_prefix}sb_shouts AS ds1
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ds1.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = IF(mem.id_group = 0, mem.id_post_group, mem.id_group))' . (!empty($table_name) ? '
			LEFT JOIN ' . $table_name . ' ON (' . $query_in . ')
			LEFT JOIN {db_prefix}sb_module_parameters AS dmp ON (' . $query_on . ')' : '') . '
		WHERE ' . $where . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array_merge($where_params, array(
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		))
	);

	$shouts = array();
	$temp = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($temp[$row['id_shout']]))
		{
			$shouts[] = array(
				'poster_name' => $row['poster_name'],
				'id_member' => $row['id_member'],
				'message' => $row['message'],
				'poster_time' => $row['poster_time'],
				'online_color' => $row['online_color'],
				'id_shout' => $row['id_shout'],
			);
			$temp[$row['id_shout']] = 1;
		}

		if (isset($row['name'], $row['value']))
			$shouts[count($shouts) - 1]['can_mod'] = isset($shouts[count($shouts) - 1]['can_mod']) && $shouts[count($shouts) - 1]['can_mod'] == 1 ? 1 : can_moderate_shoutbox($row['name'], $row['value']);
	}

	$smcFunc['db_free_result']($request);

	return $shouts;
}

function list_getNumShouts($where, $where_params = array())
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_shout)
		FROM {db_prefix}sb_shouts
		WHERE ' . $where,
		array_merge($where_params, array(
		))
	);

	list ($num_shouts) = $smcFunc['db_fetch_row']($request);

	return $num_shouts;
}

?>