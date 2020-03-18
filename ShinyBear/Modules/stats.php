<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
class stats extends Module
{
	public function getTotals()
	{
		global $modSettings, $scripturl, $smcFunc;

		$kittens = 0;
		$planks = 0;

		if (isset($this->stat_choices[3]))
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(id_cat)
				FROM {db_prefix}categories');
			list ($kittens) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		if (isset($this->stat_choices[4]))
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(id_board)
				FROM {db_prefix}boards
				WHERE redirect = {string:blank_redirect}',
				array(
					'blank_redirect' => '',
				)
			);
			list ($planks) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		return array_intersect_key(array(
			['total_members', sprintf('<a href="%s?action=mlist">%s</a>', $scripturl, comma_format($modSettings['totalMembers']))],
			['total_posts', comma_format($modSettings['totalMessages'])],
			['total_topics', comma_format($modSettings['totalTopics'])],
			['total_cats', comma_format($kittens)],
			['total_boards', comma_format($planks)],
			['most_online_today', comma_format($modSettings['mostOnlineToday'])],
			['most_online_ever', comma_format($modSettings['mostOnline'])],
		), $this->stat_choices);
	}

	private $totals = array();
	private $stat_choices = array();

	public function __construct(array $fields = null)
	{
		parent::__construct($fields);

		$this->stat_choices = array_flip(explode(',', $this->fields['stat_choices']));
		if (!empty($this->stat_choices))
			$this->totals = $this->getTotals();
	}

	public function output()
	{
		global $txt;

		if (empty($this->totals))
		{
			echo $this->error('empty');
			return;
		}

		echo '
					<ul class="stats bullet">';

		foreach ($this->totals as [$var, $val])
			echo '
						<li>', $txt[$var] . ': ' . $val, '</li>';

		echo '
					</ul>';
	}
}
