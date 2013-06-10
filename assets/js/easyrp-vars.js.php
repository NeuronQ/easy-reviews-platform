<?php
require dirname(__FILE__) . '/../../config.php';

header('Content-Type: application/javascript');

$stars_dir_url = isset($easyrp_config->stars_dir_url) ?
					$easyrp_config->stars_dir_url :
					$_GET['stars_dir_url'];

$stars_hints = "['" . implode("', '", $easyrp_config->stars_hints) . "']";

echo "easyrp_config = {
		stars_dir_url: '$stars_dir_url',
		stars_hints: $stars_hints,
		stars_no: $easyrp_config->stars_no
	}";
