<?php

/**
 * ShinyBear.template
 *
 * The purpose of this file is to display the portal.
 * @package Shiny Bear mod
 * @version 1.0 Alpha
 * @author John Rayes <live627@gmail.com>
 * @copyright Copyright (c) 2012, John Rayes
 * @license http://www.mozilla.org/MPL/MPL-1.1.html
 */

/*
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file excsbt in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is http://livemods.net code.
 *
 * The Initial Developer of the Original Code is
 * John Rayes.
 * Portions created by the Initial Developer are Copyright (c) 2012
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *
 */

/**
 * Template for displaying everything above the portal. In this case, the basic rendering of the layout is done here. Modules that go after SMF are held in a buffer and saved for later.
 *
 * @since 1.0
 */
function template_portal_above()
{
	global $context, $txt, $modSettings, $settings, $user_info;

	if (!empty($context['sb_cols']))
	{
		$sb_module_display_style = !empty($modSettings['sb_module_display_style']) ? $modSettings['sb_module_display_style'] : 0;

		foreach ($context['sb_cols'] as $row_id => $row_data)
			foreach ($row_data as $column_id => $column_data)
			{
				echo '
			<div class="sb_col" style="', $column_data['width'], '%;">';

				if ($column_data['is_smf'])
				{
					ob_start();
					$buffer = true;
				}

				if (!empty($column_data['modules']))
					template_module_column($sb_module_display_style, $column_data['modules']);

					echo '
			</div>';
			}
	}

	$context['sb_buffer'] = !empty($buffer) ? ob_get_clean() : '';
}

// This must be here to maintain balance!  DO NOT REMOVE!
function template_portal()
{
}

/**
 * Outputs everything in the buffer started in template_portal_above() and destroys it.
 *
 * @since 1.0
 */
function template_portal_below()
{
	global $context;

	// Everything trapped by the buffer gets written here. It's the best and easiest way that I know of...
	echo $context['sb_buffer'];
}

/**
 * Sets up the column if the display style is set to Modular and calls the apropriate template for this module or cloned module (clone).
 *
 * @since 1.0
 */
function template_module_column($style = 0, $column = array())
{
	global $context, $settings, $modSettings;

	// Modular Style
	if (!empty($style))
		echo '
			<div class="roundframe">';

	$i = 0;
	foreach ($column as $m)
	{
		call_user_func_array('sb_template_' . $m['module_template'], array($m, $style, $i));
		$i++;
	}

	// Modular Style
	if (!empty($style))
		echo '
			</div>';
}

?>
