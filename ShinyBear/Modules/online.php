<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
class online extends Module
{
	public function getTotals()
	{
		global $sourcedir, $txt, $user_info;

		// Get the user online list.
		require_once($sourcedir . '/Subs-MembersOnline.php');
		$membersOnlineOptions = array(
			'show_hidden' => allowedTo('moderate_forum'),
			'sort' => 'log_time',
			'reverse_sort' => true,
		);
		$membersOnlineStats = getMembersOnlineStats($membersOnlineOptions);
		$show_buddies = !empty($user_info['buddies']);
		$this->groups = array_map(
			function($group) use ($membersOnlineStats)
			{
				return array(
					$group['name'],
					array_filter(
						$membersOnlineStats['users_online'],
						function($user) use ($group)
						{
							return $user['group'] == $group['id'];
						}
					)
				);
			},
			$membersOnlineStats['online_groups']
		);

		if (!empty($membersOnlineStats['num_guests']))
			$this->totals[] = comma_format($membersOnlineStats['num_guests']) . ' ' . ($membersOnlineStats['num_guests'] == 1 ? $txt['guest'] : $txt['guests']);
		if (!empty($membersOnlineStats['num_spiders']))
			$this->totals[] = comma_format($membersOnlineStats['num_spiders']) . ' ' . ($membersOnlineStats['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
		if ($show_buddies)
			$this->totals[] = comma_format($membersOnlineStats['num_buddies']) . ' ' . ($membersOnlineStats['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
		if (!empty($membersOnlineStats['num_users_hidden']))
			$this->totals[] = comma_format($membersOnlineStats['num_users_hidden']) . ' ' . $txt['hidden'];
	}

	private $totals = array();
	private $groups = array();
	private $online_groups = array();
	private $show_online = array();

	public function __construct(array $fields = null)
	{
		parent::__construct($fields);

		$this->online_groups = array_flip(explode(',', $this->fields['online_groups']));
		$this->show_online = array_flip(explode(',', $this->fields['show_online']));
		$this->getTotals();
	}

	public function output()
	{
		if (!empty($this->totals))
			echo ' (' . implode(', ', $this->totals) . ')';

		echo '
						<ul class="flow_auto">';

		foreach ($this->groups as [$name, $users])
		{
			echo '
							<li><strong>' . $name . '</strong>:
								<ul class="sb_list_indent">';

			foreach ($users as $user)
				echo '
									<li>', $user['hidden'] ? '<em>' . $user['link'] . '</em>' : $user['link'], '</li>';

			echo '
								</ul>
							</li>';

		}

		echo '
						</ul>';
	}
}
