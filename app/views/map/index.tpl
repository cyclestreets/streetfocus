<div id="introduction">
	<p>The planning applications map shows proposals by developers to create new buildings, change existing sites, or make other changes such as tree works.</p>
</div>

<div id="mappanel">
	
	<div id="geocoder">
		<input type="text" name="location" autocomplete="off" placeholder="Search place, postcode or area" tabindex="1" spellcheck="false" />
		<!--<input type="submit" />-->
	</div>
	
	<div id="map"></div>
	
	<a href="#"><img id="geolocation" src="/images/geolocation.png" /></a>
	
	<img id="filter" src="/images/filter.png" />
	<div id="filtering">
		<h2>Refine your search</h2>
		<h3>Application activity</h3>
		<ul id="state">
			<li><a href="#">Active</a></li>
			<li><a href="#">Closed</a></li>
		</ul>
		<h3>Application type</h3>
		<ul id="type">
			<li><label><input type="checkbox" name="app_type" value="Full"> Full</label></li>
			<li><label><input type="checkbox" name="app_type" value="Outline"> Outline</label></li>
			<li><label><input type="checkbox" name="app_type" value="Amendment"> Amendment</label></li>
			<li><label><input type="checkbox" name="app_type" value="Heritage"> Heritage</label></li>
			<li><label><input type="checkbox" name="app_type" value="Trees"> Trees</label></li>
			<li><label><input type="checkbox" name="app_type" value="Advertising"> Advertising</label></li>
			<li><label><input type="checkbox" name="app_type" value="Telecoms"> Telecoms</label></li>
			<li><label><input type="checkbox" name="app_type" value="Other"> Other</label></li>
		</ul>
		<h3>Size of development</h3>
		<ul id="size">
			<li><a href="#">Small</a></li>
			<li><a href="#">Medium</a></li>
			<li><a href="#">Large</a></li>
		</ul>
	</div>
	
</div>


{include file='_popups/planningapplication.tpl'}

