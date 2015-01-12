#!/usr/bin/php
<?php
set_time_limit(0);
// ----- Setup our PHP.ini Settings
require_once(dirname(__FILE__) . '/func.php');
require_once(dirname(__FILE__) . '/SYS.class.php');

require_once(dirname(__FILE__) . '/config.php');

clearstatcache();
app_log( date('r') );

// Ensure that we're the only instance running
if ( file_exists( DP_LIBRARY_LOCK_FILE ) ) {
	app_log( 'Lock file exists, exiting.' );
	exit(0);
} else {
	if ( touch( DP_LIBRARY_LOCK_FILE ) ) {
		app_log( 'Created lock file: ' . DP_LIBRARY_LOCK_FILE );	
	} else {
		app_log( 'Failed to create lock file [' . DP_LIBRARY_LOCK_FILE . '], exiting.', true, true );
		exit( 1 );
	}
}

$token = '';

$start_time = time();

define('SLEEP_TIME', 2);

$countImport	= 0;
$countSkip	= 0;
$countError	= 0;

$imported 	= array();
$skipped	= array();
$errored	= array();
$errors		= array();


$path 		= SYS::path('/');
$rPath		= SYS::path(DP_LIBRARY_DROPBOX, $path);

//$filter 	= '/^(?!(\.|' . DP_LIBRARY_DROPBOX_BACKUP . '|' . DP_LIBRARY_DROPBOX_SKIP . '))/';
// This is a regex of folders to ignore
$filter 	= '/^(?!(\.|' . DP_LIBRARY_DROPBOX_BACKUP . '))/';
$filter         = null;

app_log( "Searching for images in {$rPath}" );

$folders	= SYS::listRecursiveDirectories($rPath, $filter);

$files = array();

$tmp = SYS::listRecursiveFiles($rPath, MAX_IMPORT, $filter, '/^(?!(\.)).*\.(jpg|thm)$/i');
if ($tmp) {
	foreach ($tmp AS $img) {
		$files[] = SYS::filePath($rPath, $img);
		
		if (count($files) > MAX_IMPORT) {
			break;
		}
	}
}

$countTotal 	= count($files);
$countFolders	= count($folders);
app_log( "Found $countTotal images in $countFolders folders" );

