<?php
define('DP_LIBRARY_ROOT',          SYS::path('/user/bob/my-photos/'));
define('DP_LIBRARY_DROPBOX',       SYS::path('/user/bob/photos-to-import/'));

define('DP_LIBRARY_DROPBOX_BACKUP', '_Backup');
define('DP_LIBRARY_DROPBOX_SKIP',   '_Skip');

define('DP_LIBRARY_PHOTOS',        SYS::path(DP_LIBRARY_ROOT, 'library'));

define('DP_LIBRARY_LOG_ROOT',      SYS::path('/user/bob/my-photos/photo-import-logs'));
define('DP_LIBRARY_LOG_FILE',      SYS::path(DP_LIBRARY_LOG_ROOT, date('ymd-his') . '.log' ) );
define('DP_LIBRARY_LAST_LOG_FILE', SYS::path(DP_LIBRARY_LOG_ROOT, 'last-run.log' ) );

define('DP_LIBRARY_LOCK_FILE',     SYS::path(DP_LIBRARY_ROOT, '.dp-import-lock') );

define('MAX_IMPORT', 15000);

// This contains the list of camera id's to allow for import. You can set the override constant 
// below to ignore this
$DP_LIBRARY_CAMERAS = array(
	'1620707755'
);

define('DP_LIBRARY_OVERRIDE_CAMERA', false);
