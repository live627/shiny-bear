<?php

/**
 * ShinyBear
 *
 * @package Shiny Bear mod
 * @version 1.0 Alpha
 * @author John Rayes <live627@gmail.com>
 * @copyright Copyright (c) 2012, John Rayes
 * @license http://www.mozilla.org/MPL/MPL-1.1.html
 */

namespace ShinyBear;

class ShinyBear
{
	/**
	 * Initialize front page.
	 *
	 * @return void
	 */
	public function init()
	{
		global $context, $txt;

		// A mobile device doesn't require a portal...
		//if (WIRELESS)
		//	redirectexit('action=forum');

		$context['sub_template'] = 'portal';
		$context['page_title'] = $context['forum_name'] . ' - ' . $txt['home'];
	}

	/**
	 * Start to load the portal.
	 *
	 * @return void
	 */
	public function load($init_action = '')
	{
		global $context, $txt, $board, $topic, $scripturl, $boarddir, $boardurl, $sourcedir, $modSettings, $user_info;

		loadLanguage('ShinyBearModules');

		// These puppies are evil >:D
		unset($_GET['PHPSESSID'], $_GET['theme']);

		// We want the first item in the requested URI
		reset($_GET);
		$uri = key($_GET);

		// If a registered SMF action was called, use that instead
		$da_action = !empty($uri) ? !empty($context['current_action']) ? $context['current_action'] : '[' . $uri . ']' : '';
		$da_action = !empty($init_action) ? $init_action : $da_action;

		$skipped_actions = array(
			'.xml',
			'xmlhttp',
			'dlattach',
			'helpadmin',
			'kesbalive',
			'printpage',
			'sbjs',
			'jseditor',
			'jsoption',
			'jsmodify',
			'jsoption',
			'suggest',
			'verificationcode',
			'viewsmfile',
			'viewquery',
			'print',
			'clock',
			'about:unknown',
			'about:mozilla',
			'modifycat',
		);
		call_integration_hook('integrate_skipped_actions', array(&$skipped_actions));
		$skip_this = in_array($da_action, $skipped_actions);
		if ($skip_this)
			return;

		// Add Forum to the linktree.
		if (!empty($board) || !empty($topic) || $da_action == 'forum' || $da_action == 'collapse')
			array_splice($context['linktree'], 1, 0, array(array(
					'name' => $txt['forum'],
					'url' => $scripturl . '?action=forum',
				)));

		$context['sb_icon_url'] = $boardurl . '/sb_extra/module_icons/';
		$context['sb_module_image_url'] = $boardurl . '/sb_extra/module_images/';
		$context['sbadmin_image_url'] = $boardurl . '/sb_extra/images/admin';
		$context['sb_module_modules_dir'] = $boarddir . '/sb_extra/modules';
		$context['sb_plugins_dir'] = $boarddir . '/sb_extra/plugins';
		$context['sb_plugins_url'] = $boardurl . '/sb_extra/plugins';
		$context['sb_module_icon_url'] = $boardurl . '/sb_extra/module_icons';
		$context['sb_module_icon_dir'] = $boarddir . '/sb_extra/module_icons';
		$context['sb_module_template'] = $boarddir . '/sb_extra/module_templates';

		$curr_action = !empty($da_action) ? $da_action : '[home]';
		$context['sb_home'] = $curr_action == '[home]';

		$this->loadLayout($curr_action);
	}

