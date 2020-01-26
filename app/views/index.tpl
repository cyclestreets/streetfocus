<div id="introduction">
	<h1 class="name">StreetFocus</h1>
	<h1>Helping local communities benefit from new development</h1>
	<p>Find out what planning applications there currently are in your area and discover whether there is funding for neighbourhood projects.</p>
</div>

{include file='_partials/geocoder.tpl'}

<div id="mappanel">
	<div id="map"></div>
</div>

<div id="statistics">
	<div>
		<h3>Applications</h3>
		<p>{$totalApplications|number_format}</p>
	</div>
	<div>
		<h3>Matched proposals</h3>
		<p>{$matchedProposals|number_format}</p>
	</div>
</div>


{include file='_popups/planningapplication.tpl'}

