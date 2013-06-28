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

##[[ ACTIVE ACTION ]]##

if(!isset($_WEBSTORE)) throw new Exception('WebStore framework not loaded');
$_WEBSTORE->requireLogin();

$filter = array();
$all = (isset($_GET['all']) and $_GET['all']) ? true : false;

if(!$_WEBSTORE->isAdmin() or !$all){$filter['uid'] = $_WEBSTORE->user['uid'];}

if($_WEBSTORE->isAdmin() and isset($_GET['remove']) and is_array($_GET['remove'])){
	foreach($_GET['remove'] as $uid=>$pid){
		$query = $_STORE->updateActive(array('permanent'=>'0','expire'=>date('Y-m-h',strtotime('-1 month'))),array('uid'=>$uid,'pid'=>$pid));
		if($query===false) $_WEBSTORE->redirectError('Database query error');
	}
	$query = $_STORE->cleanGarbage();
	if($query===false) $_WEBSTORE->redirectError('Database query error');
	$_WEBSTORE->redirectAction($_GET['action'],array('all'=>$all));
}

$query = $_STORE->selectActive(array(),$filter);
if($query===false) $_WEBSTORE->redrectError('Database query error');
$page_body = "<div class=\"content\">";
if(count($query)===0){
	$page_body.= "<b>No active subscriptions yet</b>";
}else{
	$page_body.= "<table class=\"active\">";
	$page_body.= "
		<tr>
			<th id=\"pid\">Package</th>
			<th id=\"uid\">User</th>
			<th id=\"sid\">Server</th>
			<th id=\"expire\">Expire</th>
			<th id=\"remove\"></th>
		</tr>";
	foreach($query as $active){
		$query = $_STORE->selectPackages(array('name'),array('pid'=>$active['pid']));
		if($query===false) $_WEBSTORE->redirectError('Database query error');
		$package = $query['0'];
		$query = $_STORE->selectServers(array('name'),array('sid'=>$active['sid']));
		if($query===false) $_WEBSTORE->redirectError('Database query error');
		$server = $query['0'];
		$page_body.= "
		<tr>
			<td>{$package['name']}</td>
			<td>{$active['uid']}</td>
			<td>{$server['name']}</td>
			<td>".($active['permanent']?'Permanent':$active['expire'])."</td>
			<td id=\"remove\"><input type=\"checkbox\" name=\"remove[{$active['uid']}][]\" value=\"{$active['pid']}\"></td>
		</tr>";
		//$page_body.= implode("</td>\n<td>",$active);
		//$page_body.= "</td>\n<a href=\"".$_WEBSTORE->linkStore($_GET['action'],array('remove[pid]'=>$active['pid'],'remove[uid]'=>$active['uid']))."\"><td id=\"remove\"></td></a></tr>";
	}
	$page_body.="</table>";
}

$page_body.= "</div>";
$page_body.= "<div class=\"buttons\">";
if($_WEBSTORE->isAdmin()) {
	if($all) $page_body.= $_WEBSTORE->buttonAction($_GET['action'],'Show yours','yellow',array('all'=>false));
	else $page_body.= $_WEBSTORE->buttonAction($_GET['action'],'Show all','yellow',array('all'=>true));
	$page_body.= $_WEBSTORE->buttonSubmit($_GET['action'],'Delete checked','red',array('all'=>$all));
}
$page_body.= $_WEBSTORE->buttonReturn();
$page_body.= "</div>";


$_WEBSTORE->registerVariable('pageicon','resource/icons/calendar.png');
$_WEBSTORE->registerVariable('pagetitle','Active subscriptions');
$_WEBSTORE->registerVariable('pagebody',$page_body);