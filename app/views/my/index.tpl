
<h1>Monitor areas</h1>

<p>In this section, you can monitor areas, by creating notifications for when new planning applications come up.</p>

<h2>Add an area to monitor</h2>

<p class="action"><a href="/my/add/" class="button"><strong>+</strong>&nbsp; Add an area to monitor</a></p>

<h2>My monitors</h2>

{if (!$user)}
	<p>Please <a href="/login/?/my/">log in</a> to view areas (if any) that you are monitoring.</p>
{else}
	<div id="map"></div>
{/if}

