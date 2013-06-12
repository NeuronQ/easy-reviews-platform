<?php

require $easyrp_root . '/inc/config.php';

global $easyrp_config;

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
