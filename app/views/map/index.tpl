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
		{include file='_partials/filtering.tpl'}
		
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

	
