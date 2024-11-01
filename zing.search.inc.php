<?php 
/*
 * Based on plugin Addicted To Live Search by John Nunemaker 
 * http://addictedtonew.com/archives/145/wordpress-live-search-plugin/
 */
 ?>
<?php
function zing_addicted_add() {
	$http=zing_http("livesearch","ajax/search.php","","");
	echo '<script type="text/javascript">';
	echo 'AddictedToLiveSearch.url = "'.$http.'"';
	echo '</script>';
}

if ($pagenow!='widgets.php') add_action('wp_footer', 'zing_addicted_add');

function zing_addicted_search_rewrite($wp_rewrite) {
	$rules = array(ZING_URL.'ajax/search_results.php' => '/');
	$wp_rewrite->rules = $rules + $wp_rewrite->rules;
}

if ($pagenow!='widgets.php') add_filter('generate_rewrite_rules', 'zing_addicted_search_rewrite');

?>