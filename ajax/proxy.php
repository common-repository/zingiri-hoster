<?php
require('../../../../wp-blog-header.php');
if (!IsAdmin()) die('No access');

$http=zing_http("zhg",$to_include,$page);
$http=get_option("zing_ws_saas").'/saas/'.'saas.php';
$p='?';
foreach ($_GET as $n => $v) {
	$http.=$p.$n.'='.urlencode($v);
	$p='&';
}
$news = new hostergo_http_request($http,'hostergo');
$outputjson=$news->DownloadToString();
$ret=json_decode($outputjson,true);
//echo $outputjson;
echo $ret['output'];
?>