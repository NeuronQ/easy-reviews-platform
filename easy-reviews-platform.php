<?php
/*
Plugin Name: Easy Reviews Platform
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates 
Description: The easiest way to set up a professional reviews site.
Version: 0.0.0
Author: Neuron Q
Author URI: http://URI_Of_The_Plugin_Author 
License: BSD
.
Any other notes about the plugin go here.
.
*/

$old_error_reporting_level = error_reporting(-1); // DEBUG

/*
 * Include main plugin class and utilities and initializes global variables.
 * (in a separate file because it could also be included in standalone pages,
 * like for AJAX functionality)
 */
require dirname(__FILE__). '/init.php';

global $EasyRP;
$EasyRP = new EasyRP();

error_reporting($old_error_reporting_level); // DEBUG
