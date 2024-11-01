<?php
/*
 * Copy this file in the root directory of your Clientexec installation and access it via a browser.
 * It will create a file CE.txt in the root directory, upload this file via your Hostergo admin menu.
 */
require("./config.php");
echo 'Migrate data from Clientexec to Hostergo<br />';

// connect to db

$db = @mysql_connect($hostname,$dbuser,$dbpass) or die("<h1>Could not connect to the database. Please check your settings</h1>");
@mysql_select_db($database,$db) or die("<h1>Could not connect to the database. Please check your settings</h1>");
// open file
$myFile = "CE.txt";
$fh = fopen($myFile, 'w') or die("Can't open file");

//users
$query = "SELECT * FROM `".$dbtablesprefix."users` where `groupid` not in (-1) and `status` >= 0";
$sql = mysql_query($query) or die(mysql_error());
while (($user = mysql_fetch_array($sql)) && $i < 99999) {
	$r=array();
	$i++;
	$id=$user['id'];

	//user custom fields
	$query = "SELECT customuserfields.name,user_customuserfields.value FROM `".$dbtablesprefix."user_customuserfields`,  `".$dbtablesprefix."customuserfields` where customuserfields.id=user_customuserfields.customid and `userid`=".$id;
	$sql2 = mysql_query($query) or die(mysql_error());
	while ($user_cf = mysql_fetch_array($sql2)) {
		$field=$user_cf[0];
		$value=$user_cf[1];
		$user[$field]=$value;
	}


	$customer=array();
	$customer['loginname']=$user['email'];
	$customer['password']=$user['password'];
	$customer['lastname']=$user['lastname'];
	$customer['middlename']='';
	$customer['initials']=$user['firstname'];
	$customer['company']=$user['organisation'];
	$customer['email']=$user['email'];
	$customer['address']=$user['Address'];
	$customer['zip']=$user['Zipcode'];
	$customer['state']=$user['State'];
	$customer['city']=$user['City'];
	$customer['country']=$user['Country'];
	$customer['phone']=$user['Phone'];
	$customer['group']=group($user['groupid']);
	$customer['joindate']=$user['dateActivated'];
	$customer['newsletter']=$user['Receive Email Announcements'];
	//$user['status']
	//$user['currency']
	//$user['lastseen']
	//$user['clienttype']
	//$user['paymenttype']
	$r['type']='CE';
	$r['customer']=$customer;

	//alternate emails
	//----------------
	$query = "SELECT * FROM `".$dbtablesprefix."altuseremail` where `userid`=".$id;
	$sql2 = mysql_query($query) or die(mysql_error());
	while ($altemail = mysql_fetch_array($sql2)) {
	}

	//client notes
	//------------
	$query = "SELECT * FROM `".$dbtablesprefix."clients_notes` where `target_id`=".$id;
	$sql2 = mysql_query($query) or die(mysql_error());
	while ($user_domain = mysql_fetch_array($sql2)) {
	}


	//registered domains
	//------------------
	$domains=array();
	$query = "SELECT * FROM `".$dbtablesprefix."users_domains` where `userid`=".$id;
	$sql2 = mysql_query($query) or die(mysql_error());
	while ($user_domain = mysql_fetch_array($sql2)) {
		$domain=array();
		//		$domain['date_created']=
		//		$domain['date_updated']=
		$domain['name']=$user_domain['domain'];
		$domain['period']=$user_domain['period'];
		$domain['registration']=1;
		$domain['duedate']=$user_domain['nextbilldate'];
		switch ($user_domain['status']) {
			case 1:
				$domain['status']=20;
				break;
			default:
				die("Unrecognised domain status: ".$$user_domain['status']);
				break;
		}
		//		$domain['statusdate']=
		$domains[]=$domain;
	}
	$r['domains']=$domains;

	//packages
	//--------
	$packages=array();
	$query = "SELECT * FROM `".$dbtablesprefix."domains` where `customerid`=".$id;
	$sql2 = mysql_query($query) or die(mysql_error());
	while ($domain = mysql_fetch_array($sql2)) {
		$package=array();
		$package['date_created']=$domain['dateActivated'];
		$package['tempid']=$domain['id'];
		//		$package['date_updated']=
		//		$package['orderid']=
		$package['productname']=plan($domain['Plan']);
		$package['hostname']=server($domain['serverid']);
		switch ($domain['status']) {
			case 0:
				$package['status']=10;
				break;
			case 1:
				$package['status']=20;
				break;
			case 2:
				$package['status']=30;
				break;
			case 3:
				$package['status']=90;
				break;
			default:
				die("Unrecognised package status: ".$domain['status']);
				break;
		}
		$package['domain']=$domain['DomainName'];
		$package['username']=$domain['UserName'];
		$package['password']=$domain['password'];
		$package['frequency']=$domain['paymentterm'];
		if ($domain['use_custom_price']) $package['price']=$domain['customer_price'];
		$package['duedate']=$domain['nextbilldate'];
		//		$package['statusdate']=
		$packages[]=$package;
	}
	$r['packages']=$packages;

	//invoices
	//--------
	$invoices=array();
	$query = "SELECT * FROM `".$dbtablesprefix."invoice` where `customerid`=".$id;
	$sql2 = mysql_query($query) or die(mysql_error());
	while ($inv = mysql_fetch_array($sql2)) {
		$invoice=array();
		$invoicelines=array();
		$query = "SELECT * FROM `".$dbtablesprefix."invoiceentry` where `invoiceid`=".$inv['id'];
		$sql3 = mysql_query($query) or die(mysql_error());
		while ($entry = mysql_fetch_array($sql3)) {
			$invoiceline=array();
			$invoiceline['date_created']=$entry['date'];
			$invoiceline['appliestoid']=$entry['appliestoid'];
			$invoiceline['description']=$entry['description'].' ('.$entry['detail'].')';
			switch ($entry['billingtypeid']) {
				case -1:
					$invoiceline['type']=10; //hosting
					break;
				case -2:
					$invoiceline['type']=20; //registration
					break;
				default:
					$invoiceline['type']=30; //other
					break;

			}
			$invoiceline['price']=$entry['price'];
			$invoicelines[]=$invoiceline;
		}
		$invoice['lines']=$invoicelines;
		$invoice['date_created']=$inv['sentdate'];
		$invoice['duedate']=$inv['billdate'];
		if ($inv['status']==0 && $inv['sent']==0) $invoice['status']=10; //issued
		elseif ($inv['status']==0 && $inv['sent']==1) $invoice['status']=20; //sent
		elseif ($inv['status']==1) $invoice['status']=30; //paid
		elseif ($inv['status']==2) $invoice['status']=80; //void
		elseif ($inv['status']==3) $invoice['status']=40; //refunded
		else error("Couldn't determine status for invoice ".$inv['id']." (".$inv['status'].'/'.$inv['sent'].')');

		$invoice['topay']=$inv['amount'];
		if ($inv['datepaid']) $invoice['paid']=$inv['amount'];
		$invoice['currency']=strtoupper($user['currency']);
		//$invoice['reminder']=
		//$invoice['webid']=
		//$invoice['pdf']=
		$invoices[]=$invoice;
	}
	$r['invoices']=$invoices;

	//	print_r($r);
	//	echo '<br />----------------------';
	//	echo '<br />';
	$stringData=json_encode($r)."\r\n";
	//	echo '<br />----------------------';
	//	echo '<br />';
	fwrite($fh, $stringData);

}

