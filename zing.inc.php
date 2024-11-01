<?php

// Pre-2.6 compatibility for wp-content folder location
if (!defined("WP_CONTENT_URL")) {
	define("WP_CONTENT_URL", get_option("siteurl") . "/wp-content");
}
if (!defined("WP_CONTENT_DIR")) {
	define("WP_CONTENT_DIR", ABSPATH . "wp-content");
}

if (!defined("ZING_AJAX")) define("ZING_AJAX",false);

if (!defined("ZING_PLUGIN")) {
	$zing_plugin=str_replace(realpath(dirname(__FILE__).'/..'),"",dirname(__FILE__));
	$zing_plugin=substr($zing_plugin,1);
	define("ZING_PLUGIN", $zing_plugin);
}

if (!defined("ZING")) {
	define("ZING", true);
}
if (!defined("ZING_SAAS_URL")) { define("ZING_SAAS_URL",get_option("zing_ws_saas")); }

if (!defined("ZING_LOC")) {
	define("ZING_LOC", WP_CONTENT_DIR . "/plugins/".ZING_PLUGIN."/");
}
if (!defined("ZING_URL")) {
	define("ZING_URL", WP_CONTENT_URL . "/plugins/".ZING_PLUGIN."/");
}
if (!defined("ZING_HOME")) {
	define("ZING_HOME", get_option("home"));
}
if (!defined("BLOGUPLOADDIR")) {
	$upload=wp_upload_dir();
	if (isset($upload['basedir'])) define("BLOGUPLOADDIR",$upload['basedir'].'/');
}

$dbtablesprefix = $wpdb->prefix."zing_";
$dblocation = DB_HOST;
$dbname = DB_NAME;
$dbuser = DB_USER;
$dbpass = DB_PASSWORD;

$zing_version=get_option("zing_hoster_version");

require (ZING_LOC."./zing.subs.inc.php");
require(dirname(__FILE__)."/includes/httpclass.inc.php");
require(dirname(__FILE__)."/includes/hooks.login.inc.php");
require(dirname(__FILE__)."/includes/links.inc.php");
if ($zing_version && (get_option("zing_ws_key") != "")) {
	add_action("init","zing_init");
	add_filter('wp_footer','zing_footer');
	add_filter('get_pages','zing_exclude_pages');
	add_action("plugins_loaded", "zing_sidebar_init");
	add_filter('the_content', 'zing_content', 10, 3);
	add_action('wp_head','zing_header');
	add_action('admin_head','hostergo_admin_header');
}
add_action('admin_head','hostergo_admin_header_min');
add_action('admin_notices','hostergo_admin_notices');

require_once(dirname(__FILE__) . '/hoster_controlpanel.php');

function hostergo_admin_notices() {
	global $zing_ret;
	$errors=array();
	$warnings=array();
	$files=array();
	$dirs=array();

	if (isset($zing_ret['licmessage'])) $warnings[]=$zing_ret['licmessage'];
	$upload=wp_upload_dir();
	if ($upload['error']) $errors[]=$upload['error'];
	if (get_option('bookings_debug')) $warnings[]="Debug is active, once you finished debugging, it's recommended to turn this off";
	if (phpversion() < '5') $warnings[]="You are running PHP version ".phpversion().". We recommend you upgrade to PHP 5.3 or higher.";
	if (ini_get("zend.ze1_compatibility_mode")) $warnings[]="You are running PHP in PHP 4 compatibility mode. We recommend you turn this option off.";
	if (!function_exists('curl_init')) $errors[]="You need to have cURL installed. Contact your hosting provider to do so.";

	if (count($warnings) > 0) {
		echo "<div id='zing-warning' style='background-color:greenyellow' class='updated fade'><p><strong>";
		foreach ($warnings as $message) echo 'HosterGo: '.$message.'<br />';
		echo "</strong> "."</p></div>";
	}
	if (count($errors) > 0) {
		echo "<div id='zing-warning' style='background-color:pink' class='updated fade'><p><strong>";
		foreach ($errors as $message) echo 'HosterGo:'.$message.'<br />';
		echo "</strong> "."</p></div>";
	}

	return array('errors'=> $errors, 'warnings' => $warnings);
}

