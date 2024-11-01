<?php
$zing_ws_name = "HosterGo";
$zing_ws_shortname = "zing_ws";

function zing_options() {
	global $zing_ws_name, $zing_ws_shortname;
	global $wpdb;

	$install_type = array("Clean" );
	$zing_yn = array("Yes" => "Yes", "No" => "No");
	$zing_conditions_pages=array();
	$zing_conditions_pages[0]='';

	$a = $wpdb->get_results( "SELECT id, post_title FROM ".$wpdb->prefix."posts WHERE post_type='page' AND post_status='publish' ORDER BY post_title" );
	foreach ($a as $o) {
		$zing_conditions_pages[$o->id]=$o->post_title;
	}

	$controlpanelOptions = array (

	array(  "name" => "Connection settings",
            "type" => "heading",
			"desc" => "This section customizes the HosterGo connections settings.",
	),

	array(	"name" => "Remote server URL",
			"desc" => "URL for the remote server. It is recommended that you use the secure https protocol.",
			"id" => $zing_ws_shortname."_saas",
			"std" => "http://bowmore.hostergo.com/hostergo",
			"type" => "text"),

	array(	"name" => "Account name",
			"desc" => "This is your account name.",
			"id" => $zing_ws_shortname."_id",
			"std" => get_option('home'),
			"type" => "text"),

	array(	"name" => "Access key",
			"desc" => "This is your unique access key. It is used in combination with your account name to securely access our remote servers. <br />Please take note of it in a secure place.",
			"id" => $zing_ws_shortname."_key",
			"std" => create_sessionid(32,1),
			"type" => "text"),

	array(	"name" => "License key",
			"desc" => 'The HosterGo service comes with a 60 day free trial, if you wish to continue with your free trial afterwards, you\'ll need to enter your license key here. You can purchase a license at any time <a href="https://go.zingiri.com/cart.php?gid=7">here</a>.',
			"id" => $zing_ws_shortname."_lic",
			"std" => "",
			"type" => "text"),

	array(  "name" => "Integration settings",
            "type" => "heading",
			"desc" => "This section customizes the HosterGo integration settings.",
	),

	array(	"name" => "Conditions page",
			"desc" => "Select the page you wish to use to display your conditions. This will be displayed to your customers during the checkout process.",
			"id" => $zing_ws_shortname."_conditions_page",
			"std" => "",
			"type" => "selectwithkey",
			"options" => $zing_conditions_pages),

	);

	if ($ids=get_option("zing_hoster_pages")) {
		$ida=explode(",",$ids);
		foreach ($ida as $i) {
			$p = $wpdb->get_results( "SELECT post_title FROM ".$wpdb->prefix."posts WHERE id='".$i."'" );
			$controlpanelOptions[]=array(	"name" => $p[0]->post_title." page",
			"desc" => "Display ".$p[0]->post_title." page in the menus.",
			"id" => $zing_ws_shortname."_show_menu_".$i,
			"std" => "Yes",
			"type" => "select",
			"options" => $zing_yn);
		}
	}
	$controlpanelOptions[]=	array(  "name" => "Other settings",
            "type" => "heading",
			"desc" => "This section customizes other settings.");
	$controlpanelOptions[]=	array(	"name" => "Testing mode",
			"desc" => "Setting this to yes only shows the pages when you are logged in as an administrator. It will not be shown to the public.",
			"id" => $zing_ws_shortname."_testing",
			"std" => "Yes",
			"type" => "select",
			"options" => $zing_yn);

	$controlpanelOptions[]=	array(  "name" => "Before you install",
            "type" => "heading",
			"desc" => '<div style="width:800px;font-size:smaller;color:red;"><div style="text-decoration:underline;display:inline;font-weight:bold">IMPORTANT:</div> HosterGo uses web services stored on Zingiri\'s servers. In doing so, personal data is collected and stored on our servers. 
					This data includes amongst others your admin email address as this is used, together with the API key as a unique identifier for your account on Zingiri\'s servers.
					We have a very strict <a href="http://www.zingiri.com/privacy-policy/" target="_blank">privacy policy</a> as well as <a href="http://www.zingiri.com/terms/" target="_blank">terms & conditions</a> governing data stored on our servers.
					<div style="font-weight:bold;display:inline">By installing this plugin you accept these terms & conditions.</div></div>',
	);

	return $controlpanelOptions;
}