fclose($fh);
if ($error) {
	echo "Errors occured, please fix them and try again";
	$fh = fopen($myFile, 'w') or die("Can't open file");
	fclose($fh);
} else {
	echo 'Exported '.$i.' clients';
}



//helper functions

function group($groupid) {
	$query = "SELECT * FROM `".$dbtablesprefix."groups` where `id`=".$groupid;
	$sql = mysql_query($query) or die(mysql_error());
	$group = mysql_fetch_array($sql) or die("Group ".$groupid." not found");
	if ($group['issuperadmin']) return 'ADMIN';
	if ($group['admin']) return 'USER';
	return 'CUSTOMER';
}
function plan($planid) {
	global $domain;
	$query = "SELECT planname FROM `".$dbtablesprefix."package` where `id`=".$planid;
	$sql = mysql_query($query) or die(mysql_error());
	$plan = mysql_fetch_array($sql) or die("Plan ".$planid." not found for ".$domain['DomainName']);
	return $plan['planname'];
}

function server($serverid) {
	global $domain;
	$query = "SELECT hostname FROM `".$dbtablesprefix."server` where `id`=".$serverid;
	$sql = mysql_query($query) or die(mysql_error());
	$server = mysql_fetch_array($sql) or die("Server ".$serverid." not found for ".$domain['DomainName']);
	return $server['hostname'];
}

function error($msg) {
	global $error;
	$error=true;
	echo $msg."<br />";
}

?>