/**
 * Activation: creation of options & set up of pages
 * @return unknown_type
 */
function zing_activate() {
	global $wpdb;

	$zing_version=get_option("zing_hoster_version");
	update_option("zing_hoster_version",ZING_VERSION);

	//create default pages
	if (!$zing_version) {
		$pages=array();
		$pages[]=array("Shop","main","*",0);
		$pages[]=array("Cart","cart","",0);
		$pages[]=array("Checkout","conditions","checkout",0);
		$pages[]=array("Admin","admin","",9);
		$pages[]=array("Personal","my","",3);
		$pages[]=array("Login","my","login",1);
		$pages[]=array("Logout","logout","*",3);
		$pages[]=array("Register","customer","add",1);

		$ids="";
		foreach ($pages as $i =>$p)
		{
			$my_post = array();
			$my_post['post_title'] = $p['0'];
			$my_post['post_content'] = '';
			$my_post['post_status'] = 'publish';
			$my_post['post_author'] = 1;
			$my_post['post_type'] = 'page';
			$my_post['comment_status'] = 'closed';
			$my_post['menu_order'] = 100+$i;
			$id=wp_insert_post( $my_post );
			if (empty($ids)) { $ids.=$id; } else { $ids.=",".$id; }
			if (!empty($p[1])) add_post_meta($id,'zing_page',$p[1]);
			if (!empty($p[2])) add_post_meta($id,'zing_action',$p[2]);
			if (!empty($p[3])) add_post_meta($id,'zing_security',$p[3]);
		}
		update_option("zing_hoster_pages",$ids);
	}
}

/**
 * Deactivation
 * @return void
 */
function zing_deactivate() {
	wp_clear_scheduled_hook('zing_clean_cache_hook');
}

/**
 * Uninstallation: removal of options
 * @return void
 */
function zing_uninstall() {
	$error=false;
	$http=zing_http("unregister");
	$news = new hostergo_http_request($http,'hostergo');
	$outputjson=$news->DownloadToString();
	$outputarray=json_decode($outputjson,true);
	if ($outputarray['status']!=1) {
		$error=$outputjson;
	}
	$ids=get_option("zing_hoster_pages");
	$ida=explode(",",$ids);
	foreach ($ida as $id) {
		wp_delete_post($id);
	}
	delete_option("zing_hoster_version");
	delete_option("zing_hoster_pages");
	return $error;
}

/**
 * Main function handling content, footer and sidebars
 * @param $process
 * @param $content
 * @return unknown_type
 */
function zing_main($process,$content="") {

	if (isset($_GET['zfaces']) && ($_GET['zfaces'] && $process=="content")) return $content;
	global $post;
	global $wpdb;
	global $action;
	global $cat;
	global $cntry;
	global $customerid;
	global $dblocation;
	global $dbname;
	global $dbpass;
	global $dbtablesprefix;
	global $dbuser;
	global $default_lang;
	global $lang;
	global $zing_loaded;
	global $cntry;
	global $txt;
	global $zing_live;

	$to_include="";
	switch ($process)
	{
		case "content":
			$cf=get_post_custom();

			if (isset($_GET['page']))
			{
				//do nothing, page already set
			}
			elseif (isset($cf['zing_page']))
			{
				$_GET['page']=$cf['zing_page'][0];
				if (isset($cf['zing_action']))
				{
					$_GET['action']=$cf['zing_action'][0];
				}
			}
			else
			{
				return $content;
			}
			if (isset($cf['cat'])) {
				$_GET['cat']=$cf['cat'][0];
			}

			$to_include="loadmain.php";
			break;
		case "footer":
			$to_include="footer.php";
			break;
		case "sidebar":
			$to_include="menu_".$content.".php";
			break;
		case "init":
			break;
	}
	if (!$zing_loaded)
	{
		require (ZING_LOC."./zing.startmodules.inc.php");
		$zing_loaded=TRUE;
	} else {
		require (ZING_LOC."./includes/readvals.inc.php");        // get and post values
	}

	if ($to_include) {
		if ($zing_live) {
			$http=zing_http("zhg",$to_include,$page);
			$news = new hostergo_http_request($http,'hostergo');
			if ($news->live()) {
				$outputjson=$news->DownloadToString();
				//echo $outputjson;
				$outputarray=json_decode($outputjson,true);
				$txt=$outputarray['txt'];
				$output=zing_filter($outputarray['output'],$to_include,$page);
				if (isset($outputarray['page'])) {
					echo $outputarray['page'];
					//	header('Location:'.$outputarray['redirect']);
				}
				hostergo_process_return($outputarray,$output);
				echo $output;
			}
		} else {
			if ($process=='content') echo $txt['saas1'];
		}

	}
	else return $content;
}

