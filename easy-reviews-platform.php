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

// DEBUG
$old_error_reporting_level = error_reporting(-1);
$old_assert_active         = assert_options(ASSERT_ACTIVE,   true);
$old_assert_bail           = assert_options(ASSERT_BAIL,     true);
$old_assert_warning        = assert_options(ASSERT_WARNING,  false);

/*
 * Include main plugin class and utilities and initializes global variables.
 * (in a separate file because it could also be included in standalone pages,
 * like for AJAX functionality)
 */
require dirname(__FILE__). '/init.php';

global $EasyRP;
$EasyRP = new EasyRP();

error_reporting($old_error_reporting_level); // DEBUG
assert_options(ASSERT_ACTIVE,   $old_assert_active);
assert_options(ASSERT_BAIL,     $old_assert_bail);
assert_options(ASSERT_WARNING,  $old_assert_warning);
