<div id="introduction">
	<h1>StreetFocus</h1>
	<h2>Helping local communities benefit from new development.</h2>
	<p>Find out what planning applications there are currently  in your area and get funding for neighbourhood projects. <a class="learnmore" href="/about/">Learn more &gt;</a></p>
</div>

<div id="mappanel">
	<div id="map"></div>
	<div id="geocoder">
		<input type="text" name="location" autocomplete="off" placeholder="Search place, postcode or area" tabindex="1" spellcheck="false" />
		<!--<input type="submit" />-->
	</div>
	<a href="#"><img id="geolocation" src="/images/geolocation.png" /></a>
</div>

<div id="statistics">
	<p><span>{$totalApplications|number_format}</span> Applications</p>
	<p><span>{$matchedProposals|number_format}</span> Matched proposals</p>
</div>


{include file='_popups/planningapplication.tpl'}

