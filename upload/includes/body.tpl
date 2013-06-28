<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>{$title}</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" href="resource/style.css" type="text/css" />
	<link rel="stylesheet" href="resource/buttons.css" type="text/css" />
	<link rel="stylesheet" href="resource/contextmenu.css" type="text/css"/>
</head>
<body>
	<form class="box" method="get">
		<div class="title"><img src="{$pageicon}" alt=""/>{$pagetitle}<a id="menu" onclick="return false;">&#x25BC;Menu</a></div>
		{$pagebody}
	</form>
	<script src="resource/contextmenu.js"></script>
	<script type="text/javascript">
		var menu = contextmenu([{$menuelements}]);
		contextmenu.attach(document.getElementById('menu'), menu);
		document.getElementById('menu').onclick = function() {
			contextmenu.show(menu, this);
			return false;
		}
	</script>
</body>
</html>