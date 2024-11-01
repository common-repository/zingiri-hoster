<?php
add_filter('check_password','hostergo_check_password',10,4);
add_action('profile_update','hostergo_profile_update'); //post wp update
add_filter( 'authenticate', 'hostergo_sso_authenticate', 10, 3 );

function hostergo_sso_authenticate($user, $username, $password) {
	global $blog_id;

	//If the user name is empty we exit
	if (!$username) return null;

	//If the user exists in WP we don't have to do anything
	if (get_userdatabylogin( $username )) return null;

	//If the user doesn't exist in WP we check if the user exists in HosterGo and create it if necessary
	$http=zing_http("zhg","","customerget");
	$news = new hostergo_http_request($http,'hostergo');
	$news->post=array('loginname'=>$username);
	$ret=json_decode($news->DownloadToString(),true);
	if (isset($ret['status']) && $ret['status']) {
		$user=array();
		$user['user_login']=$username;
		$user['user_email']=$ret['email'];
		$user['first_name']=$ret['initials'];
		$user['last_name']=$ret['lastname'] ? $ret['lastname'] : $ret['company'];
		$user['user_pass']=$password;
		$user['role']='subscriber';
		$id=wp_insert_user($user);

		if (function_exists('add_user_to_blog')) {
			add_user_to_blog($blog_id,$id,$user['role']);
		}
	}
	return null;
}

function hostergo_check_password($check,$password,$hash,$user_id) {
	if (!$check) { //the user could be using his old HosterGo password
		$user=new WP_User($user_id);
		$http=zing_http("zhg","","login");
		//echo '<br />calling here:<br />'.$http.'<br />';
		$news = new hostergo_http_request($http,'hostergo');
		$news->post=array('name'=>$user->data->user_email,'pass'=>$password);
		$json=json_decode($news->DownloadToString(),true);
		$check=isset($ret['status']) ? $ret['status'] : false;
	}

	return $check;
}

function hostergo_profile_update($user_id) {
	global $whmcsID;
	$password=$_POST['pass1'];
	$user=new WP_User($user_id);
	$http=zing_http("zhg","","customerupdate");
	//echo '<br />calling here:<br />'.$http.'<br />';
	$news = new hostergo_http_request($http,'hostergo');
	$news->post=array('loginname'=>$user->data->ID,'email'=>$user->data->user_email,'firstname'=>$user->data->first_name,'lastname'=>$user->data->last_name,'pass'=>$password);
	$json=json_decode($news->DownloadToString(),true);
	$check=isset($ret['status']) ? $ret['status'] : false;
}
