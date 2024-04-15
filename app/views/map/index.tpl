<h1>Planning applications</h1>

<div id="introduction">
	<p>This map shows submitted applications by developers to create new buildings, change existing sites, and other changes such as tree works.</p>
</div>

{include file='_partials/geocoder.tpl'}

<div id="mappanel">
	
	<div id="map"></div>
	
	<div id="details"></div>
	
	<div id="filter" class="control">
		<p><a href="#"><img src="/images/filter.png" alt="Filter" /> Refine search</a></p>
	</div>
	
	<form id="filtering" tabindex="2">
		<p class="close"><a href="#">×</a></p>
		
		<input type="reset" value="Reset" title="Reset all filters below, to show everything"></input>
		
		<h2>Refine search</h2>
		
		<h3>Application activity</h3>
		<ul id="state">
			<li title="Applications currently being consulted on or undecided"><label><input type="checkbox" name="state[]" value="Undecided" checked="checked" /> Current</label></li>
			<li title="Approved applications"><label><input type="checkbox" name="state[]" value="Conditions,Permitted,Referred,Rejected,Unresolved,Withdrawn" /> Decided</label></li>
		</ul>
		
		{include file='_partials/filtering.tpl'}
		
		<h3>Date range</h3>
		<p id="dateslider"></p>
		<p id="daterange">
			<input type="number" value="{$dateRange.since}" name="since" min="{$dateRange.min}" max="{$dateRange.max}" step="1" />
			<span> - </span>
			<input type="number" value="{$dateRange.until}" name="until" min="{$dateRange.min}" max="{$dateRange.max}" step="1" />
		</p>
	</form>
	
	<div id="zoomstate" class="control">
		<p>Zoom in more <br />to see locations</p>
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

	
