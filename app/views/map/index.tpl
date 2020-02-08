<h1>Planning applications</h1>

<div id="introduction">
	<p>This map shows proposals by developers to create new buildings, change existing sites, and other changes such as tree works.</p>
</div>

{include file='_partials/geocoder.tpl'}

<div id="mappanel">
	
	<div id="map"></div>
	
	<div id="details"></div>
	
	<div id="filter" class="control">
		<p><a href="#"><img id="filter" src="/images/filter.png" /> Refine search</a></p>
	</div>
	
	<div id="filtering">
		<p class="close"><a href="#">×</a></p>
		<h2>Refine search</h2>
		<h3>Application activity</h3>
		<ul id="app_state">
			<li><label><input type="checkbox" name="app_state" value="Undecided"> Active</label></li>
			<li><label><input type="checkbox" name="app_state" value="Permitted,Conditions,Rejected,Withdrawn,Other"> Closed</label></li>
		</ul>
		<h3>Application type</h3>
		<ul id="app_type">
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
		<ul id="app_size">
			<li><label><input type="checkbox" name="app_size" value="Small"> Small</label></li>
			<li><label><input type="checkbox" name="app_size" value="Medium"> Medium</label></li>
			<li><label><input type="checkbox" name="app_size" value="Large"> Large</label></li>
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

	