if ( $countTotal > 0 ) {
app_log('');

if (is_array($files) && count($files) > 0) {
	while (count($files) > 0) {
		$f = $files[0];
		
		app_log( $f );
		
		$src = SYS::filePath($f);
		
		if ( !file_exists($src) ) {
			app_log( "\tFile does not exist: $src [$f] skipping!" );
			array_shift($files);
			continue;
		}

		if ( ( fileatime($src) + 15 ) > time() ) {
			touch($src);
			app_log( "\tFile modified in the last 15 seconds, skipping!" );
			array_shift($files);
			continue;
		}
		
		$exif	= @exif_read_data($src);

		if ( !$exif ) {
			$countError++;
			$errors[] = 'Exif Read Error: ' . $src;
			app_log( "\tError reading exif: $src" );
			array_shift($files);
			continue;
		}

		// Test that we're importing just this camera's images
		if ( empty($exif['Camera']) || !in_array($exif['Camera'], $DP_LIBRARY_CAMERAS) ) {
			echo $files[0] . "\n";
			app_log( "\tUnknown camera id: {$exif['Camera']}" );
			
			if ( DP_LIBRARY_OVERRIDE_CAMERA !== true ) {
				print_r($DP_LIBRARY_CAMERAS);
				print_r($exif);
				if ( unlink( DP_LIBRARY_LOCK_FILE ) ) {
					app_log( 'Cleaned up lock file: ' . DP_LIBRARY_LOCK_FILE );
				}
				exit;
			}
			
			if ( !empty($exif['Camera']) ) {
				array_shift($files);
				continue;
			}
		}
		
		$dt	= explode(':', $exif['DateTimeOriginal']);
		$dt	= sprintf('%s-%s-%s:%s:%s', $dt[0], $dt[1], $dt[2], $dt[3], $dt[4]);
		$ds 	= strtotime($dt);
		
		if ($ds == '' || $ds == -1) {
			$countError++;
			$errors[] = 'Timestamp Error: ' . $src;
			app_log( "\tError finding timestamp: $f" );
			array_shift($files);
			continue;
		}
		$ext 	= strtolower(substr($src, strrpos($src, '.')));
		$year	= date('Y', $ds);
		$folder	= date('ymd', $ds);
		$file	= date('ymd-His', $ds) . $ext;
		
		$yPath = SYS::path(DP_LIBRARY_PHOTOS, $year);
		if (!file_exists($yPath . '/')) {
			app_log( "Creating year [$yPath]" );
			mkdir($yPath);
		}
		
		app_log( "\t{$file}" );
		
		$tPath	= SYS::path(DP_LIBRARY_PHOTOS, $token);
		if (!file_exists($tPath . '/')) {
			app_log( "Creating token [$tPath]" );
			mkdir($tPath);
		}
		
		$path	= SYS::path($tPath, $year, $folder);
		$dest	= SYS::filePath($path, $file);
		
		if (!file_exists($path . '/')) {
			app_log( "Creating [$path]" );
			mkdir($path);
		}
		
		$fs_src 	= filesize($src);
		$src_md5	= '';
		$i			= 1;
		$skip		= false;
		
		// Test if we have a counter
		$tmp	= SYS::filePath($path, sprintf('%s-%02d%s', substr($file, 0, strrpos($file, '.')), 1, $ext));
		if (file_exists($tmp)) {
			$dest = $tmp;
		}
		
		while (file_exists($dest)) {
			$i++;
			// Check our filesize
			/*
			$fs_dst 	= filesize($dest);
			if ($fs_src == $fs_dst) {
				app_log( "\tDestination exists, filesize match, skipping" );
				app_log( "\tDestination: {$dest}" );
				//flush(); ob_flush();
				$skip = true;
				break;
			}
			*/
			
			if ($src_md5 == '') {
				$src_md5 = md5(file_get_contents($src));
			}
			
			$dst_md5	= md5(file_get_contents($dest));
			
			if ($src_md5 == $dst_md5) {
				app_log( "\tDestination exists, MD5 match, skipping" );
				app_log( "\tSRC: {$src_md5}" );
				app_log( "\tDST: {$dst_md5}" );
				app_log( "\tDestination: {$dest}" );
				$skip = true;
				break;
			} else {
				app_log( "\tMD5 SRC: {$src_md5}" );
				app_log( "\tMD5 DST: {$dst_md5}" );
			}
			
			$path 	= dirname($dest);
			$tmp	= $file;
			$ext	= substr($tmp, strrpos($tmp, '.'));
			$tmp	= sprintf('%s-%02d%s', substr($tmp, 0, strrpos($tmp, '.')), $i, $ext);
			$dest	= SYS::filePath($path, $tmp);
			app_log( "\tSuggesting new filename: $dest" );
		}

		// Check for CR2 (RAW IMAGE)
		$cr2 = substr($src, 0, strrpos($src, '.'));
		if (file_exists($cr2 . '.CR2')) {
			$cr2 .= '.CR2';
		} elseif (file_exists($cr2 . '.cr2')) {
			$cr2 .= '.cr2';
		} else {
			$cr2 = false;
		}

		// Check for CRW (RAW IMAGES)
		$crw = substr($src, 0, strrpos($src, '.'));
		if (file_exists($crw . '.CRW')) {
			$crw .= '.CRW';
		} elseif (file_exists($crw . '.crw')) {
			$crw .= '.crw';
		} else {
			$crw = false;
		}
		
		// Check for XMP (Meta-data files)
		$xmp = substr($src, 0, strrpos($src, '.'));
		if (file_exists($xmp . '.XMP')) {
			$xmp .= '.XMP';
		} elseif (file_exists($xmp . '.xmp')) {
			$xmp .= '.xmp';
		} else {
			$xmp = false;
		}
		
		if ($skip) {
			$countSkip++;
			$skipped[] = basename($src) . ' => ' . $year . '/' . $folder . '/' . $file;
			
			/*
			$skip = SYS::path(dirname($src), DP_LIBRARY_DROPBOX_SKIP);
			if (!file_exists($skip)) {
				app_log( "\t>> Created: $skip" );
				mkdir(SYS::path($skip));
			}
			$skip = SYS::path($skip, basename($src));
			if (!file_exists($skip)) {
				rename($src, $skip);
				app_log( "\t>> Move to $skip" );
			} else {
				app_log( "\t>> Image exists $skip" );
			}
			*/

			if ( unlink($src) ) {
				app_log( "\t>> Removed $src, image exists" );
			}

			if ( $cr2 !== false ) {
				if ( unlink($cr2) ) {
					app_log( "\t>> Removed $cr2, image exists" );
				}
			}

			if ( $crw !== false ) {
				if ( unlink($crw) ) {
					app_log( "\t>> Removed $crw, image exists" );
				}
			}

			if ( $xmp !== false ) {
				if ( unlink($xmp) ) {
					app_log( "\t>> Removed $xmp" );
				}
			}

			
			/*
			if ($cr2 !== false) {
				$skip =  SYS::path( dirname($src), DP_LIBRARY_DROPBOX_SKIP, substr(basename($src), 0, strrpos(basename($src), '.')) . substr($cr2, strrpos($cr2, '.') ) );
				rename($cr2, $skip);
				app_log( "\t>> Move to $skip" );
			}
			if ($crw !== false) {
				$skip = SYS::path( dirname($src), DP_LIBRARY_DROPBOX_SKIP, substr(basename($src), 0, strrpos(basename($src), '.')) . substr($crw, strrpos($crw, '.')));
				rename($crw, $skip);
				app_log( "\t>> Move to $skip" );
			}
			*/
			
			array_shift($files);
			continue;
		}
		
		// If
		if ($i == 2) {
			$shiftDir 	= dirname($dest);
			$shiftFile	= basename($dest);
			$shiftBase	= substr($shiftFile, 0, strrpos($shiftFile, '-'));
			$shiftExt	= substr($shiftFile, strrpos($shiftFile, '.'));
			$shiftFrom 	= $shiftBase . $shiftExt;
			$shiftTo 	= $shiftBase . '-01' . substr($shiftFile, strrpos($shiftFile, '.'));
			if (!file_exists(SYS::filePath($shiftDir, $shiftTo))) {
				app_log( "\t>> Shift from $shiftFrom to $shiftTo" );
				rename(SYS::filePath($shiftDir, $shiftFrom), SYS::filePath($shiftDir, $shiftTo));
			}
			
		}

		$success = true;
		
		app_log( "\t>> Copy to $dest" );
		if ( !copy($src, $dest) ) {
			die("Unable to copy $src to $dest\n");
		}
		$countImport++;
		$imported[] = basename($src) . ' => ' . $year . '/' . $folder . '/' . $file;

		if ($cr2 !== false) {
			$destCr2 = substr($dest, 0, strrpos($dest, '.'));
			app_log( "\t>> Copy to $destCr2.cr2" );
			$success = $success && copy($cr2, $destCr2 . '.cr2');
		}

		if ($crw !== false) {
			$destCrw = substr($dest, 0, strrpos($dest, '.'));
			app_log( "\t>> Copy to $destCrw.crw" );
			$success = $success && copy($crw, $destCrw . '.crw');
		}

		if ( $success === true ) {
			if ( unlink($src) ) {
				app_log( "\t>> Removed $src" );
			}
			if ($cr2 !== false && unlink($cr2) ) {
				app_log( "\t>> Removed $cr2" );
			}
			if ($crw !== false && unlink($crw) ) {
				app_log( "\t>> Removed $crw" );
			}

			if ( $xmp !== false && unlink($xmp) ) {
				app_log( "\t>> Removed $xmp" );
			}

		}

		/*
		$backup = SYS::path(dirname($src), DP_LIBRARY_DROPBOX_BACKUP);
		if (!file_exists($backup)) {
			app_log( "\t>> Created $backup" );
			mkdir(SYS::path($backup));
		}

		$backup = SYS::filePath($backup, basename($f));
		if (!file_exists($backup)) {
			app_log( "\t>> Move to $backup" );
			rename($src, $backup);
			if ($cr2 !== false) {
				$backup = substr($backup, 0, strrpos($backup, '.')) . substr($cr2, strrpos($cr2, '.'));
				rename($cr2, $backup);
				app_log( "\t>> Move to $backup" );
			}
			if ($crw !== false) {
				$backup = substr($backup, 0, strrpos($backup, '.')) . substr($crw, strrpos($crw, '.'));
				rename($crw, $backup);
				app_log( "\t>> Move to $backup" );
			}
		}
		*/
		
		array_shift($files);
		//sleep(SLEEP_TIME);
	}	
}

$end_time = time();
app_log('');

if (count($errors) > 0) {
	app_log( "Errors:\n\t", false );
	app_log( implode("\n\t", $errors) );
	app_log( '' );
}

$t = ($end_time - $start_time);
app_log( "Elapsed time: " . floor($t / 60) . ':' . sprintf('%02d', ($t % 60)) . '  Images Per Minute: ' . round(($countTotal / ($t/60)), 2), true, true );
app_log( "COUNTS: Total: $countTotal  Imported: $countImport  Skipped: $countSkip  Error: $countError", true, true );
//app_log( "Imported:\n\t" . join($imported, "\n\t") );
}

app_log("");
app_log("Pruning empty directories");

$folders = SYS::listRecursiveDirectories($rPath);
$folders = array_reverse($folders);
if ( $folders ) {
	foreach ( $folders AS $f ) {
		$items = SYS::listDirectoryEntries( SYS::path($rPath, $f) );
		if ( count($items['file']) == 0 && count($items['dir']) == 0 ) {
			if ( rmdir( SYS::path($rPath, $f) ) ) {
				app_log(">> Removed empty folder $f");
			}
		}
	}
}

if ( unlink( DP_LIBRARY_LOCK_FILE ) ) {
	app_log( 'Cleaned up lock file: ' . DP_LIBRARY_LOCK_FILE );
} else {
	app_log( 'Failed to clean up lock file: ' . DP_LIBRARY_LOCK_FILE );
}

unlink( DP_LIBRARY_LAST_LOG_FILE );
copy( DP_LIBRARY_LOG_FILE, DP_LIBRARY_LAST_LOG_FILE );
if ( $countTotal == 0 ) {
	unlink( DP_LIBRARY_LOG_FILE );
}