function hostergo_process_return($ret,&$output) {
	global $blog_id;

	if (!isset($ret['action'])) return;

	if ($ret['action']=='create_user') {
		$user=array();
		$user['user_login']=$ret['email'];
		$user['user_email']=$ret['email'];
		$user['first_name']=$ret['initials'];
		$user['last_name']=$ret['surname'];
		$user['nickname']=$ret['initials'];
		$user['user_pass']=$ret['pass'];
		$user['role']='subscriber';
		$id=wp_insert_user($user);
		if (function_exists('add_user_to_blog')) {
			add_user_to_blog($blog_id,$id,$user['role']);
		}
		wp_signon(array('user_login'=>$user['user_login'],'user_password'=>$user['user_pass']));
	} elseif ($ret['action']=='login') {
		//print_r($_SERVER);
		//die();
		header('Location:'.get_option('siteurl').'/wp-login.php?redirect_to='.$_SERVER['REQUEST_URI']);
		die();
		//} elseif ($ret['action']=='show_login') {
		//	$loginForm=wp_login_form(array('echo'=>false));
		//	$output=$loginForm.$output;
	}

}

function zing_filter($output,$to_include,$page) {
	if ($to_include == "loadmain.php" && $page == "conditions") {
		if ($cp=get_page(get_option("zing_ws_conditions_page"))) {
			$output=str_replace("[zing-conditions-page]",str_replace(chr(13),"<br />",$cp->post_content),$output);
			//$output=str_replace(chr(13),"<br />",$output);
		}
	}
	$output=str_replace(':443','',$output);
	$output=str_replace('https://','http://',$output);
	return $output;
}

function zing_http($module,$to_include="",$page="",$key="",$other="") {
	global $current_user;

	if ($module=='register') $http=get_option("zing_ws_saas").'/saas/'.'register.php';
	elseif ($module=='unregister') $http=get_option("zing_ws_saas").'/saas/'.'unregister.php';
	elseif ($module=='livesearch') $http=ZING_URL.'ajax/'.'proxy.php';
	else $http=get_option("zing_ws_saas").'/saas/'.'saas.php';
	$http.= '?module='.$module;
	if ($module=='register') {
		$http.= '&saas_blogname='.urlencode(get_option("blogname"));
		$http.= '&saas_blogdescription='.urlencode(get_option("blogdescription"));
		$http.= '&saas_email='.urlencode(get_option("admin_email"));
	}
	if (isset($_POST['loginname'])) $http.= '&name='.$_POST['loginname'];
	if (isset($_POST['pass'])) $http.= '&pass='.$_POST['pass'];

	if (get_option("zing_ws_key")) $http.= '&saas_hash='.urlencode(md5(get_option("zing_ws_key").ZING_HOME));
	if (!empty($key)) $http.= '&saas_key='.urlencode($key);

	if ($page) $http.= '&page='.$page;
	if ($to_include) $http.= '&include='.$to_include;
	$http.= '&ip='.GetUserIP();
	$http.= '&home='.urlencode(ZING_HOME);
	$http.= '&saas_id='.urlencode(get_option('zing_ws_id'));
	$http.= '&saas_version='.urlencode(ZING_VERSION);
	$http.= '&saas_lic='.urlencode(get_option('zing_ws_lic'));
	$http.= '&saas_url='.urlencode(ZING_URL);

	$wp=array();
	$wp['isadmin']=is_admin() ? 1 : 0;
	if (isset($current_user->data)) {
		$wp['login']=$current_user->data->user_login;
		$wp['lastname']=get_user_meta(1,'last_name',true);
		$wp['firstname']=get_user_meta(1,'first_name',true);
	} else {
		$wp['loginurl']=wp_login_url();
	}
	$wp['prefixurl']=hostergo_default_page_prefix();
	if (!isset($_SESSION['hostergo']['guest'])) {
		$_SESSION['hostergo']['guest']=create_sessionid(8);
	}
	$wp['guest']=$_SESSION['hostergo']['guest'];
	$http.='&wp='.base64_encode(json_encode($wp));
	if (!($module=="zhg" && $page=='check')) {
		if (count($_GET) > 0) {
			foreach ($_GET as $n => $v) {
				if ($page && $n=="page") /*do nothing*/;
				else $http.= '&saas_get_'.$n.'='.urlencode($v);
			}
		}
		if (count($_FILES) > 0) {
			foreach ($_FILES as $n1 => $v1) {
				foreach ($v1 as $n2 => $v2) {
					if ($n2 == 'tmp_name') {
						$v2new=md5($v2);
						$putdata = fopen(ZING_LOC.'cache/'.$v2new, "w");
						$fp = fopen($v2, "r");
						while ($data = fread($fp, 1024)) {
							fwrite($putdata, $data);
						}
						fclose($fp);
						fclose($putdata);
						$v2=ZING_URL.'cache/'.$v2new;
					}
					$http.= '&saas_files_'.$n1.'__'.$n2.'='.urlencode($v2);

				}
			}
		}
	}
	return $http;
}
/**
 * Page content filter
 * @param $content
 * @return unknown_type
 */
