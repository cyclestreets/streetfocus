<html>
	
	<head>
		
		<title>StreetFocus - Helping local communities benefit from new developments</title>

		<link href="https://fonts.googleapis.com/css?family=Fjalla+One|PT+Sans&amp;display=swap" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="/css/streetfocus.css">
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
		<script src="/js/lib/jquery-ui-1.12.1/jquery-ui.min.js"></script>
		<script src="/js/lib/jQuery-Touch-Events/src/jquery.mobile-events.min.js"></script>
		
		<script src="https://api.tiles.mapbox.com/mapbox-gl-js/v1.6.1/mapbox-gl.js"></script>
		<link href="https://api.tiles.mapbox.com/mapbox-gl-js/v1.6.1/mapbox-gl.css" rel="stylesheet" />
		
		<script type="text/javascript" src="/js/lib/geocoder.js"></script>
		
		<script src="/js/streetfocus.js"></script>
		<script>
			$(function() {
				config = {
					planitApiBaseUrl: '{$_settings.planitApiBaseUrl}',
					cyclestreetsApiKey: '{$_settings.cyclestreetsApiKey}',
					mapboxApiKey: '{$_settings.mapboxApiKey}'
				};
				streetfocus.initialise (config, '{$_action}');
			});
		</script>
		
	</head>
	
	<body class="{$_action}">
		
		<header>
			
			<p><a href="/"><img src="/images/logo.png" id="logo" alt="StreetFocus" /></a></p>
			
			<nav>
				<img src="/images/hamburger.png" />
				<ul>
					<li class="{($_action == 'home') ? 'selected ' : ''}mobile"><a href="/">Home</a></li>
					<li{($_action == 'map') ? ' class="selected"' : ''}><a href="/map/">Planning applications</a></li>
					<li{($_action == 'proposals') ? ' class="selected"' : ''}><a href="/proposals/">Proposals</a></li>
					<li{($_action == 'my') ? ' class="selected"' : ''}><a href="/my/">Monitor areas</a></li>
					<li{($_action == 'blog') ? ' class="selected"' : ''}><a href="/blog/">Blog</a></li>
					<li{($_action == 'about') ? ' class="selected"' : ''}><a href="/about/">About</a></li>
					<li class="{($_action == 'contacts') ? 'selected ' : ''}mobile"><a href="/contacts/">Contact us</a></li>
				</ul>
			</nav>
			
		</header>
		
		<main>
		
		
