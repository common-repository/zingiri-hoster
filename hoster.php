<?php
/*
 Plugin Name: Hoster
 Plugin URI: http://www.zingiri.com
 Description: This plugin provides a simple client management & billing solution for hosters.
 Author: Zingiri
 Version: 1.2.1
 Author URI: http://www.zingiri.com/
 */

define("ZING_VERSION","1.2.1");
define("ZING_APPS",dirname(__FILE__)."/apps/fields/");
define("ZING_APPS_EMBED","zap/");
require(dirname(__FILE__)."/zing.inc.php");
require(dirname(__FILE__)."/zing.search.inc.php");
require(dirname(__FILE__)."/zing.apps.inc.php");

register_activation_hook(__FILE__,'zing_activate');
register_deactivation_hook(__FILE__,'zing_deactivate');

?>