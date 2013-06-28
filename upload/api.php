<?php
/*--------------------------------
 XDS Version Alpha
 Xavux Software LLC.
 Do not redistribute without the
 author's permission.
 ---------------------------------
 Main file
 --------------------------------*/

define('STORE_ROOT_PATH',dirname(__FILE__));
define('STORE_INCLUDES_PATH',STORE_ROOT_PATH . '/includes');

require STORE_INCLUDES_PATH . '/class.core.php';
require STORE_INCLUDES_PATH . '/config.inc.php';

$_STORE = new Store();

error_reporting(E_ALL);

try{
	$valid = $_STORE->PayPal->processIpn();
	
	if($valid){
		$query = $_STORE->selectPackages(array('pid'),array('pid'=>$_POST['item_number'],'price'=>$_POST['mc_gross']));
		if($query===false or !count($query)) return false;
		$query = $_STORE->purchasePackage($_POST['custom'],$_POST['item_number'],$_POST['mc_gross'],$_POST);
		$_STORE->PayPal->logReport('Purchase status: '.($query ? 'succesful' : 'failed'));
		exit;
	}
}catch(Exception $e){}


$_API = new StoreAPI($_STORE);
$_API->sendHeaders();
if(!isset($_GET['action'],$_GET['apikey'])) die($_API->error('invalid_parameters'));

$_GET['input'] = isset($_GET['input']) ? $_GET['input'] : null;
$output = $_API->callApi($_GET['action'],$_GET['apikey'],$_GET['input']);
if(empty($output)) die($_API->error('empty_response'));
die($output);