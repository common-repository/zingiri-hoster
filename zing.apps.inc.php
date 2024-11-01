<?php
// Pre-2.6 compatibility for wp-content folder location
if (!defined("WP_CONTENT_URL")) {
	define("WP_CONTENT_URL", get_option("siteurl") . "/wp-content");
}
if (!defined("WP_CONTENT_DIR")) {
	define("WP_CONTENT_DIR", ABSPATH . "wp-content");
}

if (!defined("ZING_APPS_PLAYER_PLUGIN")) {
	$zing_apps_player_plugin=str_replace(WP_CONTENT_DIR."/plugins/","",dirname(__FILE__));;
	define("ZING_APPS_PLAYER_PLUGIN", $zing_apps_player_plugin);
}

if (!defined("ZING_APPS_PLAYER")) {
	define("ZING_APPS_PLAYER", true);
}

if (!defined("ZING_APPS_PLAYER_URL")) {
	define("ZING_APPS_PLAYER_URL", WP_CONTENT_URL . "/plugins/".ZING_APPS_PLAYER_PLUGIN."/");
}
if (!defined("ZING_APPS_PLAYER_DIR")) {
	define("ZING_APPS_PLAYER_DIR", WP_CONTENT_DIR . "/plugins/".ZING_APPS_PLAYER_PLUGIN."/");
}

add_action("init","zing_apps_player_init");
add_filter('the_content', 'zing_apps_player_content', 11, 3);
add_action('wp_head','zing_apps_player_header');
add_action('admin_head','zing_apps_player_header');

/**
 * Activation of apps player: creation of database tables & set up of pages
 * @return unknown_type
 */

function zing_apps_player_activate() {
	$zing_version=get_option("zing_apps_player_version");
	if (!$zing_version)
	{
		add_option("zing_apps_player_version",ZING_VERSION);
	}
	else
	{
		update_option("zing_apps_player_version",ZING_VERSION);
	}
}

/**
 * Deactivation of apps player: removal of database tables
 * @return unknown_type
 */
function zing_apps_player_deactivate() {
	global $wpdb;
	zing_apps_player_uninstall();
}

/**
 * Uninstallation of apps player
 * @return void
 */
function zing_apps_player_uninstall() {
	delete_option("zing_apps_player_version",ZING_VERSION);
}

/**
 * Page content filter
 * @param $content
 * @return unknown_type
 */
function zing_apps_player_content($content) {

	global $post;
	global $dbtablesprefix;

	$cf=get_post_custom();

	if (!isset($_GET['zfaces']) && ($post->ID == get_option("zing_apps_player_page"))) {
		$zfaces="summary";
	} elseif (isset($_GET['zfaces'])) {
		$zfaces=$_GET['zfaces'];
	} elseif (isset($cf['zfaces'])) {
		$zfaces=$cf['zfaces'][0];
	} else {
		return $content;
	}

	switch ($zfaces)
	{
		case "form":
		case "list":
			$to_include="scripts/".$zfaces.".php";
			$http=zing_http("zap",$to_include);
			$news = new hostergo_http_request($http,'hostergo');
			$outputjson=$news->DownloadToString();
			$outputarray=json_decode($outputjson,true);
			echo '<div id="main_body">';
			echo $outputarray['output'];
			echo '</div>';
			//if ($outputarray['redirect']) {
			//	header('Location:'.$outputarray['redirect']);
			//}
			break;
	}
	return "";

}


/**
 * Header hook: loads scripts and css files
 * @return unknown_type
 */
function zing_apps_player_header()
{
	if (!is_admin()) echo '<link rel="stylesheet" href="' . get_option("zing_ws_saas") . '/aphps/fwkfor/css/integrated_view.css" type="text/css" media="screen" />';
}

/**
 * Initialization of page, action & page_id arrays
 * @return unknown_type
 */
function zing_apps_player_init()
{
	ob_start();
	if (isset($_GET['zfaces']))
	{
		$pages=get_option("zing_hoster_pages");
		$pagearray=explode(",",$pages);
		$_GET['page_id']=$pagearray[0];
	}
}
?>