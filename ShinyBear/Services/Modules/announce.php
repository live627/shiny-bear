<?php
// Version: 1.0: announce.php
namespace ShinyBear\Services\Modules;

if (!defined('SMF')) {
	die('Hacking attempt...');
}

/**
 * @package ShinyBear
 * @since 1.0
 */
class announce extends Module
{
	public function output()
	{
		global $context;

		// Grab the parameters, if they exist.
		if (is_array($this->fields))
		{
			$msg = html_entity_decode($this->fields['msg'], ENT_QUOTES);

			// Does this exist?
			if (!empty($msg))
				echo parse_bbc($msg);
			// No? Error!
			else
				echo $this->error();
		}
		// I guess $this->fields isn't an array....shame.
		else
			echo $this->error();
	}
}
