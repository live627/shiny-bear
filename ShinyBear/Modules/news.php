<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
class news extends Module
{
	/**
	 * Cuts a string up until a given number of words.
	 *
	 * - Doesn't slice words. It CAN interrupt a sentence, however...
	 * - Preserves all whitespace characters.
	 *
	 * @access private
	 * @since 1.0
	 * @param string $str The text string to split
	 * @param int $limit Maximum number of words to show. Default is 70.
	 * $param string $rsb What to append if $string contains more words than specified by 4max. Default is three dots.
	 *
	 * @return string The truncated string.
	 */
	private function truncate($str, $n = 300, $delim = '…')
	{
   if (strlen($str) > $n)
   {
	   preg_match('/^([\s\S]{1,' . $n . '})[\s]+?[\s\S]+/', $str, $matches);
	   return rtrim($matches[1]) . $delim;
   }
   else
	   return $str;
}

	/**
	 * Fetches topics from a board.
	 *
	 * This function is split into three basic queries:
	 *
	 * - Fetches the specified board. If the user cannot see or wants to ignore it, screw it.
	 * - Gets the list of topics. Returns an empty array if none are found.
	 * - And finally, the third query actually fetches the meat and bone of the first message in each topic.
	 *
	 * What is the use of all this without caching? We love cash! Especially greenbacks!
	 *
	 * @access private
	 * @since 1.0
	 * @param int $board The ID of the board to get. Required.
	 * @param int $limit Maximum number of topics to show. Default is 5.
	 *
	 * @return array All the posts found.
	 */
	private function boardNews($board, $limit = 5)
	{
		global $scripturl, $settings, $smcFunc, $modSettings, $user_info;

		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}topics AS t
			WHERE
				id_board = {int:current_board}' . ($modSettings['postmod_active'] ? '
				AND approved = {int:is_approved}' : '') . '
				AND {query_see_topic_board}
			ORDER BY id_topic DESC
			LIMIT ' . $limit,
			array(
				'current_board' => $board,
				'is_approved' => 1,
			)
		);

		$posts = array();
		while (list ($id_topic) = $smcFunc['db_fetch_row']($request))
			$posts[$id_topic] = array();
		$smcFunc['db_free_result']($request);

		if (empty($posts))
			return array();

		$request = $smcFunc['db_query']('', '
			SELECT
				m.subject, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
				t.num_replies, t.num_views, m.body, m.smileys_enabled, m.id_msg, m.icon,
				t.id_topic, m.id_member
			FROM {db_prefix}topics AS t
				JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE t.id_topic IN ({array_int:post_list})',
			array(
				'post_list' => array_keys($posts),
			)
		);

		$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'poll', 'moved', 'recycled', 'wireless');
		$icon_sources = array();
		foreach ($stable_icons as $icon)
			$icon_sources[$icon] = 'images_url';

		$boards_can = boardsAllowedTo(array('post_reply_own', 'post_reply_any', 'moderate_board'), true, false);
		$can_reply_own = $boards_can['post_reply_own'] === array(0) || in_array($boards_can['post_reply_own']);
		$can_reply_any = $boards_can['post_reply_any'] === array(0) || in_array($boards_can['post_reply_any']);
		$can_moderate = $boards_can['moderate_board'] === array(0) || in_array($boards_can['moderate_board']);

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$row['body'] = nl2br($this->truncate(strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br>' => "\n")))));

			// Censor the subject.
			censorText($row['subject']);
			censorText($row['body']);

			// Build the array.
			$posts[$row['id_topic']] = array(
				'subject' => $row['subject'],
				'replies' => $row['num_replies'],
				'views' => $row['num_views'],
				'preview' => $row['body'],
				'time' => timeformat($row['poster_time']),
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'poster' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
				'icon' => '<img src="' . $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.png" class="icon" alt="' . $row['icon'] . '" />',
				'can_reply' => !empty($row['locked']) ? $can_moderate : $can_reply_any || ($can_reply_own && $row['first_id_member'] == $user_info['id']),
			);
		}

		$smcFunc['db_free_result']($request);

		return $posts;
	}
	private $posts = array();

	public function __construct(array $fields = null)
	{
		global $context, $scripturl;

		parent::__construct($fields);

		$board = empty($this->fields['board']) ? 1 : $this->fields['board'];
		$limit = empty($this->fields['limit']) ? 5 : $this->fields['limit'];

		$this->posts = $this->boardNews($board, $limit);
	}

	public function output()
	{
		global $context, $txt, $options, $scripturl;

		if (empty($this->posts))
		{
			echo $this->error('empty');
			return;
		}

		foreach ($this->posts as $topic)
		{
			echo '
						<div class="sub_bar">
							<h4 class="subbg">
								', $topic['icon'], '
								<a href="', $topic['href'], '">', $topic['subject'], '</a>
							</h4>
						</div>
						<div class="padding">';

			echo '
							<p class="smalltext">', $txt['posted_by'], ' ', $topic['poster'], ' | ', $topic['time'], '
							(', $topic['views'], ' ', $txt['views'], ')';

			if (!empty($topic['replies']))
				echo '
							<a href="', $topic['href'], '">', $topic['replies'], ' ', $txt['replies'], '</a>';

			if ($topic['can_reply'])
				echo ' | <a href="', $topic['href'], '#quickreply">', $txt['reply'], '</a>';

				echo '
							</p>';

			echo '
							', $topic['preview'], '
						</div>';
		}
	}
}
