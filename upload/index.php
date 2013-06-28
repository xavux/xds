<?php
/*/================================================\*\
|*|                                                |*|
|*|      Xavux Dedicated Store - Version 2.0.0     |*|
|*|                                                |*|
|*| This software is provided to you by Xavux LLC. |*|
|*| Please, refer to the license file that came    |*|
|*| along with your distribution of XDS for more   |*|
|*| information about the software's usage terms.  |*|
\*\================================================/*/
//die("Yeah, ask Hans to show what's inside :3"); //COMMENT THIS!
##[[ INITIALIZATION BLOCK ]]##

define('STORE_ROOT_PATH',dirname(__FILE__));
define('STORE_INCLUDES_PATH',STORE_ROOT_PATH . '/includes');

require STORE_INCLUDES_PATH . '/class.core.php';
require STORE_INCLUDES_PATH . '/class.webstore.php';
require STORE_INCLUDES_PATH . '/config.inc.php';

$_WEBSTORE = new WebStore($_STORE = new Store());

$_GET = isset($_GET) ? $_GET : array();

##[[ PERMISSION CHECK BLOCK ]]##

$allowedActions = array('home','error','login','logout','packages');
$_GET['action'] = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : 'home';

##[[ MENU BUILDING BLOCK ]]##

$menuelements = array();
$menuelements[] = "{label: 'Home Page', href: \"".$_WEBSTORE->linkStore('home')."\"}";
$menuelements[] = "{label: 'Buy packages', href: \"".$_WEBSTORE->linkStore('packages')."\"}";
if($_WEBSTORE->user){
	$menuelements[] = "{hr: true}";
	if($_WEBSTORE->isAdmin()){
		$managebuttons = array();
		$managebuttons[] = "{label: 'Modify servers/packages',href: \"".$_WEBSTORE->linkStore('manage')."\"}";
		$managebuttons[] = "{label: 'View all subscriptions',href: \"".$_WEBSTORE->linkStore('active',array('all'=>true))."\"}";

		$menuelements[] = "{label: 'Manage store',children: [".implode(",",$managebuttons)."]}";
	}
	$menuelements[] = "{label: 'View account',href: \"".$_WEBSTORE->linkStore('account')."\"}";
	$menuelements[] = "{label: 'View your subscriptions',href: \"".$_WEBSTORE->linkStore('active')."\"}";
	$menuelements[] = "{label: 'Log out',href: \"".$_WEBSTORE->linkStore('logout')."\"}";
}elseif(!in_array($_GET['action'],$allowedActions)){
	$_WEBSTORE->requireLogin();
}else{
	$menuelements[] = "{hr: true}";
	$menuelements[] = "{label: 'Login',href: \"".$_WEBSTORE->linkStore('login')."\"}";
}

##[[ TEMPLATE INITIALIZATION BLOCK ]]##

$_WEBSTORE->loadTemplate('page',STORE_INCLUDES_PATH . '/body.tpl');
$_WEBSTORE->registerVariable('title',$_CONFIG['site']['title']);
$_WEBSTORE->registerVariable('pageicon','resource/icons/page.png');
$_WEBSTORE->registerVariable('pagetitle','[No title]');
$_WEBSTORE->registerVariable('pagebody','<div class="content"><p>[No body]</p></div>');

##[[ ACTION PROCESSING BLOCK ]]##

switch($_GET['action']) {
	case 'home':
		$page_body = "
			<div class=\"content\">
				<img src=\"resource/logo.png\" alt=\"\" class=\"logo\"/>
				<p>This store requires you to log in through Steam service prior to making any purchases.</p>
				<p>You will be redirected to the page you attempted to request after logging in.</p>
			</div>
			<div class=\"buttons\">
				" . $_WEBSTORE->buttonSubmit('packages', 'Proceed to package selection') . "
			</div>";
		$_WEBSTORE->registerVariable('pageicon','resource/icons/key.png');
		$_WEBSTORE->registerVariable('pagetitle','Welcome to ' . $_CONFIG['site']['title'] . '!');
		$_WEBSTORE->registerVariable('pagebody',$page_body);
		break;
	case 'logout':
		if(empty($_SESSION['store_uid'])) $_WEBSTORE->redirectAction('home');
		session_destroy();
		$page_body = "
			<div class=\"content\">
				<p>Logout succesful!</p>
				<p>You can now return to the home page of the store, or continue browsing the internet. Thank you for using {$_CONFIG['site']['title']}!</p>
			</div>
			<div class=\"buttons\">
				" . $_WEBSTORE->buttonReturn() . "
			</div>";
		$_WEBSTORE->registerVariable('pageicon','resource/icons/user_go.png');
		$_WEBSTORE->registerVariable('pagetitle','Logout');
		$_WEBSTORE->registerVariable('pagebody',$page_body);
		break;
	case 'login':
		if(!empty($_SESSION['store_uid'])){
			$_WEBSTORE->redirectAction('home');
		}else{
			$uid = $_STORE->checkLogin();
			if($uid!==false and is_numeric($uid)){
				$_SESSION['store_uid'] = $uid;
				$query = $_STORE->selectUsers(array("uid"),array("uid"=>$uid),1);
				if($query==false or !count($query)) $_STORE->insertUser(array('uid'=>$uid));
				if(!empty($_SESSION['store_goto'])){
					$goto = $_SESSION['store_goto'];
					unset($_SESSION['store_goto']);
					$_WEBSTORE->redirectLink($goto);
				}else{
					$_WEBSTORE->redirectAction('home');
				}
				exit;
			}
		}
		$_WEBSTORE->registerVariable('pageicon','resource/icons/key.png');
		$_WEBSTORE->registerVariable('pagetitle','Login');
		$_WEBSTORE->registerVariable('pagebody',"
			<div class=\"content\">
				<center><img src=\"resource/steam.png\" alt=\"Steam\" height=\"48\"/></center>
				<p>This store requires you to log in through Steam service prior to making any purchases.</p>
				<p>You will be redirected to the page you attempted to request after logging in.</p>
			</div>
			<div class=\"buttons\">
				" . $_WEBSTORE->buttonLink($_STORE->generateLoginUrl($_WEBSTORE->linkStore('login')),'Login via Steam','green') . "
				" . $_WEBSTORE->buttonBack() . "
			</div>
			");
		break;
	case 'error':
		if(!isset($_SESSION['error'])) $_SESSION['error'] = 'Unknown error';
		$error = htmlentities($_SESSION['error']);
		unset($_SESSION['error']);
		$_WEBSTORE->registerVariable('pageicon','resource/icons/error.png');
		$_WEBSTORE->registerVariable('pagetitle','Error');
		$_WEBSTORE->registerVariable('pagebody',"
			<div class=\"content\">
				<p><b>{$error}</b></p>
				<p>We are very sorry that this error has been encountered during your browsing experience.
				Make an attempt at returning to the previous page, and if that does not resolve the issue, contact the store owner.</p>
			</div>
			<div class=\"buttons\">
				" . $_WEBSTORE->buttonBack() . "
			</div>
			");
		break;
	default:
		if(!$_WEBSTORE->loadAction($_GET['action'])) $_WEBSTORE->redirectError("Page '{$_GET['action']}' not found");
		break;
}

$menuelements[] = "{hr: true}";
$menuelements[] = "{label: 'Contact store owner'}";
$menuelements[] = "{label: 'About this software'}";
$_WEBSTORE->registerVariable('menuelements',implode(",",$menuelements));

$_WEBSTORE->parseTemplate('page');
$_WEBSTORE->printTemplate('page');