function zing_content($content) {
	return zing_main("content",$content);
}


/**
 * Header hook: loads scripts and css files
 * @return unknown_type
 */
function zing_header()
{
	echo '<link rel="stylesheet" type="text/css" href="' . ZING_URL . 'css/zing.css" media="screen" />';
	echo '<script type="text/javascript" src="' . get_option("zing_ws_saas") . '/zhg/js/admin.js"></script>';
	echo '<script type="text/javascript" src="' . get_option("zing_ws_saas") . '/zhg/js/live_search.js"></script>';
	//echo '<script type="text/javascript" language="javascript">';
	//echo '</script>';
}

function hostergo_admin_header_min()
{
	echo '<link rel="stylesheet" type="text/css" href="' . ZING_URL . 'css/support-us.css" media="screen" />';
}

function hostergo_admin_header()
{
	echo '<link rel="stylesheet" type="text/css" href="' . ZING_URL . 'css/zing.css" media="screen" />';
	echo '<script type="text/javascript" src="' . get_option("zing_ws_saas") . '/zhg/js/admin.js"></script>';
	echo '<script type="text/javascript" src="' . get_option("zing_ws_saas") . '/zhg/js/live_search.js"></script>';
	echo '<script type="text/javascript" language="javascript">';
	echo "var zfajax='admin-ajax.php?page=form&zf=form_edit&ajax=1&scr=';";
	echo '</script>';

}

/**
 * Sidebar products menu widget
 * @param $args
 * @return unknown_type
 */
function widget_sidebar_products($args) {
	global $txt;
	zing_main("init");
	extract($args);
	echo $before_widget;
	echo $before_title;
	echo $txt['menu15'];
	echo $after_title;
	echo '<div id="zing-sidebar">';
	echo '<style type="text/css">';
	echo '#zing-sidebar h1 {display:none; }';
	echo '</style>';
	zing_main("sidebar","products");
	echo "</div>";
	echo $after_widget;
}

/**
 * Sidebar cart menu widget
 * @param $args
 * @return unknown_type
 */
function widget_sidebar_cart($args) {
	global $txt;
	zing_main("init");
	extract($args);
	echo $before_widget;
	echo $before_title;
	echo $txt['menu2'];
	echo $after_title;
	echo '<div id="zing-sidebar">';
	echo '<style type="text/css">';
	echo '#zing-sidebar h1 {display:none; }';
	echo '</style>';
	zing_main("sidebar","cart");
	echo '</div>';
	echo $after_widget;

}

