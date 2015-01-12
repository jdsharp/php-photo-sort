<?php /* $Id$ */

/**
 * SharpWebKit System class
 */

/**
 * This is intended to be a completely static class...
 */

class SYS
{
	function init()
	{
		// + Throw in some code here to make this win/linux compatible
		define('SYS_DS', '/');
	}
	
	function pathNormalize()
	{
		$sep = array('/' => '\\', '\\' => '/');
		
		$args = func_get_args();
		
		$tmp = array_flatten($args);
		
		$path = implode(SYS_DS, $tmp);
		$path = str_replace($sep[SYS_DS], SYS_DS, $path);
		// Do the sub string to exclude './the/path' from getting converted'
		$path = preg_replace('|([^\.]{1})\.(' . SYS_DS . '){1}|', '${1}', $path);
		$path = preg_replace('|[' . SYS_DS . ']{2,}|', SYS_DS, $path);
		
		return $path;
	}

	function pathSafe()
	{
		$path = SYS::pathNormalize(func_get_args());
		$path = str_replace('..' . SYS_DS, '', $path);
		
		if (substr($path, -1) == SYS_DS) {
			$path = substr($path, 0, -1);
		}
		
		return $path;
	}
    
	function path()
	{
		$args = func_get_args();
		$path = SYS::pathNormalize($args);
		
		do {
			$p = $path;
			$path = preg_replace('|([^' . SYS_DS . ']+' . SYS_DS . '\.\.' . SYS_DS . '){1}|', '', $path, 1);
		} while ($p != $path);
		
		if (substr($path, -1) == SYS_DS) {
			$path = substr($path, 0, -1);
		}
		
		return $path;
	}
	
	function filePath()
	{
		$args = func_get_args();
		$path = SYS::path($args);
		return $path;
	}
	
	function pathSeperator()
	{
		return SYS_DS;
	}
	
	function listDirectoryEntries($path, $filter = null)
	{
		
		$entries = array('dir'  => array(), 'file' => array());
		$path = SYS::pathNormalize($path);
		if (($dh = opendir($path)) !== false) {
			while (($e = readdir($dh)) !== false) {
				if ($e == '.' || $e == '..') {
					continue;
				}
				if ($filter && !preg_match($filter, $e)) {
					continue;
				}
				
				if (is_dir($path . '/' . $e)) {
					$entries['dir'][$e] = $e;
				} elseif (is_file($path . '/' . $e)) {
					$entries['file'][$e] = $e;
				}
			}
			closedir($dh);
			
			ksort($entries['dir']);
			ksort($entries['file']);
			
			return $entries;
		}
		
		return false;
	}
	
	function listFiles($path, $filter = null)
	{
		$e = SYS::listDirectoryEntries($path, $filter);
		if ($e) {
			return $e['file'];
		}
		
		return false;
	}
	
	function listRecursiveFiles($path, $max = 0, $dFilter = null, $filter = null)
	{
		$ret = array();
		
		$dirs = SYS::listRecursiveDirectories($path, $dFilter);
		array_unshift($dirs, './');
		
		foreach ($dirs AS $d) {
			$e = SYS::listFiles($path . '/' . $d, $filter);			
			if ($e) {
				foreach ($e AS $f) {
					$ret[] = SYS::filePath($d, $f);
				}
			}
			
			if ($max > 0 && count($ret) > $max) {
				break;
			}
		}
		
		return $ret;
	}
	
	function listDirectories($path, $filter = null)
	{
		$e = SYS::listDirectoryEntries($path, $filter);
		if ($e) {
			return $e['dir'];
		}
		
		return false;
	}
	
	function listRecursiveDirectories($path, $filter = null)
	{
		$ret	= array();
		$stack 	= array($path);
		while (($d = array_shift($stack)) != false) {
			$e = SYS::listDirectoryEntries($d, $filter);
			if ($e) {
				foreach ($e['dir'] AS $v) {
					$p = $d . '/' . $v;
					$stack[] 	= $p;
					$ret[] 		= str_replace($path, '', $p);
				}
			}
			
			if (count($ret) > 10000) {
				break;
			}
		}
		
		return $ret;
	}
}

SYS::init();
