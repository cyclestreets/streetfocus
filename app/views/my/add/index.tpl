
<h1>Monitor an area</h1>

{if isSet ($error)}
	<p>{$error|htmlspecialchars}</p>
{else}


{if isSet ($outcome)}
	<p>{$outcome|htmlspecialchars}</p>
{else}
	


<form method="post" action="" name="form">
	
	<p>This page will create notifications for when new planning applications come up in the area you specify.</p>
	
	<h2>1. Set map location</h2>
	<p>Move the map to the area that you want to get notifications for:</p>
	<div id="map"></div>
	<input type="hidden" name="bbox" id="bbox" value="{$bbox}" />
	
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
