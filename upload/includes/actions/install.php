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