/**
 * Sidebar admin menu widget
 * @param $args
 * @return unknown_type
 */
function widget_sidebar_admin($args) {
	global $txt;
	zing_main("init");
	extract($args);
	echo $before_widget;
	echo $before_title;
	echo $txt['menu9'];
	echo $after_title;
	echo '<div id="zing-sidebar">';
	echo '<style type="text/css">';
	//echo 'h1#zing-sidebar {display:none; }';
	echo '</style>';
	zing_main("sidebar","admin");
	echo '</div>';
	echo $after_widget;
}

/**
 * Sidebar search bar widget
 * @param $args
 * @return unknown_type
 */
function widget_sidebar_searchbar($args) {
	global $txt;
	extract($args);
	echo $before_widget;
	echo '<form method="get" name="searchform">';
	echo '<div><input type="text" value="" id="s" name="s" style="width: 95%;" />';
	echo '<span class="art-button-wrapper">';
	echo '<span class="l"> </span>';
	echo '<span class="r"> </span>';
	echo '</span></div></form>';
	echo $after_widget;
}

/**
 * Register sidebar widgets
 * @return unknown_type
 */
function zing_sidebar_init()
{
	global $pagenow;
	if (current_user_can('edit_plugins') || get_option('zing_ws_testing')!="Yes") {
		wp_register_sidebar_widget('hostergo_widget_cart',__('HosterGo Cart'), 'widget_sidebar_cart');
		wp_register_sidebar_widget('hostergo_widget_products',__('HosterGo Products'), 'widget_sidebar_products');
	}
	if (IsAdmin() || ($pagenow=='widgets.php' && current_user_can('edit_plugins'))) {
		wp_register_sidebar_widget('hostergo_widget_admin',__('HosterGo Admin'), 'widget_sidebar_admin');
		wp_register_sidebar_widget('hostergo_widget_searchbar',__('HosterGo Search Bar'), 'widget_sidebar_searchbar');
	}
}

/**
 * Initialization of page, action & page_id arrays
 * @return unknown_type
 */
function zing_init()
{
	global $wp_version;
	//wp_enqueue_script('prototype');
	wp_enqueue_script('jquery');
	if (is_admin()) {
		wp_enqueue_script(array('jquery-ui-core','jquery-ui-datepicker','jquery-ui-sortable'));
		wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/flick/jquery-ui.css');
		if (isset($_REQUEST['page']) && ($_REQUEST['page']=='form')) {
			if ($wp_version < '3.3') {
				wp_enqueue_script(array('editor', 'thickbox', 'media-upload'));
				wp_enqueue_style('thickbox');
			}
		}
	} else {
		wp_enqueue_script(array('jquery-ui-core','jquery-ui-datepicker'));
		wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/flick/jquery-ui.css');
	}

	if (!session_id()) session_start();

	global $dbtablesprefix;
	global $dblocation;
	global $dbname;
	global $dbuser;
	global $dbpass;
	global $product_dir;
	global $brands_dir;
	global $orders_dir;
	global $lang_dir;
	global $template_dir;
	global $gfx_dir;
	global $scripts_dir;
	global $products_dir;
	global $index_refer;
	global $name;
	global $customerid;

	if ( ( defined( 'WP_ADMIN' ) && WP_ADMIN == true ) || ( strpos( $_SERVER[ 'PHP_SELF' ], 'wp-admin' ) !== false ) ) return;

	global $zing_page_id_to_page, $zing_page_to_page_id, $wpdb;
	global $name;
	global $customerid;

	$zing_page_id_to_page=array();
	$zing_page_to_page_id=array();

	$sql = "SELECT post_id,meta_value FROM $wpdb->postmeta WHERE meta_key = 'zing_page'";
	$a = $wpdb->get_results( $sql );

	foreach ($a as $i => $o )
	{
		$zing_page_id_to_page[$o->post_id][0]=$o->meta_value;
	}
	$sql = "SELECT post_id,meta_value FROM $wpdb->postmeta WHERE meta_key = 'zing_action'";
	$a = $wpdb->get_results( $sql );
	foreach ($a as $i => $o )
	{
		$zing_page_id_to_page[$o->post_id][1]=$o->meta_value;
	}

	$zing_page_to_page_id=array();
	foreach ($zing_page_id_to_page as $i => $a)
	{
		$page=$a[0];
		$action=isset($a[1]) ? $a[1] : '';
		if (isset($a[0]) && isset($a[1]))
		{
			$zing_page_to_page_id[$page][$action]=$i;
		}
		if (isset($a[0]) && !isset($a[1]))
		{
			$zing_page_to_page_id[$page]['*']=$i;
		}
	}

	if (isset($_POST['page']) && ($_POST['page']=="login") && (!isset($_POST['lostlogin']) || empty($_POST['lostlogin'])))
	{
		require(dirname(__FILE__)."/includes/login.php");
		exit;
	}
	if (!empty($_GET['page_id']) && ($_GET['page_id']==zing_page_id("logout")))
	{
		wp_logout();
		header('Location:'.get_option("home"));
		exit;
	}

	if (!isset($_GET['page_id']) && isset($_GET['page']))
	{
		//cat is a parameter used by Wordpress for categories
		if (isset($_GET['cat']) && isset($_GET['page'])) {
			$_GET['kat']=$_GET['cat'];
			unset($_GET['cat']);
		}

		$_GET['page_id']=zing_page_id("main");
	}
}

