
<h1>Monitor an area</h1>

{if isset ($error)}
	<p>{$error|escape}</p>
{else}


{if isset ($result)}
	{if ($result)}
		<p>✓ - Your new monitor has been created. We will let you know when new planning applications appear in that area.</p>
		<p>Your new monitor is now shown on your <a href="/my/">My monitors</a> page.</p>
		<p><span class="warning">Beta note: E-mails are not yet going out, but will be shortly.</span></p>
	{else}
		<p>Apologies - there was a problem saving this monitor. Please try again later.</p>
	{/if}
{else}
	


<form method="post" action="" name="form">
	
	<p>This page will create notifications for when new planning applications come up in the area you specify.</p>
	
	<h2>1. Set map location</h2>
	<p>Move the map to the area that you want to get notifications for:</p>
	<div id="map"></div>
	<input type="hidden" name="bbox" id="bbox" />
	
	<h2>2. Add optional filters</h2>
	<p>If you wish, you can also limit notifications to planning applications in this to match particular criteria:</p>
	<div id="filtering">
		{include file='_partials/filtering.tpl'}
	</div>
	
	<h2>3. Save</h2>
	<p>We will send alerts, to your e-mail address, <strong>{$email}</strong>, no more than once a day.</p>
	<p>We will also save this to your monitors page.</p>
	<p><input type="submit" value="Monitor this area!" class="button" /></p>
	
</form>


{/if}
{/if}
