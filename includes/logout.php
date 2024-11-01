<?php
$http=zing_http("zhg","","logout");
$news = new hostergo_http_request($http,'hostergo');
$json=$news->DownloadToString();
$ret=json_decode($json,true);
unset($_SESSION['hostergo']);

?>
<html>
<head>
<META HTTP-EQUIV="Refresh" CONTENT="1; URL=<?php echo get_option('home');?>">
</head>
<body>
<br />
<br />
<br />
<br />
<br />
<br />
<br />
<center><h4><?php echo $ret['message'] ?></h4></center>
</body>
</html>
