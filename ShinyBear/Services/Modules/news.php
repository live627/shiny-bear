<?php
// Version: 1.0: news.php
namespace ShinyBear\Services\Modules;

if (!defined('SMF')) {
	die('Hacking attempt...');
}

/**
 * @package ShinyBear
 * @since 1.0
 */
class news extends Module
{
	/**
	 * Cuts a string up until a given number of words.
	 * - Doesn't slice words. It CAN interrupt a sentence, however...
	 * - Preserves all whitespace characters.
	 *
	 * @access private
	 * @since 1.0
	 * @param string $string The input string
	 * @param int $limit Maximum number of words to show. Default is 70.
	 * $param string $rsb What to append if $string contains more words than specified by 4max. Default is three dots.
	 *
	 * @return string The truncated string.
	 */
	private function truncate($string, $limit = 70, $rsb = '...')
	{
		$words = preg_split('/(\s)+/', $string, $limit + 1, PREG_SPLIT_DELIM_CAPTURE);
		$newstring = '';
		$numwords = 0;

		foreach ($words as $k => $word)
			if (preg_match('/(\S)+/', $word))
			{
				if ($numwords < $limit)
				{
					$newstring .= $word;
					if (isset($words[$k + 1]) && preg_match('/(\s)+/', $words[$k + 1]))
					{
						$newstring .= $words[$k + 1];
						++$numwords;
					}
				}
			}

		if ($numwords >= $limit)
			$newstring .= $rsb;

		return $newstring;
	}

	/**
	 * Fetches topics from a board.
	 *
	 * This function is split into three basic queries:
	 * - Fetches the specified board. If the user cannot see or wants to ignore it, screw it.
	 * - Gets the list of topiics. Returns an empty array if none are found.
	 * - And finally, the third query actually fetches the meat and bone of the first message in each topic.
	 *
	 * What is the use of all this without caching? We love cash! Especially greenbacks!
	 *
	 * @access private
	 * @since 1.0
	 * @param int $board The ID of the board to get. Required.
	 * @param int $limit Maximum number of topics to show. Default is 5.
	 * $param bool $ignore Whether or not to honor ignored boards. Default is false.
	 *
	 * @return array All the posts found.
	 */
	private function boardNews($board, $limit = 5, $ignore = false)
	{
		global $scripturl, $settings, $smcFunc, $modSettings;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE b.id_board = {int:current_board}
				AND {query' . ($ignore ? '_wanna' : '') . '_see_board}
			LIMIT 1',
			array(
				'current_board' => $board,
			)
		);

		if ($smcFunc['db_num_rows']($request) == 0)
			return array();

		list ($board) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_first_msg
			FROM {db_prefix}topics
			WHERE id_board = {int:current_board}' . ($modSettings['postmod_active'] ? '
				AND approved = {int:is_approved}' : '') . '
			LIMIT ' . $limit,
			array(
				'current_board' => $board,
				'is_approved' => 1,
			)
		);

		$post_list = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$post_list[] = $row['id_first_msg'];
		$smcFunc['db_free_result']($request);

		if (empty($post_list))
			return array();

		$request = $smcFunc['db_query']('', '
			SELECT
				m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
				t.num_replies, t.num_views, m.body, m.smileys_enabled, m.id_msg, m.icon,
				t.id_topic, m.id_member
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE t.id_first_msg IN ({array_int:post_list})',
			array(
				'post_list' => $post_list,
			)
		);

		$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'poll', 'moved', 'recycled', 'wireless');
		$icon_sources = array();
		foreach ($stable_icons as $icon)
			$icon_sources[$icon] = 'images_url';

		$can_reply_own = allowedTo('post_reply_own');
		$can_reply_any = allowedTo('post_reply_any');
		$can_moderate = allowedTo('moderate_board');

		$posts = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$row['body'] = $this->truncate(strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br />' => "\n"))), 10);
			$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']  . '-prv');

			// Censor the subject.
			censorText($row['subject']);
			censorText($row['body']);

			$color_class = '';
			// Pinned topics should get a different color, too.
			if (!empty($row['is_sticky']))
				$color_class .= ' sticky';
			// Locked topics get special treatment as well.
			if (!empty($row['locked']))
				$color_class .= ' locked';

			// Build the array.
			$posts[$row['id_msg']] = array(
				'id' => $row['id_msg'],
				'subject' => $row['subject'],
				'replies' => $row['num_replies'],
				'views' => $row['num_views'],
				'short_subject' => shorten_subject($row['subject'], 25),
				'preview' => $row['body'],
				'time' => timeformat($row['poster_time']),
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#new" rel="nofollow">' . $row['subject'] . '</a>',
				'poster' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
				'icon' => '<img src="' . $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.png" class="icon" alt="' . $row['icon'] . '" />',
				'can_reply' => !empty($row['locked']) ? $can_moderate : $can_reply_any || ($can_reply_own && $row['first_id_member'] == $user_info['id']),
				'style' => $color_class,
			);
		}

		$smcFunc['db_free_result']($request);

		krsort($posts);
		return $posts;
	}

	public function output()
	{
		global $context, $txt, $options, $scripturl;

		$board = empty($this->fields['board']) ? 1 : $this->fields['board'];
		$limit = empty($this->fields['limit']) ? 5 : $this->fields['limit'];

		// Store the board news
		$input = $this->boardNews($board, $limit);

		// Default - Any content?
		if (empty($input))
		{
			echo $this->error('empty');
			return;
		}

		$use_bg2 = true;
		foreach ($input as $topic)
		{
			$use_bg2 = !$use_bg2;

			echo '
						<div class="title_bar">
							<h3 class="titlebg">
								', $topic['icon'], '
								', $topic['subject'], '
							</h3>
						</div>
						<div class="', $use_bg2 ? 'windowbg2' : 'windowbg', $topic['style'], '">';

			echo '
							<p class="smalltext">', $txt['posted_by'], ' ', $topic['poster'], ' | ', $topic['time'], '
							(', $topic['views'], ' ', $txt['views'], ')';

			if (!empty($topic['replies']) || $topic['can_reply'])
			{
				if (!empty($topic['replies']))
					echo '
							<a href="', $topic['href'], '">', $topic['replies'], ' ', $txt['replies'], '</a>';

					if ($topic['can_reply'])
						echo ' | ';

				if ($topic['can_reply'])
				{
					// If quick reply is open, point directly to it, otherwise use the regular reply page
					if (empty($options['display_quick_reply']) || $options['display_quick_reply'] != 2)
						$reply_url = $scripturl . '?action=post;topic=' . $topic['id'] . '.0;last_msg=' . $topic['id'];
					else
						$reply_url = substr($topic['href'], 0, strpos($topic['href'], '#')) . '#quickreply';

					echo '
							<a href="', $reply_url, '">', $txt['reply'], '</a>';
				}

				echo '
							</p>';
			}

			echo '
							', $topic['preview'], '
						</div>';
		}
	}
}
