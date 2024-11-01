<?php
$dbtablesprefix = $wpdb->prefix."zing_";
$dblocation = DB_HOST;
$dbname = DB_NAME;
$dbuser = DB_USER;
$dbpass = DB_PASSWORD;

include (dirname(__FILE__)."/includes/readvals.inc.php");        // get and post values
if (function_exists("qtrans_getLanguage")) {
	$lang=qtrans_getLanguage();
}
