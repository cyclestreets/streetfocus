<div id="pane">
	
	<div id="introduction">
		<h1 class="name">StreetFocus</h1>
		<h1>Helping local communities benefit from new developments</h1>
		<p>Find out what planning applications there currently are in your area and discover whether there is funding for neighbourhood projects.</p>
	</div>
	
	{include file='_partials/geocoder.tpl'}
	
	<div class="staticmap">
		<a href="/map/"><img src="/images/staticmap.png" alt="Map" /></a>
	</div>
	
	<div id="statistics">
		<div>
			<h3>Applications</h3>
			<p>{$totalApplications|number_format}</p>
		</div>
		<div>
			<h3>Matched ideas</h3>
			<p>{$matchedIdeas|number_format}</p>
		</div>
	</div>
	
</div>

<div class="staticmap">
	<a href="/map/"><img src="/images/staticmap.png" alt="Map" /></a>
</div>