/**
 * Look up page name based on Wordpress page_id
 * @param $page_id
 * @return unknown_type
 */
function zing_page($page_id)
{
	global $zing_page_id_to_page;
	if (isset($zing_page_id_to_page[$page_id]))
	{
		return $zing_page_id_to_page[$page_id];
	}
	return "main";
}

/*
 * Cron job
 */
function zing_clean_cache() {
}

if (!wp_next_scheduled('zing_clean_cache_hook')) {
	wp_schedule_event( time(), 'daily', 'zing_clean_cache_hook' );
}
add_action('zing_clean_cache_hook','zing_clean_cache');

/**
 * Look up Wordpress page_id based on page name and action
 * @param $page
 * @param $action
 * @return unknown_type
 */
function zing_page_id($page,$action="*")
{
	global $zing_page_to_page_id;

	if (isset($zing_page_to_page_id[$page][$action]))
	{
		return $zing_page_to_page_id[$page][$action];
	}
	elseif (isset($zing_page_to_page_id[$page]))
	{
		echo $page;
		echo $action;
		echo "this case";die();
		return $zing_page_to_page_id[$page];
	}
	return "";
}

/**
 * Exclude certain pages from the menu depending on whether the user is logged in
 * or is an administrator. This depends on the custom field "security":
 * 	0 - show if not logged in
 * 	1 - show if not logged in but hide if logged in
 *  2 - show if customer logged in
 *  3 - show if customer or user or admin logged in
 * 	4 - show if not logged in or if customer logged in
 *  5 - show if user or customer logged in
 *  6 - show if user or admin logged in
 *  9 - show if admin logged in
 * @param $pages
 * @return unknown_type
 */
function zing_exclude_pages( $pages )
{
	$bail_out = ( ( defined( 'WP_ADMIN' ) && WP_ADMIN == true ) || ( strpos( $_SERVER[ 'PHP_SELF' ], 'wp-admin' ) !== false ) );
	if ( $bail_out ) return $pages;

	Global $dbtablesprefix;
	Global $cntry;
	Global $lang;
	Global $lang2;
	Global $lang3;

	$loggedin=LoggedIn();
	if ($loggedin) $isadmin=IsAdmin(); else $isadmin=false;
	if (!$isadmin) $iscustomer=true; else $iscustomer=false;

	$unsetpages=array();
	$l=count($pages);
	for ( $i=0; $i<$l; $i++ ) {
		$page = & $pages[$i];
		$security=get_post_meta($page->ID,"zing_security",TRUE);
		$show=false;
		if ($security == 0) {
			$show=true;
		}
		elseif ($security == "1" && !$loggedin) {
			$show=true;
		}
		elseif ($security == "2" && $loggedin && $iscustomer) {
			$show=true;
		}
		elseif ($security == "3" && $loggedin) {
			$show=true;
		}
		elseif ($security == "4" && (!$loggedin || $iscustomer)) {
			$show=true;
		}
		elseif ($security == "5" && $loggedin && !$isadmin && ($isuser || $iscustomer)) {
			$show=true;
		}
		elseif ($security == "6" && $loggedin && ($isuser || $isadmin)) {
			$show=true;
		}
		elseif ($security == "9" && $loggedin && $isadmin) {
			$show=true;
		}

		if (!current_user_can('edit_plugins') && get_option('zing_ws_testing')=="Yes" && get_post_meta($page->ID,"zing_page",TRUE)!="") {
			$show=false;
		}
		if (!$show || get_option("zing_ws_show_menu_".$page->ID)=="No")
		{
			unset($pages[$i]);
			$unsetpages[$page->ID]=true;
		}
	}

	return $pages;
}

