<?php

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

easyrp_configs_defaultify($easyrp_config);

function easyrp_configs_defaultify(&$easyrp_config)
{
	foreach ($easyrp_config as $content_type => &$configs) {
		if ($content_type == 'GENERAL') continue;
		else $configs = array_merge_r($easyrp_config['GENERAL'], $configs);
	}
}

function array_merge_r($array1, $array2)
{
	foreach ($array2 as $k => $v) {
		if (is_string($k) && isset($array1[$k]) && is_array($v) && is_array($array1[$k])) {
			$array1[$k] = array_merge_r($array1[$k], $v);
		} else {
			$array1[$k] = $v;
		}
	}
	
	return $array1;
}
