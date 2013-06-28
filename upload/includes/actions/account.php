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

##[[ ACCOUNT ACTION ]]##

if(!isset($_WEBSTORE)) throw new Exception('WebStore framework not loaded');
$_WEBSTORE->requireLogin();

$page_body = "<div class=\"content\">";
$page_body.= $_WEBSTORE->textEntry('uid','Account ID', $_WEBSTORE->user['uid'],'disabled');
$page_body.= $_WEBSTORE->textEntry('paid','Paid amount (in '.$_WEBSTORE->store->conf['site']['currency'].')', $_WEBSTORE->user['paid'],'disabled');
$page_body.= $_WEBSTORE->textEntry('role','Store permission', $_WEBSTORE->isAdmin() ? 'Administrator' : 'Customer','disabled');
$page_body.= "</div>";
$page_body.= "<div class=\"buttons\">";
$page_body.= $_WEBSTORE->buttonAction('logout','Log out','red');
$page_body.= $_WEBSTORE->buttonAction('active','Subscriptions','yellow');
$page_body.= $_WEBSTORE->buttonReturn();
$page_body.= "</div>";


$_WEBSTORE->registerVariable('pageicon','resource/icons/user.png');
$_WEBSTORE->registerVariable('pagetitle','Account page');
$_WEBSTORE->registerVariable('pagebody',$page_body);