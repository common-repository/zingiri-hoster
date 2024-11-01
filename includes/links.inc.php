<?php
function hostergo_link($url,$printurl=false) {
	global $urlFix;

	$pageID = hostergo_default_page();
	if (get_option('permalink_structure')){
		$homePage = get_option('home');
		$wordpressPageName = get_permalink($pageID);
		$wordpressPageName = str_replace($homePage,"",$wordpressPageName);
		$pid="";
		$home=$homePage.$wordpressPageName;
	}else{
		$pid='page_id='.$pageID;
		$home=get_option('home').'/';
	}

	if (is_admin()) {
		$url=str_replace('index.php','admin.php',$url);
	} else {
		if (strstr($url,$home)===false) {
			$url=str_replace('index.php',$home.'index.php',$url);
			if ($pid && strstr($url,'?')) {
				$url.='&'.$pid;
			} elseif ($pid) {
				$url.='?'.$pid;
			}
		}
	}

	$url=str_replace('index.php','',$url);

	if (count($urlFix) > 0) {
		foreach ($urlFix as $pair) {
			if (strstr($url,'?')) $url.='&'.$pair[0].'='.$pair[1];
			else $url.='?'.$pair[0].'='.$pair[1];
		}
	}
	if ($printurl) echo $url;
	else return $url;
}

function hostergo_default_page_prefix() {
	$pageID = hostergo_default_page();
	if (get_option('permalink_structure')){
		$homePage = get_option('home');
		$wordpressPageName = get_permalink($pageID);
		$wordpressPageName = str_replace($homePage,"",$wordpressPageName);
		$pid="";
		$home=$homePage.$wordpressPageName.'/?';
	}else{
		$home=get_option('home').'/?page_id='.$pageID.'&';
	}
	return $home;
}

function hostergo_default_page() {
	$ids=get_option("zing_hoster_pages");
	$ida=explode(",",$ids);
	return $ida[0];
}
