<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
interface iModule
{
	public function __construct($field);

	function output();
}

abstract class Module implements iModule
{
	protected $fields;
	protected $err = false;

	/*
	 * Constructs the module.
	 *
	 * @param array $field
	 * @access public
	 * @return void
	 */
	public function __construct($fields = null)
	{
		$this->fields = $fields;
	}

	/*
	 * Gets the error generated by the validation method.
	 *
	 * @access public
	 * @return mixed The error string or false for no error.
	 */
	function getError()
	{
		return $this->err;
	}

	function error($type = 'error', $error_type = 'general', $log_error = false, $echo = true)
	{
		global $txt;

		// All possible pre-defined types.
		$valid_types = array(
			'mod_not_installed' => $type == 'mod_not_installed' ? 1 : 0,
			'not_allowed' => $type == 'not_allowed' ? 1 : 0,
			'no_language' => $type == 'no_language' ? 1 : 0,
			'query_error' => $type == 'query_error' ? 1 : 0,
			'empty' => $type == 'empty' ? 1 : 0,
			'error' => $type == 'error' ? 1 : 0,
		);

		//sb_call_hook('echo $this->error', array(&$type));

		$error_string = !empty($valid_types[$type]) ? $txt['sb_module_' . $type] : $type;
		$error_html = $error_type == 'critical' ? array('<p class="error">', '</p>') : array('', '');

		// Don't need this anymore!
		unset($valid_types);

		// Should it be logged?
		if ($log_error)
			log_error($error_string, $error_type);

		$this->err = implode($error_string, $error_html);

		return $this->err;
	}
}