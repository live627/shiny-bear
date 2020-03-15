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
	 */
	public static function pre_log_stats(&$no_stat_actions)
	{
		$no_stat_actions['sbjs'] = true;
	}

	public static function buttons(&$buttons)
	{
		global $scripturl, $txt;

		if (!self::$isActive)
			return;

		self::array_insert ($buttons, 'home', array('forum' => array(
			'title' => (!empty($txt['forum']) ? $txt['forum'] : 'Forum'),
			'href' => $scripturl . '?action=forum',
			'show' => ,
			'action_hook' => true,
		)),'after');

		// Adding the Shiny Bear submenu to the Admin button.
		if (isset($buttons['admin']))
			$buttons['admin']['sub_buttons'] +=  = array(
				'sb' => array(
					'title' => $txt['sb'],
					'href' => $scripturl . '?action=admin;area=sbmodules;sa=sbmanmodules',
					'show' => allowedTo('admin_forum'),
					'is_last' => true,
				),
			);
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
/**
 * @param array      $array
 * @param int|string $position
 * @param mixed      $insert the data to add before or after the above key
 * @param string $where adding before or after
 */
public static function array_insert (&$array, $position, $insert, $where = 'before') {
    if (!is_int($position))
    {
        $position   = array_search($position, array_keys($array));

	// If the key is not found, just insert it at the end
	if ($position === false)
        $position   =count($array)-2;
}
	if ($where === 'after')
		$position += 1;
  $first = array_splice ($array, 0, $position);
  $array = array_merge ($first, $insert, $array);
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
			$context['template_layers'][] = 'portal';

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
	 * @param array $permissionGroups An array containing all possible permissions groups.
	 * @param array $permissionList An associative array with all the possible permissions.
	 */
	public static function load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
	{
		global $modSettings;

		loadLanguage('ShinyBearPermissions');
			$permissionList['membergroup'] += array(
				'sb_view' => array(false, 'sb', 'sb'),
			);
		if (empty($modSettings['sb_portal_mode']))
			$hiddenPermissions[] = 'sb_view';
	}

	public static function load_permission_levels(&$groupLevels)
	{
		$groupLevels['global']['restrict'][] = 'sb_view';
	}

	public static function illegal_guest_permissions()
	{
		global $context;

		$context['non_guest_permissions'][] = 'sb_view';
	}

	public static function reports_groupperm(&$disabled_permissions)
	{
		global $modSettings;

		if (empty($modSettings['sb_portal_mode']))
			$disabled_permissions[] = 'sb_view';
	}
}