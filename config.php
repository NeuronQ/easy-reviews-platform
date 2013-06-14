<?php

global $easyrp_root, $easyrp_config;

require $easyrp_root . '/inc/config.php';

$easyrp_config = array(
	'GENERAL' => array(
		'stars' => array(
			'no' => 5,
			'hints' => array(1, 2, 3, 4, 5),
		),
	),
	'easyrp_review' => array(
		'stars' => array(
			'no' => 6,
		),
	),
	'post' => array(
	),
);

$easyrp_config_defaultified = easyrp_configs_defaultify($easyrp_config);
