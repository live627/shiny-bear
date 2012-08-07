<?php
/**
 * This script prsbares the database for all the tables and other database changes Envision Portal requires.
 *
 * NOTE: This script is meant to run using the <samp><database></database></samp> elements of the package-info.xml file. This is so admins have the choice to uninstall any database data installed with the mod. Also, since using the <samp><database></samp> elements automatically calls on db_extend('packages'), we will only be calling that if we are running this script standalone.
 *
 * @package installer
 * @since 1.0
 */

/**
 * Before attempting to execute, this file attempts to load SSI.php to enable access to the database functions.
*/
// If SSI.php is in the same place as this file, and SMF isn't defined...
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');

// Hmm... no SSI.php and no SMF?
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

if ((SMF == 'SSI') && !$user_info['is_admin'])
	die('Admin privileges required.');

versionCheck();
populateDB();

// !!! SMF doesn't believe in the update setting for create table, so we'll use our own instead.
function sb_db_create_table($name, $columns, $indexes, $parameters)
{
	global $smcFunc, $db_prefix;

	// Make sure the name has the proper...what's that thing called? (SMF's way makes an unsafe assumption imo)
	$name = str_replace('{db_prefix}', $db_prefix, $name);
	$table_name = ((substr($name, 0, 1) == '`') ? $name : ('`' . $name));
	$table_name = ((substr($name, -1) == '`') ? $table_name : ($table_name . '`'));

	// If the table doesn't exist, create it. We're basically done here after that.
	if (!in_array(str_replace('{db_prefix}', $db_prefix, $name), $smcFunc['db_list_tables']()))
		return $smcFunc['db_create_table']($name, $columns, $indexes, $parameters, 'update');

	$query = $smcFunc['db_query']('', '
		SHOW COLUMNS
		FROM {raw:table_name}',
		array(
			'table_name' => $table_name,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($query))
		foreach ($columns as $key => $column)
			if ($row['Field'] == $column['name'])
			{
				$type = (isset($column['size']) ? ($column['type'] . '(' . $column['size'] . ')') : $column['type']);
				if ($row['Type'] != $type)
					$smcFunc['db_change_column']($table_name, $column['name'], $column);
				unset($columns[$key]);
				break;
			}

	$smcFunc['db_free_result']($query);

	if (!empty($columns))
		foreach ($columns as $column)
			if (!empty($column))
				$smcFunc['db_add_column']($table_name, $column);

	return true;
}

// !!! Installs Tables for SMF 2.0.x with default values!
function populateDB()
{
	global $smcFunc, $modSettings;

	$sb_tables = array(
		array(
			'name' => 'layouts',
			'columns' => array(
				array(
					'name' => 'id_layout',
					'type' => 'mediumint',
					'size' => 8,
					'unsigned' => true,
					'auto' => true,
				),
				array(
					'name' => 'id_member',
					'type' => 'mediumint',
					'size' => 8,
					'unsigned' => true,
				),
				array(
					'name' => 'name',
					'type' => 'varchar',
					'size' => 40,
				),
				array(
					'name' => 'approved',
					'type' => 'tinyint',
					'size' => 1,
					'unsigned' => true,
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_layout')
				),
				array(
					'columns' => array('id_member')
				)
			),
			'default' => array(
				'columns' => array(
					'id_layout' => 'int',
					'id_member' => 'int',
				),
				'values' => array(
					array(1, 0),
				),
				'keys' => array('id_layout', 'id_member')
			)
		),
		array(
			'name' => 'layout_actions',
			'columns' => array(
				array(
					'name' => 'id_layout',
					'type' => 'mediumint',
					'size' => 8,
					'unsigned' => true,
					'auto' => true,
				),
				array(
					'name' => 'action',
					'type' => 'varchar',
					'size' => 40,
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_layout, action(40)')
				)
			),
			'default' => array(
				'columns' => array(
					'id_layout' => 'int',
					'action' => 'string',
				),
				'values' => array(
					array(1, '[home]'),
				),
				'keys' => array('id_layout')
			)
		),
		array(
			'name' => 'layout_positions',
			'columns' => array(
				array(
					'name' => 'id_layout_position',
					'type' => 'int',
					'size' => 10,
					'unsigned' => true,
					'auto' => true,
				),
				array(
					'name' => 'id_layout',
					'type' => 'mediumint',
					'size' => 8,
					'unsigned' => true,
				),
				array(
					'name' => 'x_pos',
					'type' => 'tinyint',
					'size' => 3,
				),
				array(
					'name' => 'y_pos',
					'type' => 'tinyint',
					'size' => 3,
				),
				array(
					'name' => 'width',
					'type' => 'tinyint',
					'size' => 3,
				),
				array(
					'name' => 'status',
					'type' => 'enum(\'active\',\'inactive\')',
					'default' => 'active',
				),
				array(
					'name' => 'is_smf',
					'type' => 'tinyint',
					'size' => 1,
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_layout_position')
				),
				array(
					'columns' => array('id_layout')
				),
			),
			'default' => array(
				'columns' => array(
					'id_layout_position' => 'int',
					'id_layout' => 'int',
					'x_pos' => 'int',
					'y_pos' => 'int',
					'width' => 'int',
					'status' => 'string',
				),
				'values' => array(
					array(1, 1, 0, 0, 100, 'active'),
					array(2, 1, 1, 0, 19.5, 'active'),
					array(3, 1, 1, 1, 60, 'active'),
					array(4, 1, 1, 2, 19.5, 'active'),
					array(5, 1, 2, 0, 100, 'inactive'),
				),
				'keys' => array('id_layout_position', 'id_layout')
			)
		),
		array(
			'name' => 'modules',
			'columns' => array(
				array(
					'name' => 'id_module',
					'type' => 'smallint',
					'size' => 5,
					'unsigned' => true,
					'auto' => true,
				),
				array(
					'name' => 'type',
					'type' => 'varchar',
					'size' => 80,
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_module')
				),
				array(
					'type' => 'unique',
					'columns' => array('type')
				)
			),
			'default' => array(
				'columns' => array(
					'id_module' => 'int',
					'type' => 'string',
				),
				'values' => array(
					array(1, 'announce'),
					array(2, 'usercp'),
					array(3, 'stats'),
					array(4, 'online'),
					array(5, 'news'),
					array(6, 'recent'),
					array(7, 'search'),
					array(8, 'calendar'),
					array(9, 'poll'),
				),
				'keys' => array('id_module', 'type')
			)
		),
		array(
			'name' => 'module_positions',
			'columns' => array(
				array(
					'name' => 'id_position',
					'type' => 'int',
					'size' => 10,
					'unsigned' => true,
					'auto' => true,
				),
				array(
					'name' => 'id_layout_position',
					'type' => 'int',
					'size' => 10,
					'unsigned' => true,
				),
				array(
					'name' => 'id_module',
					'type' => 'tinyint',
					'size' => 3,
					'unsigned' => true,
				),
				array(
					'name' => 'position',
					'type' => 'tinyint',
					'size' => 2,
				)
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('id_position')
				),
				array(
					'columns' => array('id_layout_position')
				),
				array(
					'columns' => array('id_module')
				),
			),
			'default' => array(
				'columns' => array(
					'id_position' => 'int',
					'id_layout_position' => 'int',
					'id_module' => 'int',
					'position' => 'int',
				),
				'values' => array(
					// top
					array(1, 1, 1, 0),
					// left
					array(2, 2, 2, 0),
					array(3, 2, 3, 1),
					array(4, 2, 4, 2),
					//middle
					array(5, 3, 5, 0),
					array(6, 3, 6, 1),
					// right
					array(7, 4, 7, 0),
					array(8, 4, 8, 1),
					array(9, 4, 9, 2),
				),
				'keys' => array('id_position', 'id_layout_position', 'id_module')
			)
		),
		array(
			'name' => 'module_field_data',
			'columns' => array(
				array(
					'name' => 'name',
					'type' => 'varchar',
					'size' => 80,
					'unsigned' => true,
				),
				array(
					'name' => 'id_module_position',
					'type' => 'int',
					'size' => 10,
					'unsigned' => true,
				),
				array(
					'name' => 'value',
					'type' => 'text',
				),
			),
			'indexes' => array(
				array(
					'type' => 'primary',
					'columns' => array('name', 'id_module_position')
				),
			),
		),
	);

	db_extend('packages');

	foreach ($sb_tables as $table)
	{
		sb_db_create_table('{db_prefix}sb_' . $table['name'], $table['columns'], $table['indexes'], array(), 'update');

		if (isset($table['default']))
			$smcFunc['db_insert']('ignore', '{db_prefix}sb_' . $table['name'], $table['default']['columns'], $table['default']['values'], $table['default']['keys']);
	}

	// Makes sense to let everyone view a portal, no? But don't modify the permissions if the admin has already set them.
	$request = $smcFunc['db_query']('', '
		SELECT id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:permission}',
		array(
			'permission' => 'sb_view',
		)
	);

	$num = $smcFunc['db_num_rows']($request);
	$smcFunc['db_free_result']($request);

	if (empty($num))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group NOT IN ({array_int:exclude_groups})
			' . (empty($modSettings['permission_enable_postgroups']) ? '
				AND min_posts = {int:min_posts}' : ''),
			array(
				'exclude_groups' => array(1, 3),
				'min_posts' => -1,
			)
		);

		$groups = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$groups[] = array($row['id_group'], 'sb_view', empty($modSettings['permission_enable_deny']) ? 1 : -1);

		$groups[] = array(-1, 'sb_view', !empty($modSettings['permission_enable_deny']) ? 1 : -1);
		$groups[] = array(0, 'sb_view', !empty($modSettings['permission_enable_deny']) ? 1 : -1);

		if (!empty($groups))
			$smcFunc['db_insert']('ignore',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$groups,
				array('id_group', 'permission')
			);
	}
}

// !!! Requires PHP/5.3.0+!
function versionCheck()
{
	if (version_compare(PHP_VERSION, '5.3.0', '<'))
		fatal_error('This mod needs PHP 5.3 or greater. You will not be able to install/use this mod, contact your host and ask for a php upgrade.');
}

?>
