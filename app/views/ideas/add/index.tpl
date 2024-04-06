<h1>Add your idea to improve an area</h1>


<p>Here you can add an idea to improve the environment of your street or another area.</p>
<p>If a planning application arises that could potentially fund this, we will match the idea with it.<br />You can <a href="/my/">monitor an area</a> to be informed of new planning applications.</p>


{if (isset ($error))}
	<p class="error">{$error|escape}</p>
{/if}

{$form}

{if (isset ($resultId))}
	<div class="success">
		<p>✓ Thank you! Your idea is now shown on the <a href="/ideas/internal/{$resultId}/">ideas map</a>.</p>
	</div>
{/if}


