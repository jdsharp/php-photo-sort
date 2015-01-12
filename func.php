<?php

function app_log($msg, $nl = true, $quiet = false) {
	echo $msg . ( $nl === true ? "\n" : '' );
	flush();
	file_put_contents(DP_LIBRARY_LOG_FILE, $msg . ( $nl === true ? "\n" : '' ), FILE_APPEND);
}

/**
 * Removes unspecified keys in the array
 * 
 * @author			Jonathan Sharp <jonathan@sharpmedia.net>
 * @returns 		array
 */
function array_prune($keys, $array)
{
	
}

function object_updateArray(&$obj, $ary)
{
	if (is_array($ary)) {
		foreach ($ary AS $k => $v) {
			$obj->{$k} = $v;
		}
		
		return true;
	}
	
	return false;
}

function array_objectify($array)
{
	$obj = new stdClass();
	foreach ($array AS $k => $v) {
		$obj->{$k} = $v;
	}
	
	return $obj;
}

/**
 * Flattens a multimentional array.
 *
 * Takes a multi-dimentional array as input and returns a flattened
 * array as output. Implemented using a non-recursive algorithm.
 * Example:
 * <code>
 * $in = array('John', 'Jim', array('Jane', 'Jasmine'), 'Jake');
 * $out = array_flatten($in);
 * // $out = array('John', 'Jim', 'Jane', 'Jasmine', 'Jake');
 * </code>
 *
 * @author        Jonathan Sharp <jonathan@sharpmedia.net>
 * @var            array
 * @returns        array
 */
function array_flatten($array)
{
	while (is_array($array) && (count($array) > 0)) {
	//($v = array_shift($array)) !== null) {
		$v = array_shift($array);
		if (is_array($v)) {
			$array = array_merge($v, $array);
		} else {
			$tmp[] = $v;
		}
	}
	
	return $tmp;
}

if (!function_exists('array_combine')) {
	function array_combine($keys, $values)
	{
		if ((is_array($keys) && is_array($values)) && (count($keys) == count($values))) {
			$ary = array();
			while ((($k = array_shift($keys)) && ($v = array_shift($values))) != false) {
				$ary[$k] = $v;
			}
			return $ary;
		}
		return false;
	}
}