function zing_ws_add_admin() {
	global $zingError;
	global $zing_ws_name, $zing_ws_shortname;

	$controlpanelOptions=zing_options();

	if ( isset($_GET['page']) && ($_GET['page'] == 'hostergo') ) {
		if ( isset($_REQUEST['action']) && ('update' == $_REQUEST['action']) ) {
			unset($_SESSION['hostergo']['menus']);
			zing_activate();

			foreach ($controlpanelOptions as $value) {
				if( isset( $_REQUEST[ $value['id'] ] ) ) {
					update_option( $value['id'], $_REQUEST[ $value['id'] ]  );
				} else { delete_option( $value['id'] );
				}
			}
			header("Location: admin.php?page=hostergo&updated=true");
			die;
		} elseif ( isset($_REQUEST['action']) && ('install' == $_REQUEST['action']) ) {
			unset($_SESSION['hostergo']['menus']);
			zing_activate();
			foreach ($controlpanelOptions as $value) {
				if( isset($value['id']) && isset( $_REQUEST[ $value['id'] ] ) ) {
					if ($value['id']=="zing_ws_key") {
						$key=$_REQUEST[$value['id']];
						$http=zing_http("register","","",$key);
						$news = new hostergo_http_request($http,'hostergo');
						$outputjson=$news->DownloadToString();
						$outputarray=json_decode($outputjson,true);
						if ($outputarray['status']==1) {
							update_option("zing_ws_key",$key);
						} else {
							$zingError=$outputjson;
						}
					} else {
						update_option( $value['id'], $_REQUEST[ $value['id'] ]  );
					}
				}
			}
			if (get_option("zing_ws_key")!="") {
				header("Location: admin.php?page=hostergo&installed=true");
				die;
			}
		} elseif( isset($_REQUEST['action']) && ('uninstall' == $_REQUEST['action']) ) {
			$zingError=zing_uninstall();
			foreach ($controlpanelOptions as $value) {
				delete_option( $value['id'] );
			}
			header("Location: admin.php?page=hostergo&uninstalled=true");
			die;
		}
	}

	add_menu_page($zing_ws_name." Options", "$zing_ws_name", 'activate_plugins', 'hostergo', 'zing_ws_admin');
	add_submenu_page('hostergo', $zing_ws_name, $zing_ws_name, 'manage_options', 'hostergo', 'zing_ws_admin');

	if (get_option('zing_ws_key')) hostergo_admin_menu();

}

function hostergo_admin_menu() {
	if (!isset($_SESSION['hostergo']['menus'])) {
		$http=zing_http("zhg",'menu_admin_json.php');
		$news = new hostergo_http_request($http,'hostergo');
		if ($news->live()) {
			$outputjson=$news->DownloadToString();
			$outputarray=json_decode($outputjson,true);
			$menus=json_decode($outputarray['output'],true);
			$_SESSION['hostergo']['menus']=$menus;

		}
	}
	$menus=$_SESSION['hostergo']['menus'];

	$prev='';
	if (isset($menus) && is_array($menus) && count($menus) > 1) {
		foreach ($menus as $slug => $menu) {
			//print_r($menu);echo '<br />';
			if ($prev != $menu['menu']) {
				add_menu_page( $menu['menutxt'], $menu['menutxt'], 'activate_plugins', $slug, 'hostergo_admin'); //, $icon_url, $position
				$prev=$menu['menu'];
				$first=true;
				$mainSlug=$slug;
			}
			add_submenu_page( $mainSlug, $menu['submenutxt'], $menu['submenutxt'], 'activate_plugins', $first ? $slug : $slug, 'hostergo_admin' );
			$first=false;
		}
	}
}

