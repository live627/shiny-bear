<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
class stats extends Module
{
	public function output()
	{
	global $txt, $smcFunc, $scripturl, $settings, $modSettings, $context;

	// Grab the params, if they exist.
	if (is_array($this->fields))
	{
		if (empty($this->fields['stat_choices']))
		{
			echo $this->error();
			return;
		}
		else
			$stat_choices = explode(',', $this->fields['stat_choices']);

		$totals = array();

		if (isset($stat_choices[3]))
		{
			// How many cats? Er... categories. Not cats...xD
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(id_cat)
				FROM {db_prefix}categories');
			list ($totals['cats']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		if (isset($stat_choices[4]))
		{
			// How many boards?
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(id_board)
				FROM {db_prefix}boards
				WHERE redirect = {string:blank_redirect}',
				array(
					'blank_redirect' => '',
				)
			);
			list ($totals['boards']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		// Start the output.
		echo '
					<ul class="stats bullet">';

		foreach ($stat_choices as $type)
		{
			echo '
						<li>';

			switch ($type)
			{
				case 0:
					echo $txt['total_members'] . ': <a href="' . $scripturl . '?action=mlist">' . comma_format($modSettings['totalMembers']) . '</a>';
					break;
				case 1:
					echo $txt['total_posts'] . ': ' . comma_format($modSettings['totalMessages']);
					break;
				case 2:
					echo $txt['total_topics'] . ': ' . comma_format($modSettings['totalTopics']);
					break;
				case 3:
					echo $txt['total_cats'] . ': ' . comma_format($totals['cats']);
					break;
				case 4:
					echo $txt['total_boards'] . ': ' . comma_format($totals['boards']);
					break;
				case 5:
					echo $txt['most_online_today'] . ': ' . comma_format($modSettings['mostOnlineToday']);
					break;
				default: // case 6:
					echo $txt['most_online_ever'] . ': ' . comma_format($modSettings['mostOnline']);
					break;
			}
			echo '</li>';
		}
		echo '
					</ul>';

		// No longer need this.
		unset($totals);

	}
	else
		echo $this->error();
}
}
