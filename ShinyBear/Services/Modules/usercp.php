<?php
// Version: 1.0: usercp.php
namespace ShinyBear\Services\Modules;

if (!defined('SMF')) {
	die('Hacking attempt...');
}

/**
 * @package ShinyBear
 * @since 1.0
 */
class usercp extends Module
{
	/*
	 * Gets the current URL as specified by $_SERVER['REQUEST_URL']
	 */
	private function getUrl()
	{
		global $scripturl;

		$cur_url = $_SERVER['REQUEST_URL'];

		return $cur_url;
	}

	function output()
	{
		global $context, $txt, $scripturl, $settings, $user_info;

		// Only display this info if we are logged in.
		if (!$user_info['is_guest'])
		{
			// Set the logout variable.
			$context['sb_usercp'] = array(
				'profile' => array('text' => 'profile', 'lang' => true, 'url' => $scripturl . '?action=profile'),
				'logout' => array('text' => 'logout', 'lang' => true, 'url' => $scripturl . '?action=logout;' . $context['session_var'] . '=' . $context['session_id']),
			);

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_sb_usercp');

			$_SESSION['logout_url'] = $this->getUrl();

			// What does the user want the time formatted as?
			$s = strpos($user_info['time_format'], '%S') === false ? '' : ':%S';
			if (strpos($user_info['time_format'], '%H') === false && strpos($user_info['time_format'], '%T') === false)
			{
				$h = strpos($user_info['time_format'], '%l') === false ? '%I' : '%l';
				$time_fmt = $h . ':%M' . $s . ' %p';
			}
			else
				$time_fmt = '%H:%M' . $s;

			echo '
					<strong class="largetext">', $txt['sb_hello'], ', ', $user_info['name'], '</strong><br />';

			if (!empty($user_info['avatar']['image']))
				echo '
					<div style="padding: 1ex">
						<a href="', $scripturl, '?action=profile">', $user_info['avatar']['image'], '</a>
					</div>';
			else
				echo '
					<br />';

			echo '
					<ul class="usercp bullet">
						<li>', $txt['total_posts'], ': ', $user_info['posts'], '</li>
						<li>', $txt['view'], ': <a href="', $scripturl, '?action=unread">', $txt['sb_user_posts'], '</a> | <a href="', $scripturl, '?action=unreadreplies">', $txt['sb_user_replies'], '</a></li>
						<li>', $txt['view'], ': <a href="', $scripturl, '?action=pm">', $txt['sb_inbox'], '</a> | <a href="', $scripturl, '?action=pm;f=sent">', $txt['sb_outbox'], '</a></li>
						<li><a href="', $scripturl, '?action=helpadmin;help=see_admin_ip" onclick="return reqWin(this.href);" class="help">', $user_info['ip'], '</a></li>
						<li>', timeformat(time(), '%a, ' . $time_fmt), '</li>
					</ul>
					', template_button_strip($context['sb_usercp'], ''), '';
		}
		// They're a guest? Show the guest info here instead, and a login box.
		else
		{
			$_SESSION['login_url'] = $this->getUrl();

			echo '
					', $txt['hello_guest'], ' <strong>', $txt['guest'], '</strong>.<br />
					', $txt['login_or_register'], '<br />
					<br />
					<form action="', $scripturl, '?action=login2" method="post">
						<table border="0" cellspacing="2" cellpadding="0" class="table">
							<tr>
								<td class="lefttext"><label for="user">', $txt['sb_login_user'], ':</label>&nbsp;</td>
								<td class="lefttext"><input type="text" name="user" id="user" size="10" /></td>
							</tr>
							<tr>
								<td class="lefttext"><label for="passwrd">', $txt['password'], ':</label>&nbsp;</td>
								<td class="lefttext"><input type="password" name="passwrd" id="passwrd" size="10" /></td>
							</tr>
							<tr>
								<td class="lefttext"><label for="cookielength">', $txt['sb_length'], '</label>&nbsp;</td>
								<td>
								<select name="cookielength" id="cookielength">
									<option value="60">', $txt['one_hour'], '</option>
									<option value="1440">', $txt['one_day'], '</option>
									<option value="10080">', $txt['one_week'], '</option>
									<option value="302400">', $txt['one_month'], '</option>
									<option value="-1" selected="selected">', $txt['forever'], '</option>
								</select>
								</td>
							</tr>
							<tr>
								<td class="righttext" colspan="2"><input type="submit" value="', $txt['login'], '" class="button_submit" /></td>
							</tr>
						</table>
					</form>
					', $txt['welcome_guest_activate'], '';
		}
	}
}
