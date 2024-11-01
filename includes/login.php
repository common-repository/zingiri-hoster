<?php
$lostlogin = 0;
if (!empty($_GET['lostlogin'])) {
	$lostlogin=$_GET['lostlogin'];
}
$email = "";
if (!empty($_POST['email'])) {
	$email=$_POST['email'];
}
if ($email == "") { $email = "--"; }


if ($lostlogin == 0) {
	$post_name = $_POST['loginname'];
	$post_pass = $_POST['pass'];
	$http=zing_http("zhg","","login");
	$news = new hostergo_http_request($http,'hostergo');
	$json=$news->DownloadToString();
	$ret=json_decode($json,true);
	$pagetoload = $ret['page'];
		?>
<html>
<head>
<META HTTP-EQUIV="Refresh"
	CONTENT="1; URL=index.php?<?php echo $pagetoload; ?>">
</head>
<body>
<br />
<br />
<br />
<br />
<br />
<br />
<br />
<center><h4><?php echo $ret['message']; ?></h4></center>
</body>
</html>
<?php 
}
?>