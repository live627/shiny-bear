<?php

namespace ShinyBear;

/**
 * @package ShinyBear
 * @since 1.0
 */
class Integration
{
	private static $sbHome = false;
	private static $isActive = false;

	public static function pre_load()
	{
		global $modSettings;

		$modSettings['sb_portal_mode'] = true;
		self::$isActive=!empty($modSettings['sb_portal_mode']) && allowedTo('sb_view');

		loadLanguage('ShinyBear');
	}

	/**
	 * Insert the actions needed by this mod
	 *
	 * @param array $actions An array containing all possible SMF actions.
	 * @return void
	 */
	public static function actions(&$actions)
	{
		$actions['sb'] = array('sb_source/EnvisionPortal.php', 'envisionActions');
		$actions['sbjs'] = array('sb_source/EnvisionPortal.php', 'envisionFiles');
		$actions['forum'] = array('BoardIndex.php', 'BoardIndex');
	}

	/**
	 * Map namespaces to directories
	 *
	 * @param array $classMap
	 */
	public static function autoload(&$classMap)
	{
		$classMap['ShinyBear\\'] = 'ShinyBear/';
	}

	/**
	 * Set the default action
	 */
	public static function default_action()
	{
		global $context, $sourcedir, $txt;

		if (!self::$isActive)
		{
			require_once($sourcedir . '/BoardIndex.php');

			call_user_func('BoardIndex');
		}
		else
		{
			$context['sub_template'] = 'portal';
			$context['page_title'] = $context['forum_name'] . ' - ' . $txt['home'];
			self::$sbHome = true;
		}
	}

	/**
	 * Add our AJAX action to the list
	 *
	 * @param array &$no_stat_actions Array of all actions which may not be logged.
	 * @return void
	 */
	public static function pre_log_stats(&$no_stat_actions)
	{
		$no_stat_actions[] = 'sbjs';
	}

	public static function buttons(&$buttons)
	{
		global $txt, $context, $scripturl, $modSettings;

		if (!self::$isActive)
			return;

		$new = array(
			'title' => (!empty($txt['forum']) ? $txt['forum'] : 'Forum'),
			'href' => $scripturl . '?action=forum',
			'show' => !empty($modSettings['sb_portal_mode']) && allowedTo('sb_view'),
			'action_hook' => true,
		);

		$new_buttons = array();
		foreach ($buttons as $area => $info)
		{
			$new_buttons[$area] = $info;
			if ($area == 'home')
				$new_buttons['forum'] = $new;
		}

		$buttons = $new_buttons;

		// Adding the Shiny Bear submenu to the Admin button.
		if (isset($buttons['admin']))
		{
			$new = array(
				'sb' => array(
					'title' => $txt['sb'],
					'href' => $scripturl . '?action=admin;area=sbmodules;sa=sbmanmodules',
					'show' => allowedTo('admin_forum'),
					'is_last' => true,
				),
			);

			$i = 0;
			$new_subs = array();
			$count = count($buttons['admin']['sub_buttons']);
			foreach ($buttons['admin']['sub_buttons'] as $subs => $admin)
			{
				$i++;
				$new_subs[$subs] = $admin;
				if ($subs == 'permissions')
				{
					$permissions = true;
					// Remove is_last if set.
					if (isset($buttons['admin']['sub_buttons']['permissions']['is_last']))
						unset($buttons['admin']['sub_buttons']['permissions']['is_last']);

						$new_subs['sb'] = $new['sb'];

					// set is_last to sb if it's the last.
					if ($i != $count)
						unset($new_subs['sb']['is_last']);
				}
			}

			// If permissions doesn't exist for some reason, we'll put it at the end.
			if (!isset($permissions))
				$buttons['admin']['sub_buttons'] += $new;
			else
				$buttons['admin']['sub_buttons'] = $new_subs;
		}
	}

	/**
	 * Standard method to tweak the current action when using a custom
	 * action as forum index.
	 *
	 * @param string $current_action
	 */
	public static function fixCurrentAction(&$current_action)
	{
		if (!self::$isActive)
			return;

		if ($current_action == 'home' && empty(self::$sbHome))
			$current_action = 'forum';
	}

	public static function admin_areas2(&$admin_areas)
	{
		global $txt;

		if (!self::$isActive)
			return $admin_areas;

		$sb = array(
			'title' => $txt['sb'],
			'areas' => array(
				'sbconfig' => array(
					'label' => $txt['sb_admin_config'],
					'file' => 'sb_source/ManageEnvisionSettings.php',
					'function' => 'Configuration',
					'icon' => 'sbconfiguration.png',
					'subsections' => array(
						'sbinfo' => array($txt['sb_admin_information'], ''),
						'sbgeneral' => array($txt['sb_admin_general'], ''),
					),
				),
				'sbmodules' => array(
					'label' => $txt['sb_admin_modules'],
					'file' => 'sb_source/ManageEnvisionModules.php',
					'function' => 'Modules',
					'icon' => 'sbmodules.png',
					'subsections' => array(
						'sbmanmodules' => array($txt['sb_admin_manage_modules'], ''),
						'sbaddmodules' => array($txt['sb_admin_add_modules'], ''),
					),
				),
			),
		);

		$new_admin_areas = array();
		foreach ($admin_areas as $area => $info)
		{
			$new_admin_areas[$area] = $info;
			if ($area == 'config')
				$new_admin_areas['sb'] = $sb;
		}

		$admin_areas = $new_admin_areas;
	}

	public static function load_theme()
	{
		global $context, $maintenance, $modSettings, $user_info;

		// Don't continue if they're a guest and guest access is off.
		if (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'])
			return;

		// XML mode? Nothing more is required of us...
		if (isset($_REQUEST['xml']))
			return;

		if (($maintenance && !allowedTo('admin_forum')) || !self::$isActive)
			return;

		// Load the portal layer, making sure we didn't aleady add it.
		if (!empty($context['template_layers']) && !in_array('portal', $context['template_layers']))
		{
			// Checks if the forum is in maintenance, and if the portal is disabled.
			if (($maintenance && !allowedTo('admin_forum')) || empty($modSettings['sb_portal_mode']) || !allowedTo('sb_view'))
				$context['template_layers'] = array('html', 'body');
			else
				$context['template_layers'][] = 'portal';
		}

		loadLanguage('ShinyBear');
		loadTemplate('ShinyBear');
		loadCSSFile('shinybear.css', array('default_theme' => true), 'sb');

		// Kick off time!
		$sb = new ShinyBear();
		$sb->load();
	}

	/**
	 * Global permissions used by this mod per user group
	 *
	 * There is only permissions to post new status and comments on any profile because people needs to be able to post in their own profiles by default te same goes for deleting, people are able to delete their own status/comments on their own profile page.
	 * @param array $permissionGroups An array containing all possible permissions groups.
	 * @param array $permissionList An associative array with all the possible permissions.
	 * @return void
	 */
	public static function load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
	{
		global $context;

		loadLanguage('ShinyBearPermissions');

		// If this is a guest limit the available permissions.
		if (isset($context['group']['id']) && $context['group']['id'] == -1)
			$permissionList['membergroup'] += array(
				'sb_view' => array(false, 'sb', 'sb'),
			);
		else
			$permissionList['membergroup'] += array(
				'sb_view' => array(false, 'sb', 'sb'),
			);
	}
}
