<h1>Planning applications</h1>

<div id="introduction">
	<p>This map shows proposals by developers to create new buildings, change existing sites, and other changes such as tree works.</p>
</div>

<div id="geocoder">
	<a href="#"><img id="geolocation" src="/images/gps.png" /></a>
	<input type="text" name="location" autocomplete="off" placeholder="Search for place, postcode or planning ref." tabindex="1" spellcheck="false" />
	<input type="image" src="/images/search.png" />
</div>

<div id="mappanel">
	
	<div id="map"></div>
	
	<div id="filter" class="control">
		<p><a href="#"><img id="filter" src="/images/filter.png" /> Refine search</a></p>
	</div>
	
	<div id="filtering">
		<h2>Refine search</h2>
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
		<h3>Boundary</h3>
		<p>Draw boundary to limit search results</p>
		<div id="boundary">
			<p>Draw search area</p>
		</div>
	</div>
	
	<div id="monitor" class="control">
		<a href="#">
			<img src="/images/monitor.png" />
			<h2>Monitor this area</h2>
			<p>Sign up to receive email alerts when new application plans come up.</p>
		</a>
	</div>
	
	<div id="collisions" class="control">
		<p>Show collision hotspots</p>
	</div>
	
</div>


{include file='_popups/planningapplication.tpl'}

