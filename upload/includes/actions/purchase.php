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

if(!$_WEBSTORE->user['uid']) $_WEBSTORE->redirectAction('Invalid package selected');

if(empty($_GET['pid'])) $_WEBSTORE->redirectError('Invalid package selected');
$query = $_STORE->selectPackages(array(),array('pid'=>$_GET['pid']),1);
if($query===false) $_WEBSTORE->redirectError('Database query error');
if(!count($query)) $_WEBSTORE->redirectError('Invalid package selected');
$package = $query['0'];

$all_conflicts = $_STORE->findConflicts($_WEBSTORE->user['uid'],$package['pid']);
if($all_conflicts===false) $_WEBSTORE->redirectError('Database query error');

if($all_conflicts !== true and empty($_GET['skipwarning'])){
	$conflicts = array();
	$impossible = false;
	foreach($all_conflicts as $k=>$conflict){
		if($k!=='with'){
			$conflicts[] = $conflict['pid'];
			if($conflict['permanent']=='1') $impossible = true;
		}
	}
	$query = $_STORE->selectPackages(array('name','duration'),array('pid'=>$conflicts));
	if($query===false) $_WEBSTORE->redirectError('Database query error');
	$conflicts_display = "";
	foreach($query as $conflict){
		$conflicts_display.= "<li><b>{$conflict['name']}</b></li>";
	}
	$_WEBSTORE->registerVariable('pagetitle','Purchase conflict');
	$_WEBSTORE->registerVariable('pagebody',"
		<div class=\"content\">
			<p>We are sorry to anounce, but the package you have decided to purchase (<i>{$package['name']}</i>) has conflicts with some of the packages you have already purchased!</p>
			<p>Below is a list of packages that currently conflict with your selection:</p>
			<ul>{$conflicts_display}</ul>
		".(($impossible) ?
			"<p>Unfortunately, it is impossible to override these packages, because at least one of them is a one-time purchase that conflicts with the selected package.
			Please, contact the store owner to resolve this issue.</p></div>" :
			"<p>Despite the fact that these exist, you have an option of <i>overriding</i> them. Warning: all of the purchases above will be lost, and no refund will be given!<p>
		</div>")."
		<div class=\"buttons\">
			".((!$impossible)?$_WEBSTORE->buttonAction($_GET['action'],'Purchase package','red',array('pid'=>$package['pid'],'skipwarning'=>'1')):"")."
			".$_WEBSTORE->buttonReturn('packages','Return to package list')."
		</div>
		");
	return true;
}

//Submit data using core
$_STORE->submitPurchase($_WEBSTORE->user['uid'],$package['pid']);