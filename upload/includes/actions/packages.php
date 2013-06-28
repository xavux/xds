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

##[[ INDEX ACTION ]]##

if(!isset($_WEBSTORE)) throw new Exception('WebStore framework not loaded');
$servers = $_STORE->selectServers();
$packages = $_STORE->selectPackages();
$exist = false;
if($servers!==false and $packages!==false){
	if(!count($servers)){$select_code = "<p><b>No servers have been added yet</b></p>";}
	elseif(!count($packages)){$select_code = "<p><b>No packages have been added yet</b></p>";}
	else {
		$exist = true;
		$list = array();
		foreach($servers as $server){
			$server['packages'] = array();
			$list[$server['sid']] = $server;
		}
		foreach($packages as $package){
			if(isset($list[$package['sid']])){
				$list[$package['sid']]['packages'][$package['pid']] = $package;
			}
		}
		$select_code = "<select name=\"pid\">";
		foreach($list as $sid=>$server){
			$select_code.= "<optgroup label=\"".htmlspecialchars($server['name'] . ' - ' . $server['address'],ENT_QUOTES)."\">";
			foreach($server['packages'] as $pid=>$package){$select_code.= "<option value=\"{$pid}\">#{$package['pid']}: ".htmlspecialchars($package['name'])." - ".number_format($package['price'],2)." ".$_CONFIG['site']['currency']." - ".($package['duration']==='0' ? 'Permanent' : "for ".$package['duration'])."</option>";}
			$select_code.= "</optgroup>";
		}
		$select_code.= "</select>";
	}
}else{
	$_STORE->redirectError('Database query error');
}
$_WEBSTORE->registerVariable('pageicon','resource/icons/cart_go.png');
$_WEBSTORE->registerVariable('pagetitle','Select package');
$_WEBSTORE->registerVariable('pagebody',"
	<div class=\"content\">
		<p>Select one of the availible packages from the list and then press 'Continue' to accomplish your purchase!</p>
		<p>After confirming package selection you will be redirected to a PayPal payment processing invoice page.
		Please, prepare your PayPal credentials or a bank card to accomplish the payment.</p>
		{$select_code}
	</div>
	<div class=\"buttons\">
		" . (($exist) ? $_WEBSTORE->buttonSubmit('purchase','Purchase','green',array()) : ""). "
		" . $_WEBSTORE->buttonReturn() . "
	</div>
	");