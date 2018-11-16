<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
class online extends Module
{
	public function output()
	{
		global $context, $modSettings, $sourcedir, $txt, $user_info;

		// Get the user online list.
		require_once($sourcedir . '/Subs-MembersOnline.php');
		$membersOnlineOptions = array(
			'show_hidden' => allowedTo('moderate_forum'),
			'sort' => 'log_time',
			'reverse_sort' => true,
		);
		$membersOnlineStats = getMembersOnlineStats($membersOnlineOptions);
		$context['show_buddies'] = !empty($user_info['buddies']);
		$context['membergroups'] = cache_quick_get('membergroup_list', 'Subs-Membergroups.php', 'cache_getMembergroupList', array());

		// Handle hidden users and buddies.
		$bracketList = array();
		if ($context['show_buddies'])
			$bracketList[] = comma_format($context['num_buddies']) . ' ' . ($context['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
		if (!empty($context['num_spiders']))
			$bracketList[] = comma_format($context['num_spiders']) . ' ' . ($context['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
		if (!empty($context['num_users_hidden']))
			$bracketList[] = comma_format($context['num_users_hidden']) . ' ' . $txt['hidden'];

		if (!empty($bracketList))
			echo ' (' . implode(', ', $bracketList) . ')';

		// Ready to begin the output of groups.
		echo '
						<ul class="flow_auto">';

		// Loading up all users
		foreach ($membersOnlineStats['online_groups'] as $group)
		{
			echo '
							<li><strong>' . $group['name'] . '</strong>:
								<ul class="sb_list_indent">';

			foreach ($membersOnlineStats['users_online'] as $user)
				if ($user['group'] == $group['id'])
					echo '
									<li>', $user['hidden'] ? '<em>' . $user['link'] . '</em>' : $user['link'] , '</li>';
			echo '
								</ul>
							</li>';

		}

		echo '
						</ul>';
	}
}
