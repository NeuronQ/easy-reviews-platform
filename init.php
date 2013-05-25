<?php
/*
 * Include utilities and initializes global variables.
 * (in a separate file because it should also be included in standalone pages)
 */

global $easyrp_root;
$easyrp_root = dirname(__FILE__);
require $easyrp_root . '/libs/wppu/wppu.php';
require $easyrp_root . '/EasyRP.php';