	protected function loadLayout($url, $return = false)
	{
		global $smcFunc, $context, $scripturl, $sourcedir, $txt, $user_info;

		if (is_int($url))
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					*, elp.id_layout_position
				FROM {db_prefix}sb_layouts AS el
					LEFT JOIN {db_prefix}sb_layout_positions AS elp ON (elp.id_layout = el.id_layout)
					LEFT JOIN {db_prefix}sb_module_positions AS emp ON (emp.id_layout_position = elp.id_layout_position)
					LEFT JOIN {db_prefix}sb_modules AS em ON (em.id_module = emp.id_module)
				WHERE el.id_layout = {int:id_layout}',
				array(
					'zero' => 0,
					'id_layout' => $url,
				)
			);
		}
		else
		{
			$match = (!empty($_REQUEST['board']) ? '[board]=' . $_REQUEST['board'] : (!empty($_REQUEST['topic']) ? '[topic]=' . (int) $_REQUEST['topic'] : (!empty($_REQUEST['page']) ? '[page]=' . $_REQUEST['page'] : $url)));
			$general_match = (!empty($_REQUEST['board']) ? '[board]' : (!empty($_REQUEST['topic']) ? '[topic]' : (!empty($_REQUEST['page']) ? '[page]' : (!empty($_REQUEST['action']) ? '[all_actions]' : ''))));
			$mmatch = $match;
			$mgeneral_match = $general_match;

			$request = $smcFunc['db_query']('', '
				SELECT
					el.id_layout
				FROM {db_prefix}sb_layouts AS el
					INNER JOIN {db_prefix}sb_layout_actions AS ela ON (ela.id_layout = el.id_layout AND ela.action = {string:current_action})
				WHERE el.id_member = {int:current_member}',
				array(
					'current_action' => $mmatch,
					'current_member' => $user_info['id'],
				)
			);

			$num2 = $smcFunc['db_num_rows']($request);
			$smcFunc['db_free_result']($request);

			if (empty($num2))
				$mmatch = $mgeneral_match;

			$request = $smcFunc['db_query']('', '
				SELECT
					el.id_member
				FROM {db_prefix}sb_layouts AS el
					INNER JOIN {db_prefix}sb_layout_actions AS ela ON (ela.id_layout = el.id_layout AND ela.action = {string:current_action})
				WHERE el.id_member = {int:current_member}',
				array(
					'current_action' => $mmatch,
					'current_member' => $user_info['id'],
				)
			);

			list ($current_member) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if (empty($current_member))
				$current_member = 0;

			$request = $smcFunc['db_query']('', '
				SELECT
					el.id_layout
				FROM {db_prefix}sb_layouts AS el
					INNER JOIN {db_prefix}sb_layout_actions AS ela ON (ela.id_layout = el.id_layout AND ela.action = {string:current_action})
				WHERE el.id_member = {int:zero}',
				array(
					'current_action' => $match,
					'zero' => 0,
				)
			);

			$num2 = $smcFunc['db_num_rows']($request);
			$smcFunc['db_free_result']($request);

			if (empty($num2))
				$match = $general_match;

			// If this is empty, e.g. index.php?action or index.php?action=
			if (empty($match))
			{
				$match = '[home]';
				$context['sb_home'] = true;
			}

			// Let's grab the data necessary to show the correct layout!
			$request = $smcFunc['db_query']('', '
				SELECT
					*, elp.id_layout_position
				FROM {db_prefix}sb_layouts AS el
					JOIN {db_prefix}sb_layout_actions AS ela ON (ela.action = {string:current_action} AND ela.id_layout = el.id_layout)
					LEFT JOIN {db_prefix}sb_layout_positions AS elp ON (elp.id_layout = el.id_layout)
					LEFT JOIN {db_prefix}sb_module_positions AS emp ON (emp.id_layout_position = elp.id_layout_position)
					LEFT JOIN {db_prefix}sb_modules AS em ON (em.id_module = emp.id_module)
				WHERE el.id_member = {int:current_member}',
				array(
					'current_member' => $current_member,
					'current_action' => empty($current_member) ? $match : $mmatch,
				)
			);

			$num = $smcFunc['db_num_rows']($request);
			if (empty($num))
				return;

			$old_row = 0;
			$view_groups = array();

			// Let the theme know we have a layout.
			$context['has_sb_layout'] = true;
		}
		$loaded_ids = array();

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$smf_col = !empty($row['is_smf']);

			if (!is_int($url) && !$smf_col && $row['status'] == 'inactive')
				continue;

			if (is_int($url))
				$context['layout_name'] = $row['name'];

			if (!isset($sb_modules[$row['x_pos']][$row['y_pos']]) && !empty($row['id_layout_position']))
				$sb_modules[$row['x_pos']][$row['y_pos']] = array(
					'is_smf' => $smf_col,
					'id_layout_position' => $row['id_layout_position'],
					'width' => !empty($row['width']) ? ' width: ' . $row['width'] : '',
					'extra' => $row,
				);

			if (!is_null($row['id_position']) && !empty($row['id_layout_position']))
			{
				$loaded_ids[] = $row['id_position'];
				// Store $context variables for each module. Mod Authors can use these for unique ID values, function names, etc.
				// !!! Is this really needed?
				if (!isset($sb_modules[$row['x_pos']][$row['y_pos']]['modules'][$row['position']]))
					if (empty($context['sb_mod_' . $row['type']]))
						$context['sb_mod_' . $row['type']] = $row['type'] .  '_' . $row['id_position'];

				$sb_modules[$row['x_pos']][$row['y_pos']]['modules'][$row['position']] = array(
					'is_smf' => $smf_col,
					'modify_link' => $user_info['is_admin'] ? ' [<a href="' . $scripturl . '?action=admin;area=sbmodules;sa=modify;in=' . $row['id_position'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['modify'] . '</a>]' : '',
					'type' => $row['type'],
					'id' => $row['id_position'],
				);
			}
		}

		if ($return)
			return $sb_modules;

		ksort($sb_modules);

		foreach ($sb_modules as $k => $sb_module_rows)
		{
			ksort($sb_modules[$k]);
			foreach ($sb_modules[$k] as $key => $sb)
				if (is_array($sb_modules[$k][$key]))
					foreach($sb_modules[$k][$key] as $pos => $mod)
					{
						if ($pos != 'modules' || !is_array($sb_modules[$k][$key][$pos]))
							continue;

						ksort($sb_modules[$k][$key][$pos]);
					}
		}

		$module_context = $this->process_module_context($this->load_module_context(), $loaded_ids);
		require_once($sourcedir . '/Class-ShinyBearModules.php');

		foreach ($sb_modules as $row_id => $row_data)
			foreach ($row_data as $column_id => $column_data)
				if (isset($column_data['modules']))
						foreach ($column_data['modules'] as $module => $id)
							if (!empty($id['type']))
								$sb_modules[$row_id][$column_id]['modules'][$module] = $this->process_module($module_context, $id, !is_int($url));

		call_integration_hook('integrate_load_layout', array(&$sb_modules, $url));

		$context['sb_cols'] = $sb_modules;
	}

	private function process_module_context($module_context, $loaded_ids)
	{
		global $smcFunc;

		// Load user-defined module configurations.
		$request = $smcFunc['db_query']('', '
			SELECT
				name, em.type AS module_type, value
			FROM {db_prefix}sb_module_positions AS emp
				LEFT JOIN {db_prefix}sb_modules AS em ON (em.id_module = emp.id_module)
				LEFT JOIN {db_prefix}sb_module_field_data AS emd ON (emd.id_module_position = emp.id_position)
			WHERE emp.id_position IN ({array_int:loaded_ids})',
			array(
				'loaded_ids' => $loaded_ids,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$module_type = $row['module_type'];

			if (!empty($row['name']))
				$fields[$row['name']] = array(
					'value' => $row['value'],
			);
		}

		// Merge the default and custom configs together.
		$info = $module_context[$module_type];

		if (!empty($fields))
			$module_context[$module_type] = array_rsblace_recursive($module_context[$module_type], $fields);

		return $module_context;
	}

	private function process_module($module_context, $data, $full_layout)
	{
		global $context, $modSettings, $settings, $options, $txt, $user_info, $scripturl, $smcFunc;

		$fields = $module_context[$data['type']];

		$data['module_title'] = $fields['module_title']['value'];

		if ($full_layout === false)
			return $data;

		if (file_exists($context['sb_module_modules_dir'] . '/' . $data['type'] . '/main.php'))
			require_once($context['sb_module_modules_dir'] . '/' . $data['type'] . '/main.php');

		// Load the module template.
		if (empty($fields['module_template']['value']) || !empty($fields['module_template']['value']) && !file_exists($context['sb_module_template'] . $fields['module_template']['value']))
			$fields['module_template']['value'] = 'default.php';

		require_once($context['sb_module_template'] . '/' . $fields['module_template']['value']);
		$data['module_template'] = str_replace('.php', '', $fields['module_template']['value']);

		// Correct the title target...
		if (!isset($fields['module_target']['value']))
			$data['module_target'] = '_self';

		if (!empty($fields['module_icon']['value']));
			$data['module_icon'] = '<img src="' . $context['sb_module_icon_url'] . '/' . $fields['module_icon']['value'] . '" alt="" title="' . $data['module_title'] . '" class="icon" style="margin-left: 0px;" />&nbsp;';

		if (isset($fields['module_link']))
		{
			$http = stristr($fields['module_link']['value'], 'http://') !== false || stristr($fields['module_link']['value'], 'www.') !== false;

			if ($http)
				$data['module_title'] = '<a href="' . $fields['module_link']['value'] . '" target="' . $data['module_target'] . '">' . $data['module_title'] . '</a>';
			else
				$data['module_title'] = '<a href="' . $scripturl . '?' . $fields['module_link']['value'] . '" target="' . $data['module_target'] . '">' . $data['module_title'] . '</a>';
		}

		if (!empty($fields))
		{
			$fields2 = $fields;
			$fields = array();

			foreach ($fields2 as $key => $field)
				if (isset($field['type']))
					$fields[$key] = $field['value'];//loadParameter(array(), $field['type'], $field['value']);
		}

		$module = 'shinyBearModule_' . $data['type'];
		if (!empty($fields))
			$data['class'] = new $module($fields);
		else
			$data['class'] = new $module();

		$data['is_collapsed'] = $user_info['is_guest'] ? !empty($_COOKIE[$data['type'] . 'module_' . $data['id']]) : !empty($options[$data['type'] . 'module_' . $data['id']]);

		if (isset($data['header_display']) && $data['header_display'] == 2)
		{
			$data['is_collapsed'] = false;
			$data['hide_upshrink'] = true;
		}
		else
			$data['hide_upshrink'] = false;

		if (!isset($data['header_display']))
			$data['header_display'] = 1;

		call_integration_hook('integrate_sb_process_module', array(&$data));

		if (!$data['hide_upshrink'])
			$context['javascript_vars'][$data['type'] . 'toggle_' . $data['id']] = 'new smc_Toggle({
			bToggleEnabled:  ' . (!$data['hide_upshrink'] ? 'true' : 'false') . ',
			bCurrentlyCollapsed: ' . ($data['is_collapsed'] ? 'true' : 'false') . ',
			aSwappableContainers: [' . (empty($modSettings['sb_module_enable_animations']) ? '
				\'' . $data['type'] . 'module_' . $data['id'] . '\'' : '') . '
			],
			aSwapImages: [
				{
					sId: \'' . $data['type'] . 'collapse_' . $data['id'] . '\',
					srcExpanded: smf_images_url + \'/collapse.gif\',
					altExpanded: ' . JavaScriptEscape($txt['show']) . ',
					srcCollapsed: smf_images_url + \'/expand.gif\',
					altCollapsed: ' . JavaScriptEscape($txt['show']) . '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ' . ($user_info['is_guest'] ? 'false' : 'true') . ',
				sOptionName: \'' . $data['type'] . 'collapse_' . $data['id'] . '\',
				sSessionVar: smf_session_var,
				sSessionId: smf_session_id
			},
			oCookieOptions: {
				bUseCookie: ' . ($user_info['is_guest'] ? 'true' : 'false') . ',
				sCookieName: \'' . $data['type'] . 'collapse_' . $data['id'] . '\'
			}
		});';

		return $data;
	}

	private function load_module_context($installed_mods = array(), $new_layout = false)
	{
		global $context, $txt;

		// Default module configurations.
		$module_context = array(
			'announce' => array(
				'module_title' => array(
					'value' => $txt['sb_module_announce'],
				),
				'module_icon' => array(
					'value' => 'world.png',
				),
				'msg' => array(
					'type' => 'large_text',
					'value' => 'Welcome to Shiny Bear!',
				),
			),
			'usercp' => array(
				'module_title' => array(
					'value' => $txt['sb_module_usercp'],
				),
				'module_icon' => array(
					'value' => 'heart.png',
				),
				'module_link' => array(
					'value' => 'action=profile',
				),
			),
			'stats' => array(
				'module_title' => array(
					'value' => $txt['sb_module_stats'],
				),
				'module_icon' => array(
					'value' => 'stats.png',
				),
				'module_link' => array(
					'value' => 'action=stats',
				),
				'stat_choices' => array(
					'type' => 'callback',
					'callback_func' => 'checklist',
					'preload' => create_function('&$field', '
						$field[\'options\'] = sb_list_checks($field[\'value\'], array(\'members\', \'posts\', \'topics\', \'categories\', \'boards\', \'ontoday\', \'onever\'), array(), $field[\'label\'], 0);

						return $field;'),
					'value' => '0,1,2,5,6',
					'order' => true,
				),
			),
			'online' => array(
				'module_title' => array(
					'value' => $txt['sb_module_online'],
				),
				'module_icon' => array(
					'value' => 'user.png',
				),
				'module_link' => array(
					'value' => 'action=who',
				),
				'online_pos' => array(
					'type' => 'select',
					'value' => '0',
					'options' => 'top;bottom',
				),
				'show_online' => array(
					'type' => 'callback',
					'callback_func' => 'checklist',
					'preload' => create_function('&$field', '
						$field[\'options\'] = sb_list_checks($field[\'value\'], array(\'users\', \'buddies\', \'guests\', \'hidden\', \'spiders\'), array(), $field[\'label\'], 0);

						return $field;'),
					'value' => '0,1,2',
					'order' => true,
				),
				'online_groups' => array(
					'type' => 'callback',
					'callback_func' => 'list_groups',
					'preload' => create_function('&$field', '
						$field[\'options\'] = sb_list_groups($field[\'value\'], \'-1,0,3\');

						return $field;'),
					'value' => '-3',
				),
			),
			'news' => array(
				'module_title' => array(
					'value' => $txt['sb_module_news'],
				),
				'module_icon' => array(
					'value' => 'cog.png',
				),
				'board' => array(
					'type' => 'select',
					'preload' => create_function('&$field', '
						$field[\'options\'] = sb_list_boards();

						return $field;'),
						'value' => '1',
					),
				'limit' => array(
					'type' => 'int',
					'value' => '5',
				),
			),
			'recent' => array(
				'module_title' => array(
					'value' => $txt['sb_module_topics'],
				),
				'module_icon' => array(
					'value' => 'pencil.png',
				),
				'module_link' => array(
					'value' => 'action=recent',
				),
				'post_topic' => array(
					'type' => 'select',
					'value' => 'topics',
					'options' => 'posts;topics',
				),
				'show_avatars' => array(
					'type' => 'check',
					'value' => '1',
				),
				'num_recent' => array(
					'type' => 'int',
					'value' => '10',
				),
			),
			'search' => array(
				'module_title' => array(
					'value' => $txt['sb_module_search'],
				),
				'module_icon' => array(
					'value' => 'magnifier.png',
				),
				'module_link' => array(
					'value' => 'action=search',
				),
			),
			'calendar' => array(
				'module_title' => array(
					'value' => $txt['sb_module_calendar'],
				),
				'module_icon' => array(
					'value' => 'cal.png',
				),
				'display' => array(
					'type' => 'select',
					'value' => '0',
					'options' => 'month;info',
				),
				'show_months' => array(
					'type' => 'select',
					'value' => '1',
					'options' => 'year;asdefined',
				),
				'previous' => array(
					'type' => 'int',
					'value' => '1',
				),
				'next' => array(
					'type' => 'int',
					'value' => '1',
				),
				'show_options' => array(
					'type' => 'callback',
					'callback_func' => 'checklist',
					'preload' => create_function('&$field', '
						$field[\'options\'] = sb_list_checks($field[\'value\'], array(\'events\', \'holidays\', \'birthdays\'), array(), $field[\'label\'], 0);

						return $field;'),
					'value' => '0,1,2',
					'order' => true,
				),
			),
			'poll' => array(
				'module_title' => array(
					'value' => $txt['sb_module_poll'],
				),
				'module_icon' => array(
					'value' => 'comments.png',
				),
				'options' => array(
					'type' => 'select',
					'value' => '0',
					'options' => 'showPoll;topPoll;recentPoll',
				),
				'topic' => array(
					'type' => 'int',
					'value' => '0',
				),
			),
		);

		// Let other modules hook in to the system.
		call_integration_hook('integrate_load_module_fields', array(&$module_context));

		return $module_context;
	}
}

?>
