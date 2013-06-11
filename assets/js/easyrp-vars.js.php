<?php
require dirname(__FILE__) . '/../../config.php';

header('Content-Type: application/javascript');

if (!isset($easyrp_config['GENERAL']['stars']['dir_url'])) {
	$easyrp_config['GENERAL']['stars']['dir_url'] = $_GET['stars_dir_url'];
	easyrp_configs_defaultify($easyrp_config);
}

echo 'easyrp_config = ' . json_encode($easyrp_config, JSON_PRETTY_PRINT);
