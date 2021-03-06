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
	 * @return bool whether this is an attachment, avatar, toggle of editor buttons, theme option, XML feed, popup, etc.
	 */
	private function canSkipAction($da_action)
	{
		$skipped_actions = array(
			'about:unknown' => true,
			'clock' => true,
			'dlattach' => true,
			'findmember' => true,
			'helpadmin' => true,
			'jsoption' => true,
			'likes' => true,
			'loadeditorlocale' => true,
			'modifycat' => true,
			'pm' => array('sa' => array('popup')),
			'profile' => array('area' => array('popup', 'alerts_popup')),
			'requestmembers' => true,
			'smstats' => true,
			'suggest' => true,
			'verificationcode' => true,
			'viewquery' => true,
			'viewsmfile' => true,
			'xmlhttp' => true,
			'.xml' => true,
		);
		call_integration_hook('integrate_skipped_actions', array(&$skipped_actions));
		$skip_this = false;
		if (isset($skipped_actions[$da_action]))
		{
			if (is_array($skipped_actions[$da_action]))
			{
				foreach ($skipped_actions[$da_action] as $subtype => $subnames)
					$skip_this |= isset($_REQUEST[$subtype]) && in_array($_REQUEST[$subtype], $subnames);
			}
			else
				$skip_this = isset($skipped_actions[$da_action]);
		}

		return $skip_this;
	}

	private function getMatch($da_action)
	{
		$match = (!empty($_REQUEST['board']) ? '[board]=' . $_REQUEST['board'] : (!empty($_REQUEST['topic']) ? '[topic]=' . (int) $_REQUEST['topic'] : (!empty($_REQUEST['page']) ? '[page]=' . $_REQUEST['page'] : $da_action)));

		return $match;
	}

	private function getGeneralMatch()
	{
		$general_match = (!empty($_REQUEST['board']) ? '[board]' : (!empty($_REQUEST['topic']) ? '[topic]' : (!empty($_REQUEST['page']) ? '[page]' : (!empty($_REQUEST['action']) ? '[all_actions]' : '[home]'))));

		return $general_match;
	}

	private function getMatchedLayout($da_action)
	{
		global $smcFunc, $user_info;

		$match = $this->getMatch($da_action);
		$general_match = $this->getGeneralMatch();

		$request = $smcFunc['db_query']('', '
			SELECT
				el.id_layout, action, id_member
			FROM {db_prefix}sb_layouts AS el
				INNER JOIN {db_prefix}sb_layout_actions AS ela ON (ela.id_layout = el.id_layout AND ela.action IN ({string:match}, {string:general_match}))
			WHERE el.id_member IN (0, {int:current_member})',
			array(
				'match' => $match,
				'general_match' => $general_match,
				'current_member' => $user_info['id'],
			)
		);

		while (list ($id_layout, $action, $id_member) = $smcFunc['db_fetch_row']($request))
		{
			if (
				($action == $general_match && $id_member == $user_info['id'])
				|| ($action == $match && $id_member == $user_info['id'])
				|| ($action == $general_match && $id_member == 0)
				|| ($action == $match && $id_member == 0)
			)
				return $id_layout;
		}

		return 0;
	}

	/**
	 * Add Forum to the linktree.
	 */
	public function addForumLinktree()
	{
		global $context, $board, $topic, $scripturl, $txt;

		if (!empty($board) || !empty($topic) || $context['current_action'] == 'forum')
			array_splice($context['linktree'], 1, 0, array(array(
					'name' => $txt['forum'],
					'url' => $scripturl . '?action=forum',
				)));
	}

	/**
	 * Start to load the portal.
	 */
	public function load($init_action = '')
	{
		global $context, $boarddir, $boardurl;

		// These puppies are evil >:D
		unset($_GET['PHPSESSID'], $_GET['theme']);

		// We want the first item in the requested URI
		reset($_GET);
		$uri = key($_GET);

		// If a registered SMF action was called, use that instead
		$da_action = !empty($uri) ? !empty($context['current_action']) ? $context['current_action'] : '[' . $uri . ']' : '';
		$da_action = !empty($init_action) ? $init_action : $da_action;

		if ($this->canSkipAction($da_action))
			return;

		$this->addForumLinktree();

		$context['sb_icon_url'] = $boardurl . '/sb_extra/module_icons/';
		$context['sb_module_image_url'] = $boardurl . '/sb_extra/module_images/';
		$context['sbadmin_image_url'] = $boardurl . '/sb_extra/images/admin';
		$context['sb_module_modules_dir'] = $boarddir . '/sb_extra/modules';
		$context['sb_plugins_dir'] = $boarddir . '/sb_extra/plugins';
		$context['sb_plugins_url'] = $boardurl . '/sb_extra/plugins';
		$context['sb_module_icon_url'] = $boardurl . '/sb_extra/module_icons';
		$context['sb_module_icon_dir'] = $boarddir . '/sb_extra/module_icons';
		$context['sb_module_template'] = $boarddir . '/sb_extra/module_templates';

		$this->loadLayout($da_action);
	}

	protected function loadLayout($url, $return = false)
	{
		global $smcFunc, $context, $scripturl, $txt, $user_info;

		if (is_string($url))
			$url = $this->getMatchedLayout($url);

		$request = $smcFunc['db_query']('', '
			SELECT
				*, elp.id_layout_position
			FROM {db_prefix}sb_layouts AS el
				LEFT JOIN {db_prefix}sb_layout_positions AS elp ON (elp.id_layout = el.id_layout)
				LEFT JOIN {db_prefix}sb_module_positions AS emp ON (emp.id_layout_position = elp.id_layout_position)
				LEFT JOIN {db_prefix}sb_modules AS em ON (em.id_module = emp.id_module)
			WHERE el.id_layout = {int:id_layout}',
			array(
				'id_layout' => $url,
			)
		);
		$num = $smcFunc['db_num_rows']($request);
		if (empty($num))
			return;

		$sb_modules = array();
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
					foreach ($sb_modules[$k][$key] as $pos => $mod)
					{
						if ($pos != 'modules' || !is_array($sb_modules[$k][$key][$pos]))
							continue;

						ksort($sb_modules[$k][$key][$pos]);
					}
		}

		$module_context = $this->process_module_context($this->load_module_context(), $loaded_ids);
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
				name, type, value
			FROM {db_prefix}sb_module_positions AS emp
				JOIN {db_prefix}sb_modules AS em ON (em.id_module = emp.id_module)
				JOIN {db_prefix}sb_module_field_data AS emd ON (emd.id_module_position = emp.id_position)
			WHERE emp.id_position IN ({array_int:loaded_ids})',
			array(
				'loaded_ids' => $loaded_ids,
			)
		);

		while (list ($name, $type, $value) = $smcFunc['db_fetch_row']($request))
			$module_context[$type][$name]['value'] = $value;

		return $module_context;
	}

	private function process_module($module_context, $data, $full_layout)
	{
		global $context, $modSettings, $settings, $options, $txt, $user_info, $scripturl, $smcFunc;

		$fields = $module_context[$data['type']];

		$data['module_title'] = $fields['module_title']['value'];

		if ($full_layout === false)
			return $data;

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
			if (empty(parse_url($fields['module_link']['value'], PHP_URL_SCHEME)))
				$fields['module_link']['value'] = $scripturl . '?' . $fields['module_link']['value'];

			$data['module_title'] = '<a href="' . $fields['module_link']['value'] . '" target="' . $data['module_target'] . '">' . $data['module_title'] . '</a>';
		}

		if (!empty($fields))
		{
			$fields2 = $fields;
			$fields = array();

			foreach ($fields2 as $key => $field)
				if (isset($field['type']))
					$fields[$key] = $field['value']; //loadParameter(array(), $field['type'], $field['value']);
		}

		$module = 'ShinyBear\Modules\\' . $data['type'];
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
					'preload' => function($field)
					{
						$field['options'] = sb_list_checks($field['value'], array('members', 'posts', 'topics', 'categories', 'boards', 'ontoday', 'onever'), array(), $field['label'], 0);

						return $field; },
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
					'preload' => function($field)
					{
						$field['options'] = sb_list_checks($field['value'], array('guests', 'spiders', 'buddies', 'hidden'), array(), $field['label'], 0);

						return $field; },
					'value' => '0,1,3',
					'order' => true,
				),
				'online_groups' => array(
					'type' => 'callback',
					'callback_func' => 'list_groups',
					'preload' => function($field)
					{
						$field['options'] = sb_list_groups($field['value'], '-1,0,3');

						return $field; },
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
					'preload' => function($field)
					{
						$field['options'] = sb_list_boards();

						return $field; },
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
					'preload' => function($field)
					{
						$field['options'] = sb_list_checks($field['value'], array('events', 'holidays', 'birthdays'), array(), $field['label'], 0);

						return $field; },
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
