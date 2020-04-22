<h1>Planning applications</h1>

<div id="introduction">
	<p>This map shows proposals by developers to create new buildings, change existing sites, and other changes such as tree works.</p>
</div>

{include file='_partials/geocoder.tpl'}

<div id="mappanel">
	
	<div id="map"></div>
	
	<div id="details"></div>
	
	<div id="filter" class="control">
		<p><a href="#"><img src="/images/filter.png" alt="Filter" /> Refine search</a></p>
	</div>
	
	<div id="filtering" tabindex="2">
		<p class="close"><a href="#">×</a></p>
		
		<p class="reset"><a href="#">Reset</a></p>
		<h2>Refine search</h2>
		
		{include file='_partials/filtering.tpl'}
		
		<h3>Application activity</h3>
		<ul id="app_state">
			<li><label><input type="checkbox" name="app_state[]" value="Undecided" /> Current</label></li>
			<li><label><input type="checkbox" name="app_state[]" value="Permitted,Conditions,Rejected,Withdrawn,Other" /> Decided</label></li>
		</ul>
	</div>
	
	<div id="monitor" class="control">
		<a href="/my/add/">
			<img src="/images/monitor.png" alt="Monitor" />
			<h2>Monitor this area</h2>
			<p>Sign up to receive e-mail alerts when new application plans come up.</p>
		</a>
	</div>
	
	<div id="collisions" class="control">
		<p>Show collision hotspots</p>
	</div>
	
</div>


{include file='_popups/planningapplication.tpl'}

	