/**
 * The footer is automatically inserted for Artisteer generated themes.
 * For other themes, the function zing_footer should be called from inside the theme.
 * @param $footer
 * @return unknown_type
 */
function zing_footer($footer="")
{
	$bail_out = ( ( defined( 'WP_ADMIN' ) && WP_ADMIN == true ) || ( strpos( $_SERVER[ 'PHP_SELF' ], 'wp-admin' ) !== false ) );
	if ( $bail_out ) return $footer;
}

function strip_slashes($value) {
	$value = stripslashes($value);
	$value = str_replace("/", "[fw$]", $value);
	$value = str_replace(".", "[fw$]", $value);
	$value = strip_tags($value);
	return $value;
}

// is the visitor logged in?
Function LoggedIn() {
	if (is_user_logged_in()) return true;

	global $zing_loggedin;

	if (empty($zing_loggedin)) zing_init_saas();
	return $zing_loggedin;
}

// is the id of an admin?
Function IsAdmin() {
	global $zing_isadmin;

	if (empty($zing_isadmin)) zing_init_saas();
	return $zing_isadmin;
}

function zing_init_saas() {
	global $txt,$zing_loggedin,$zing_isadmin,$zing_live,$zing_ret;

	if (isset($_REQUEST['action']) && ($_REQUEST['action']=='aphps_ajax')) return;

	$http=zing_http("zhg","","check");
	$news = new hostergo_http_request($http,'hostergo');
	$json=$news->DownloadToString();
	$ret=json_decode($json,true);
	$txt=$ret['txt'];
	$zing_loggedin=isset($ret['loggedin']) ? $ret['loggedin'] : false;
	$zing_isadmin=isset($ret['isadmin']) ? $ret['isadmin'] : false;
	$zing_live=isset($ret['live']) ? $ret['live'] : false;
	$zing_ret=$ret;
}

function jsonDecode($json,$assoc=true) {
	$json=str_replace('\"','"',$json);
	return json_decode($json,$assoc);
}

/**
 * Check if the plugin has been properly activated
 * @return boolean
 */
function zing_check() {
	$errors=array();
	$warnings=array();
	$dirs=array();
	$zing_version=get_option("zing_hoster_version");

	if ($zing_version == "") { $errors[]='Please proceed with a clean install or deactivate your plugin'; 	return array('errors'=> $errors, 'warnings' => $warnings); }

	$upload=wp_upload_dir();
	if ($upload['error']) $warnings[]=$upload['error'];

	if (!function_exists('curl_init'))	$errors[]="You need to install the PHP cURL extension to be able to use this plugin";
	return array('errors'=> $errors, 'warnings' => $warnings);

}

add_action('wp_ajax_aphps_ajax', 'aphps_ajax_callback');
function aphps_ajax_callback() {
	$to_include=$_REQUEST['zf'];
	$page='';
	$http=zing_http("zap",$to_include,$page);
	$p='?';
	$get=$_GET;
	foreach ($get as $n => $v) {
		$http.=$p.$n.'='.urlencode($v);
		$p='&';
	}
	$news = new hostergo_http_request($http,'hostergo');
	$outputjson=$news->DownloadToString();
	$ret=json_decode($outputjson,true);
	echo $ret['output'];

	die();
}
