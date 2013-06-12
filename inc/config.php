<?php

require_once $easyrp_root . '/inc/libs/phpu.php';

function easyrp_config($key, $content_type = 'GENERAL')
{
	global $easyrp_config, $easyrp_config_defaultified;

	if (!isset($easyrp_config_defaultified)) {
		$easyrp_config_defaultified = easyrp_configs_defaultify($easyrp_config);
	}

	$val =& $easyrp_config_defaultified[$content_type];
	$keys = explode('-', $key);
	while ($keys) {
		$k = array_shift($keys);
		if (isset($val[$k])) $val =& $val[$k];
		else return null;
	}

	return $val;
}

function easyrp_configs_defaultify($easyrp_config)
{
	foreach ($easyrp_config as $content_type => &$configs) {
		if ($content_type == 'GENERAL') continue;
		else $configs = array_merge_r($easyrp_config['GENERAL'], $configs);
	}

	return $easyrp_config;
}
