<?php
require('../../../../wp-blog-header.php');

$http=zing_http("zhg","ipn.php");
$p='?';
foreach ($_GET as $n => $v) {
	$http.=$p.$n.'='.urlencode($v);
	$p='&';
}
echo $http;
$news = new hostergo_http_request($http,'hostergo');
$outputjson=$news->DownloadToString();
$ret=json_decode($outputjson,true);
//echo $outputjson;
print_r($ret);
?>