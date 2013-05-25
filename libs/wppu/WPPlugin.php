<?php

// require dirname(__FILE__) . '/WPPlugin-traits.php';

/**
* WPPlugin
*
* Use only one instance of it (pseudo-singleton), to keep your sanity.  
*/
abstract class WPPlugin
{
	const name = 'WPPlugin';

	const prefix = 'wpplugin_';
	const text_domain = 'wpplugin';

	public $path;
	public $logfile_name;


	public function __construct()
	{
		global $table_prefix;

		$this->table_prefix = $table_prefix . static::prefix;
	}



	// controller action
	/*
	public function action()
	{
		// only allow invoking delete action via POST request
		if ($_POST['action'] == 'delete') {
			return 'delete';
		} else {
			if ($_GET['action'] != 'delete') return $_GET['action'];
			else return '';
		}
	}
	*/



	// admin functions

	public function add_admin_menu_separator($position)
	{
		global $menu;

		$index = 0;
		foreach ($menu as $offset => $section) {
			if (substr($section[2], 0, 9) == 'separator')
				$index++;
			if ($offset >= $position) {
				$menu[$position] = array('', 'read', "separator{$index}", '', 'wp-menu-separator');
				break;
			}
		}
		ksort($menu);
	}

	public function admin_redirect_to_action($action)
	{
		$this->js_redirect(preg_replace(
			'/([\?&]action=)\w*(&|$)/',
			'${1}' . delete . '${2}',
			$_SERVER['REQUEST_URI']
		));
	}

	public function js_redirect($url)
	{
		echo '<script>window.location.href ="' . $url .'"</script>';
	}



	// template loading

	public function frontend_template_path($path)
	{
		$theme_template_path = get_template_directory() . '/easyrp/templates/' . $path;
		if (file_exists($theme_template_path)) {
			return $theme_template_path;
		} else {
			return $this->path . '/templates/frontend/' . $path;
		}
	}



	// logging functions

	public function log($msg)
	{
		if (empty($this->logfile_name)) return;

		if (!isset($this->logfile)) {
			$this->logfile = fopen($this->logfile_name, 'a') or
				die ("[" . static::name . " plugin] Unable to open logfile.");
		}
		fwrite($this->logfile, "\r\n[" . date("H:i:s d-m-y") . '] ' . $msg);
	}


	public function log_output($code, $context = null)
	{
		if ($context === null) extract($GLOBALS);
		else {
			global $wpdb;
			extract($context);
		}
		ob_start();
			eval($code . ';');
		$this->log(ob_get_clean());
	}
}
