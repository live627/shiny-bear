<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
class recent extends Module
{
	/**
	 * Fetches recent topics.
	 *
	 * This function is split into three basic queries:
	 *
	 * - Fetches the topics and their respective boards, ignoring those the
	 * user cannot see or wants to ignore. Returns an empty array if none are found.
	 * - If the logged user is not a guest, count the number of new posts per topic.
	 * - And finally, the third query actually fetches the meat and bone of the first message in each topic.
	 *
	 * Several major diifferences set this function apart from ssi_recentTopics():
	 *
	 * - The huge, scary, hulking query is split in two. Shaves time off here. Went
	 * from evaluating potentially (many) many null rows at a Cartesian product
	 * level, down to a known subset. Immediate savings.
	 * - Unread count for members.
	 * - Cache. Can never get enough.
	 *
	 * @access private
	 * @since 1.0
	 * @param int $num_recent Maximum number of topics to show. Default is 8.
	 * $param bool $me Whether or not to only show topics started by the current member. Default is false.
	 * $param bool $ignore Whether or not to honor ignored boards. Default is true.
	 * @param array $exclude_boards Boards to exclude as array values. Default is null.
	 * @param array $include_boards Boards to include as array values. Do note that, if specifiied, posts coming only from these boards will be counted. Default is null.
	 *
	 * @return array All the posts found.
	 */
	private function getTopics($num_recent = 8, $me = false, $ignore = true, array $exclude_boards = array(), array $include_boards = array())
	{
		global $context, $modSettings, $scripturl, $smcFunc, $user_info;

		if (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
			$exclude_boards[] = $modSettings['recycle_board'];

		// Find all the posts in distinct topics. Newer ones will have higher IDs.
		$request = $smcFunc['db_query']('', '
			SELECT
				t.id_topic, b.id_board, b.name AS board_name
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
			LIMIT ' . $num_recent,
			array(
				'include_boards' => empty($include_boards) ? '' : $include_boards,
				'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
				'min_message_id' => $modSettings['maxMsgID'] - 35 * min($num_recent, 5),
				'is_approved' => 1,
				'current_member' => $user_info['id'],
			)
		);
		$topics = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topics[$row['id_topic']] = $row;
		$smcFunc['db_free_result']($request);

		// Did we find anything? If not, bail.
		if (empty($topics))
			return array();
		$topic_list = array_keys($topics);

		// Count number of new posts per topic.
		if (!$user_info['is_guest'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT
					m.id_topic, COUNT(*) AS co
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
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$topics[$row['id_topic']] += $row;
			$smcFunc['db_free_result']($request);
		}

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
			$posts[$row['id_msg']] = array(
				'board' => array(
					'link' => '<a href="' . $scripturl . '?board=' . $topics[$row['id_topic']]['id_board'] . '.0">' . $topics[$row['id_topic']]['board_name'] . '</a>',
				),
				'topic' => $row['id_topic'],
				'poster' => array(
					'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
				),
				'subject' => $row['subject'],
				'replies' => $row['num_replies'],
				'views' => $row['num_views'],
				'short_subject' => shorten_subject($row['subject'], 25),
				'preview' => $row['body'],
				'time' => timeformat($row['poster_time'], '%a, ' . $time_fmt),
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
				'co' => isset($topics[$row['id_topic']]['co']) ? $topics[$row['id_topic']]['co'] : 0,
			);
		}
		$smcFunc['db_free_result']($request);

		krsort($posts);
		return $posts;
	}

	public function output()
	{
		global $context, $scripturl;

		$context['topics'] = $this->getTopics();

		$context['mark_read_button'] = array(
			'markread' => array(
				'text' => 'mark_as_read',
				'image' => 'markread.png',
				'url' => $scripturl . '?action=markasread;sa=all;' . $context['session_var'] . '=' . $context['session_id']
			),
		);
		call_integration_hook('integrate_mark_read_button');

		echo '
						<table class="w100 cp4 cs0 ba table_grid">';

		if (!empty($context['topics']))
			foreach ($context['topics'] as $post)
			{
				echo '
							<tr>
								<td class="w25">
									', $post['poster']['link'], '
								</td>
								<td class="w50">
									', $post['board']['link'], ' &gt; ';

				if ($context['user']['is_logged'] && !empty($post['co']))
					echo '<span class="new_posts">' . $post['co'] . '</span>';

				echo '<a href="', $post['href'], '">', $post['subject'], '</a>
								</td>
								<td class="w25">
									', $post['time'], '
								</td>
							</tr>';
			}
		else
			echo '
							<tr class="windowbg2">
								<td class="center">
									No messages...
								</td>
							</tr>';

		echo '
						</table>';

		if ($context['user']['is_logged'] && !empty($context['topics']))
			template_button_strip($context['mark_read_button'], 'right');
	}
}
