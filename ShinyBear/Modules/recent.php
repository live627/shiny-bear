<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
class recent extends Module
{
	/**
	 * Fetches the topics and their respective boards, ignoring those that the
	 * user cannot see or wants to ignore. Returns an empty array if none are found.
	 *
	 * @access private
	 * @since 1.0
	 * @param int $num_recent Maximum number of topics to show. Default is 8.
	 * $param bool $me Whether or not to only show topics started by the current member. Default is false.
	 * $param bool $ignore Whether or not to honor ignored boards. Default is true.
	 * @param array $exclude_boards Boards to exclude as array values. Default is null.
	 * @param array $include_boards Boards to include as array values. Do note that, if specifiied, posts coming only from these boards will be counted. Default is null.
	 *
	 * @return array
	 */
	private function getTopics($num_recent = 8, $me = false, $ignore = true, array $exclude_boards = array(), array $include_boards = array())
	{
		global $context, $modSettings, $scripturl, $smcFunc, $user_info;

		if (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
			$exclude_boards[] = $modSettings['recycle_board'];

		// Find all the posts in distinct topics. Newer ones will have higher IDs.
		$request = $smcFunc['db_query']('', '
			SELECT
				t.id_topic, b.id_board, b.name
			FROM {db_prefix}topics AS t
				JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_last_msg >= {int:min_message_id}' . (empty($exclude_boards) ? '' : '
				AND b.id_board NOT IN ({array_int:exclude_boards})') . (empty($include_boards) ? '' : '
				AND b.id_board IN ({array_int:include_boards})') . '
				AND {query' . ($ignore ? '_wanna' : '') . '_see_board}' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}
				AND ml.approved = {int:is_approved}' : '') . ($me ? '
				AND t.id_member_started = {int:current_member}' : '') . '
				AND t.id_topic != 1
			ORDER BY id_topic DESC
			LIMIT ' . $num_recent,
			array(
				'include_boards' => $include_boards,
				'exclude_boards' => $exclude_boards,
				'min_message_id' => $modSettings['maxMsgID'] - 35 * min($num_recent, 5),
				'is_approved' => 1,
				'current_member' => $user_info['id'],
			)
		);
		$posts = array();
		while (list ($id_topic, $id_board, $name) = $smcFunc['db_fetch_row']($request))
			$posts[$id_topic] = '<a href="' . $scripturl . '?board=' . $id_board . '.0">' . $name . '</a>';
		$smcFunc['db_free_result']($request);

		return $posts;
	}

	/**
	 * If the logged user is not a guest, count the number of new posts per topic.
	 *
	 * @access private
	 * @since 1.0
	 * @param array $topic_list
	 *
	 * @return array
	 */
	private function getUnreadPostCount(array $topic_list)
	{
		global $smcFunc, $user_info;

		// Count number of new posts per topic.
		$posts = array();
		if (!$user_info['is_guest'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					m.id_topic, COUNT(*)
				FROM {db_prefix}messages AS m
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = m.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = m.id_board AND lmr.id_member = {int:current_member})
				WHERE
					m.id_topic IN ({array_int:topic_list})
					AND m.id_msg > COALESCE(lt.id_msg, lmr.id_msg, 0)
					AND approved = 1
				GROUP BY m.id_topic',
				array(
					'current_member' => $user_info['id'],
					'topic_list' => $topic_list
				)
			);
			while (list ($id_topic, $co) = $smcFunc['db_fetch_row']($request))
				$posts[$id_topic] = $co;
			$smcFunc['db_free_result']($request);
		}

		return $posts;
	}

	/**
	 * Fetches the meat and bone of the first message in each topic.
	 *
	 * @access private
	 * @since 1.0
	 * @param array $topic_list
	 *
	 * @return array
	 */
	private function getPosts(array $topic_list)
	{
		global $scripturl, $smcFunc, $user_info;

		$request = $smcFunc['db_query']('', '
			SELECT
				t.id_topic, ml.poster_time, mf.subject, ml.id_topic, ml.id_member, ml.id_msg, t.num_replies, t.num_views,
				COALESCE(mem.real_name, ml.poster_name) AS poster_name, SUBSTRING(ml.body, 1, 384) AS body, ml.smileys_enabled, ml.icon
			FROM {db_prefix}topics AS t
				JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
			WHERE t.id_topic IN ({array_int:topic_list})',
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topic_list,
			)
		);

		$posts = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			censorText($row['subject']);
			censorText($row['body']);

			if (time() - 86400 > $row['poster_time'])
			{
				if (time() - 31557600 < $row['poster_time'])
					$time_fmt = '%d %b';
				else
					$time_fmt = '%d %b %Y';
			}
			else
			{
				// What does the user want the time formatted as?
				$s = strpos($user_info['time_format'], '%S') === false ? '' : ':%S';
				if (strpos($user_info['time_format'], '%H') === false && strpos($user_info['time_format'], '%T') === false)
				{
					$h = strpos($user_info['time_format'], '%l') === false ? '%I' : '%l';
					$time_fmt = $h . ':%M' . $s . ' %p';
				}
				else
					$time_fmt = '%H:%M' . $s;
			}

			// Build the array.
			$posts[$row['id_topic']] = array(
				'poster_link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
				'subject' => $row['subject'],
				'replies' => $row['num_replies'],
				'views' => $row['num_views'],
				'preview' => $row['body'],
				'time' => timeformat($row['poster_time'], '%a, ' . $time_fmt),
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
			);
		}
		$smcFunc['db_free_result']($request);

		return $posts;
	}
	private $topics = array();
	private $unreadPostCount = array();
	private $posts = array();

	public function __construct(array $fields = null)
	{
		global $context, $scripturl;

		parent::__construct($fields);

		$this->topics = $this->getTopics();
		if (!empty($this->topics))
		{
			$topic_list = array_keys($this->topics);
			$this->unreadPostCount = $this->getUnreadPostCount($topic_list);
			$this->posts = $this->getPosts();
		}
		$context['mark_read_button'] = array(
			'markread' => array(
				'text' => 'mark_as_read',
				'image' => 'markread.png',
				'url' => $scripturl . '?action=markasread;sa=all;' . $context['session_var'] . '=' . $context['session_id']
			),
		);
		call_integration_hook('integrate_mark_read_button');
	}

	public function output()
	{
		global $context, $txt;

		echo '
						<table class="w100 cp4 cs0 ba table_grid">';

		if (empty($this->posts))
			echo '
							<tr>
								<td class="centertext">
									',  $txt['no_messages'], '
								</td>
							</tr>';
		else
			foreach ($this->posts as $id_topic => $post)
			{
				echo '
							<tr>
								<td class="w25">
									', $post['poster_link'], '
								</td>
								<td class="w50">
									', $this->topics[$id_topic], ' &gt; ';

				if ($context['user']['is_logged'] && !empty($this->unreadPostCount[$id_topic]))
					echo '<span class="new_posts">' . $this->unreadPostCount[$id_topic] . '</span>';

				echo '<a href="', $post['href'], '">', $post['subject'], '</a>
								</td>
								<td class="w25">
									', $post['time'], '
								</td>
							</tr>';
			}

		echo '
						</table>';

		if ($context['user']['is_logged'] && !empty($this->posts))
			template_button_strip($context['mark_read_button'], 'right');
	}
}
