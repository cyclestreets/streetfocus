<!DOCTYPE html>

<html lang="en">
	
	<head>
		
		<title>{$_title}</title>

		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		
		<link href="https://fonts.googleapis.com/css?family=Fjalla+One%7CPT+Sans&amp;display=swap" rel="stylesheet">
		<link rel="stylesheet" type="text/css" href="/css/streetfocus.css">
		
		<script src="/js/lib/jquery/dist/jquery.min.js"></script>
		<script src="/js/lib/jquery-ui-dist/jquery-ui.min.js"></script>
		<link href="/js/lib/jquery-ui-dist/jquery-ui.min.css" rel="stylesheet" />
		<script src="/js/lib/@benmajor/jquery-touch-events/src/jquery.mobile-events.min.js"></script>
		
		<script src="/js/lib/mapbox-gl/dist/mapbox-gl.js"></script>
		<link href="/js/lib/mapbox-gl/dist/mapbox-gl.css" rel="stylesheet" />
		
		<script src="/src/geocoder.js"></script>
		
		<script src="/src/streetfocus.js"></script>
		<script>
			{$_applicationJs}
		</script>
		
	</head>
	
	<body class="{$_action}">
		
		<header>
			
			<p><a href="/"><img src="/images/logo.png" id="logo" alt="StreetFocus" /> <img src="/images/beta.svg" width="45" height="45" id="beta" title="Public beta - some features are not yet live" /></a></p>
			
			<nav>
				<img src="/images/hamburger.png" alt="Menu" />
				<ul>
					<li class="{($_action == 'home') ? 'selected ' : ''}mobile"><a href="/">Home</a></li>
					<li{($_action == 'planningapplications') ? ' class="selected"' : ''}><a href="/map/">Planning applications</a></li>
					<li{($_action == 'ideas' || $_action == 'addidea') ? ' class="selected"' : ''}><a href="/ideas/">Ideas</a></li>
					<li{($_action == 'my' || $_action == 'addmonitor') ? ' class="selected"' : ''}><a href="/my/">Planning alerts</a></li>
					<li{($_action == 'blog') ? ' class="selected"' : ''}><a href="/blog/">Blog</a></li>
					<li{($_action == 'about') ? ' class="selected"' : ''}><a href="/about/">About</a></li>
					<li class="{($_action == 'contacts') ? 'selected ' : ''}mobile"><a href="/contacts/">Contact us</a></li>
					<li class="{($_action == 'login') ? 'selected ' : ''}login"><a href="/login/{$_returnToUrl|escape}">{if ($_user)}My account{else}Log in{/if}</a></li>
				</ul>
			</nav>
			
		</header>
		
		<main>
		
		
