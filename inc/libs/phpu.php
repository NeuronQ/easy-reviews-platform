<?php

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