function hostergo_admin() {
	$u=explode('&',$_SESSION['hostergo']['menus'][$_GET['page']]['href']);
	foreach ($u as $pair) {
		list($n,$v)=explode('=',$pair);
		if (!isset($_REQUEST[$n]) || ($n=='page')) {
			$_GET[$n]=$v;
		}
	}
	if (isset($_POST['zfaces'])) {
		$type='zap';
		$include="scripts/".$_REQUEST['zfaces'].".php";
	} elseif (isset($_GET['zfaces'])) {
		$type='zap';
		$include="scripts/".$_GET['zfaces'].".php";
	} elseif (isset($_POST['page'])) {
		$type='zhg';
		$include=$_POST['page'].'.php';
	} else {
		$type='zhg';
		$include=$_GET['page'].'.php';
	}
	$http=zing_http($type,$include);
	$news = new hostergo_http_request($http,'hostergo');
	if ($news->live()) {
		$outputjson=$news->DownloadToString();
		$outputarray=json_decode($outputjson,true);
		$output=$outputarray['output'];
	} else $output='';

	echo '<div class="wrap">';
	echo '<div style="position:relative;width:100%;float:left;">';
	echo $output;
	echo '</div>';
	echo '<div style="clear:both"></div>';
	echo '<div style="text-align:center;margin-top:15px">';
	echo '<a href="http://www.zingiri.com" target="_blank"><img width="150px" src="'.plugins_url().'/zingiri-hoster/images/logo.png" /></a>';
	echo '</div>';
	echo '</div>';
		
}

function zing_ws_admin() {
	global $zingError,$zing_version;
	global $zing_ws_name, $zing_ws_shortname;

	$controlpanelOptions=zing_options();
	if ($zingError) echo '<div id="message" class="updated fade"><p>An error occured:<br />'.$zingError.'</p></div>';
	elseif ( isset($_REQUEST['installed']) && $_REQUEST['installed'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_ws_name.' installed.</strong></p></div>';
	elseif ( isset($_REQUEST['installed']) && $_REQUEST['uninstalled'] ) echo '<div id="message" class="updated fade"><p><strong>'.$zing_ws_name.' uninstalled.</strong></p></div>';

	?>
<div class="wrap">
	<div id="cc-left" style="position: relative; float: left; width: 80%">

		<h2>
		<?php echo $zing_ws_name; ?>
		</h2>
		<form method="post">
		<?php require(dirname(__FILE__).'/includes/cpedit.inc.php');?>
		<?php if (!$zing_version || get_option('zing_ws_key')=="") { ?>
			<p>
				By choosing to install Hoster, an account will automatically be
				created for you on our remote servers.<br /> Only you will have
				access to this account. To make sure you will always be able to
				access your account, take note of the connection settings.
			</p>
			<p class="submit">
				<input name="install" type="submit" value="Install" /> <input
					type="hidden" name="action" value="install" />
			</p>
			<?php } else {?>
			<p class="submit">
				<input name="install" type="submit" value="Update" /> <input
					type="hidden" name="action" value="update" />
			</p>
			<?php }?>
		</form>
		<?php if ($zing_version && get_option('zing_ws_key')!="") { ?>
		<hr />
		<p>Before deactivating your plugin make sure you uninstall it first.</p>
		<form method="post">
			<p class="submit">
				<input name="uninstall" type="submit" value="Uninstall" /> <input
					type="hidden" name="action" value="uninstall" />
			</p>
		</form>
		<?php } ?>
		<hr />
		<p>
			For more info and support, contact us at <a
				href="http://www.zingiri.com/">Zingiri</a>.
		</p>
	</div>
	<!-- end cc-left -->
	<?php
	require(dirname(__FILE__).'/includes/support-us.inc.php');
	zing_support_us('hostergo','zingiri-hoster','hostergo',ZING_VERSION);
	?>
</div>
	<?php
}
add_action('admin_menu', 'zing_ws_add_admin'); ?>