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

##[[ MANAGE_SERVERS ACTION ]]##

if(!isset($_WEBSTORE)) return false;

if(!$_WEBSTORE->isAdmin()) $_WEBSTORE->redirectError('You do not have permission to access this page');

if(!empty($_GET['sid']) and $_GET['sid'] !='new'){
	$_GET['sid'] = trim(strtolower($_GET['sid']));
	if(!is_numeric($_GET['sid'])) $_WEBSTORE->redirectError('Invalid server ID');
	$query = $_STORE->selectServers(array(),array('sid'=>$_GET['sid']));
	if($query===false) $_WEBSTORE->redirectError('Database query error');
	$server = $query['0'];
}

if(!empty($_GET['pid']) and $_GET['pid'] !='new'){
	$_GET['pid'] = trim(strtolower($_GET['pid']));
	if(!is_numeric($_GET['pid'])) $_WEBSTORE->redirectError('Invalid package ID');
	$query = $_STORE->selectPackages(array(),array('pid'=>$_GET['pid']));
	if($query===false) $_WEBSTORE->redirectError('Database query error');
	$package = $query['0'];
}

$option = empty($_GET['option']) ? 'servers' : trim(strtolower($_GET['option']));
switch($option) {
	//API
	default:
		$_WEBSTORE->redirectAction($_GET['action']);
		break;
	case 'newkey':
		if(!isset($server)) $_WEBSTORE->redirectError('Invalid server selected');
		$apikey = md5(md5(rand().microtime()));
		$query = $_STORE->updateServers(array('apikey'=>$apikey),array('sid'=>$server['sid']));
		if($query===false) $_WEBSTORE->redirectError('Database query error');;

		$_WEBSTORE->redirectAction($_GET['action'],array('sid'=>$server['sid']));
		break;
	case 'modify':
		$rparams = array();
		if(isset($package)){
			if(empty($_GET['name']) or empty($_GET['price']) or empty($_GET['duration'])) $_WEBSTORE->redirectError('All availible fields must be filled');

			$_GET['conflicts'] = is_null($_GET['conflicts']) ? array() : $_GET['conflicts'];
			$_GET['name'] = trim($_GET['name']);
			$_GET['duration'] = trim(strtolower($_GET['duration']));

			if(!is_numeric($_GET['price'])) $_WEBSTORE->redirectError('Price should be in number format');
			if($_GET['price'] <= 0) $_WEBSTORE->redirectError('Price can not be equal or less than zero');
			if(is_numeric($_GET['duration']) and $_GET['duration'] < 0) $_WEBSTORE->redirectError('Duration can not be less than zero');
			if($_GET['duration'] != '0' and !strtotime($_GET['duration'])) $_WEBSTORE->redirectError('Duration format is invalid');

			$query = $_STORE->setConflicts($package['pid'],$_GET['conflicts']);
			if($query===false) $_WEBSTORE->redirectError('Database query error');
			$query = $_STORE->updatePackages(array('name'=>$_GET['name'],'price'=>$_GET['price'],'duration'=>$_GET['duration']),array('pid'=>$package['pid']));
			if($query===false) $_WEBSTORE->redirectError('Database query error');

			$rparams['option'] = 'packages';
			$rparams['sid'] = $server['sid'];
			$rparams['pid'] = $package['pid'];
		}elseif(isset($server)){
			if(empty($_GET['name']) or empty($_GET['address'])) $_WEBSTORE->redirectError('All availible fields must be filled');

			$_GET['name'] = trim($_GET['name']);
			$_GET['address'] = trim(strtolower($_GET['address']));

			if(!preg_match('/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/',$_GET['address'])) $_WEBSTORE->redirectError('Address is not a valid IP address');

			$query = $_STORE->updateServers(array('name'=>$_GET['name'],'address'=>$_GET['address']),array('sid'=>$server['sid']));
			if($query===false) $_WEBSTORE->redirectError('Database query error');

			$rparams['sid'] = $server['sid'];
		}else $_WEBSTORE->redirectError('Invalid entity selected');
		$query = $_STORE->cleanGarbage();
		if($query === false) $_WEBSTORE->redirectError("Garbage cleaning function failed");
		$_WEBSTORE->redirectAction($_GET['action'],$rparams);
		break;
	case 'create':
		$rparams = array();
		if(isset($_GET['pid'])){
			if(!isset($server)) $_WEBSTORE->redirectError('Package server not specified');
			if(!isset($_GET['name']) or !isset($_GET['price']) or !isset($_GET['duration'])) $_WEBSTORE->redirectError('All availible fields must be filled');

			$_GET['name'] = trim($_GET['name']);
			$_GET['duration'] = trim(strtolower($_GET['duration']));

			if(!is_numeric($_GET['price'])) $_WEBSTORE->redirectError('Price should be in number format');
			if($_GET['price'] <= 0) $_WEBSTORE->redirectError('Price can not be equal or less than zero');
			if(is_numeric($_GET['duration']) and $_GET['duration']<0) $_WEBSTORE->redirectError('Duration can not be less than zero');
			if($_GET['duration'] != '0' and !strtotime($_GET['duration'])) $_WEBSTORE->redirectError('Duration format is invalid');

			$query = $_STORE->insertPackage(array('sid'=>$server['sid'],'name'=>$_GET['name'],'price'=>$_GET['price'],'duration'=>$_GET['duration']));
			if($query===false) $_WEBSTORE->redirectError('Database query error');

			$rparams['option'] = 'packages';
			$rparams['sid'] = $server['sid'];
			$rparams['pid'] = $_STORE->db->lastInsertId('pid');
		}elseif(isset($_GET['sid'])){
			if(empty($_GET['name']) or empty($_GET['address'])) $_WEBSTORE->redirectError('All availible fields must be filled');

			$_GET['name'] = trim($_GET['name']);
			$_GET['address'] = trim(strtolower($_GET['address']));

			if(!preg_match('/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/',$_GET['address'])) $_WEBSTORE->redirectError('Address is not a valid IP address');

			$query = $_STORE->insertServer(array('name'=>$_GET['name'],'address'=>$_GET['address']));
			if($query===false) $_WEBSTORE->redirectError('Database query error');

			$rparams['sid'] = $_STORE->db->lastInsertId('sid');
		}else $_WEBSTORE->redirectAction($_GET['action']);

		$query = $_STORE->cleanGarbage();
		if($query === false) $_WEBSTORE->redirectError("Garbage cleaning function failed");
		$_WEBSTORE->redirectAction($_GET['action'],$rparams);
		break;
	case 'remove':
		$rparams = array();
		if(isset($_GET['pid'])){
			if(!isset($package)) $_WEBSTORE->redirectError('Invalid package selected');

			$query = $_STORE->deletePackages(array('pid'=>$package['pid']));
			if($query===false) return false;

			$rparams['option'] = 'packages';
			$rparams['sid'] = $server['sid'];
		}elseif(isset($_GET['sid'])){
			if(!isset($server)) $_WEBSTORE->redirectError('Invalid server selected');
			$query = $_STORE->deleteServers(array('sid'=>$server['sid']));
			$query = $_STORE->deletePackages(array('sid'=>$server['sid'])); //This is better than cleanGarbage()
			if($query===false) return false;
		}

		$query = $_STORE->cleanGarbage();
		if($query === false) $_WEBSTORE->redirectError("Garbage cleaning function failed");
		$_WEBSTORE->redirectAction($_GET['action'],$rparams);
		break;

	//Menus
	case 'servers':
		$page_body = "";
		if(isset($_GET['sid']) and $_GET['sid']=='new'){
			$page_body.="<div class=\"content\">";
			$page_body.= $_WEBSTORE->select('sid','Select an existing server from the list or create a new one.',array('* Create new...'),null,'disabled');
			$page_body.="</div>";
			$page_body.=$_WEBSTORE->title('Create server','resource/icons/server_add.png');
			$page_body.="<div class=\"content\">";
			$page_body.=$_WEBSTORE->textEntry("name","Server Name");
			$page_body.=$_WEBSTORE->textEntry("address","Server Address");
			$page_body.="</div>";
			$page_body.="<div class=\"buttons\">";
			$page_body.=$_WEBSTORE->buttonSubmit($_GET['action'],'Create','green',array('option'=>'create','sid'=>'new'));
			$page_body.=$_WEBSTORE->buttonReturn($_GET['action'],'Cancel');
			$page_body.="</div>";
		}elseif(isset($server)){
			$server['name'] = trim(htmlentities($server['name']),ENT_QUOTES);
			$page_body.="<div class=\"content\">";
			$page_body.= $_WEBSTORE->select('sid','Select an existing server from the list or create a new one.',array($server['sid']=>$server['name']),null,'disabled');
			$page_body.="</div>";
			$page_body.=$_WEBSTORE->title("Edit server - {$server['name']}",'resource/icons/server_edit.png');
			$page_body.="<div class=\"content\">";
			$page_body.=$_WEBSTORE->textEntry("sid","Server ID",$server['sid'],'disabled');
			$page_body.=$_WEBSTORE->textEntry("name","Server Name",$server['name']);
			$page_body.=$_WEBSTORE->textEntry("address","Server Address",$server['address']);
			$page_body.=$_WEBSTORE->textEntry("apikey","Server Key",$server['apikey'],"disabled style=\"font-family: 'Courier New', Courier, monospace\"");
			$page_body.="</div>";
			$page_body.="<div class=\"buttons\">";
			$page_body.=$_WEBSTORE->buttonSubmit($_GET['action'],'Save','green',array('option'=>'modify','sid'=>$server['sid']));
			$page_body.=$_WEBSTORE->buttonAction($_GET['action'],'Packages','yellow',array('option'=>'packages','sid'=>$server['sid']));
			$page_body.=$_WEBSTORE->buttonAction($_GET['action'],'New key','yellow',array('option'=>'newkey','sid'=>$server['sid']));
			$page_body.=$_WEBSTORE->buttonAction($_GET['action'],'Remove','red',array('option'=>'remove','sid'=>$server['sid']));
			$page_body.=$_WEBSTORE->buttonReturn($_GET['action'],'Cancel');
			$page_body.="</div>";
		}else{
			$query = $_STORE->selectServers(array('name','sid'));
			if($query===false) $_WEBSTORE->redirectError('Database query error');
			$query[] = array('sid'=>'new','name'=>'* Create new...');
			$servers = array();
			foreach($query as $srv){$servers[$srv['sid']] = $srv['name'];}
			$page_body.="<div class=\"content\">";
			$page_body.= $_WEBSTORE->select('sid','Select an existing server from the list or create a new one.',$servers);
			$page_body.="</div>";
			$page_body.="<div class=\"buttons\">";
			$page_body.=$_WEBSTORE->buttonSubmit($_GET['action'],'Manage server');
			$page_body.=$_WEBSTORE->buttonReturn();
			$page_body.="</div>";
		}
		$_WEBSTORE->registerVariable('pageicon','resource/icons/server.png');
		$_WEBSTORE->registerVariable('pagetitle','Manage servers');
		$_WEBSTORE->registerVariable('pagebody',$page_body);
		break;
	case 'packages':
		if(!isset($server)) $_WEBSTORE->redirectAction($_GET['action']);
		$server['name'] = trim(htmlentities($server['name']),ENT_QUOTES);
		$page_body = "";
		if(isset($_GET['pid']) and $_GET['pid']=='new'){
			$page_body.="<div class=\"content\">";
			$page_body.= $_WEBSTORE->select('pid','Select an existing package from the list or create a new one.',array('* Create new...'),null,'disabled');
			$page_body.="</div>";
			$page_body.=$_WEBSTORE->title('Create package','resource/icons/plugin_add.png');
			$page_body.="<div class=\"content\">";
			$page_body.=$_WEBSTORE->textEntry("name","Package Name");
			$page_body.=$_WEBSTORE->textEntry("price","Package Price (in {$_CONFIG['site']['currency']})");
			$page_body.=$_WEBSTORE->textEntry("duration","Package Duration");
			$page_body.="</div>";
			$page_body.="<div class=\"buttons\">";
			$page_body.=$_WEBSTORE->buttonSubmit($_GET['action'],'Create','green',array('option'=>'create','pid'=>'new','sid'=>$server['sid']));
			$page_body.=$_WEBSTORE->buttonReturn($_GET['action'],'Cancel','grey',array('option'=>'packages','sid'=>$server['sid']));
			$page_body.="</div>";
		}elseif(isset($package)){
			$conflicts = $_STORE->convertConflicts($package['conflicts']);
			$query = $_STORE->selectPackages(array('pid','name'));
			if($query===false) $_WEBSTORE->redirectError('Database query error');
			$packages = array();
			foreach($query as $pkg){$packages[$pkg['pid']] = $pkg['name'];}
			$page_body.="<div class=\"content\">";
			$page_body.= $_WEBSTORE->select('pid','Select an existing package from the list or create a new one.',array($package['pid']=>$package['name']),null,'disabled');
			$page_body.="</div>";
			$page_body.=$_WEBSTORE->title("Edit package - {$package['name']}",'resource/icons/plugin_edit.png');
			$page_body.="<div class=\"content\">";
			$page_body.=$_WEBSTORE->textEntry("pid","Package ID",$package['pid'],'disabled');
			$page_body.=$_WEBSTORE->textEntry("name","Package Name",$package['name']);
			$page_body.=$_WEBSTORE->textEntry("price","Package Price (in {$_CONFIG['site']['currency']})",$package['price']);
			$page_body.=$_WEBSTORE->textEntry("duration","Package Duration",$package['duration']);
			$page_body.=$_WEBSTORE->select("conflicts[]","Conflicts",$packages,$conflicts,"multiple id=\"conflicts\"");
			$page_body.="<script type=\"text/javascript\">function selectMultiple(){var elem = document.getElementById('conflicts');for (var i=0; i<elem.options.length; i++){elem.options[i].selected = false;}}</script>";
			$page_body.=$_WEBSTORE->buttonLink("javascript:selectMultiple()","Deselect all","grey",null);
			$page_body.="</div>";
			$page_body.="<div class=\"buttons\">";
			$page_body.=$_WEBSTORE->buttonSubmit($_GET['action'],'Save','green',array('option'=>'modify','sid'=>$server['sid'],'pid'=>$package['pid']));
			$page_body.=$_WEBSTORE->buttonAction($_GET['action'],'Remove','red',array('option'=>'remove','sid'=>$server['sid'],'pid'=>$package['pid']));
			$page_body.=$_WEBSTORE->buttonReturn($_GET['action'],'Cancel','grey',array('option'=>'packages','sid'=>$server['sid']));
			$page_body.="</div>";
		}else{
			$query = $_STORE->selectPackages(array('name','pid'));
			if($query===false) $_WEBSTORE->redirectError('Database query error');
			$query[] = array('pid'=>'new','name'=>'* Create new...');
			$packages = array();
			foreach($query as $pkg){$packages[$pkg['pid']] = $pkg['name'];}
			$page_body.="<div class=\"content\">";
			$page_body.= $_WEBSTORE->select('pid','Select an existing package from the list or create a new one.',$packages);
			$page_body.="</div>";
			$page_body.="<div class=\"buttons\">";
			$page_body.=$_WEBSTORE->buttonSubmit($_GET['action'],'Manage package','green',array('option'=>'packages','sid'=>$server['sid']));
			$page_body.=$_WEBSTORE->buttonReturn($_GET['action'],'Back to server','grey',array('sid'=>$server['sid']));
			$page_body.="</div>";
		}
		$_WEBSTORE->registerVariable('pageicon','resource/icons/plugin.png');
		$_WEBSTORE->registerVariable('pagetitle','Manage packages - '.$server['name']);
		$_WEBSTORE->registerVariable('pagebody',$page_body);
		break;
	case 'purchases':
		break;
}