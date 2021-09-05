<h1>Sign in to monitor areas</h1>


<div class="account">
	
	{if (!$_user)}
	<p>Please sign in to monitor areas and configure settings for notifications.</p>
	{/if}
	
	
	{if isSet($message)}
	<div class="message">
		{$message}
	</div>
	{/if}
	
	{if isSet($form)}
		{$form}
	{/if}
	
	
	<p>Don't have an account? <a href="/register/">Register here.</a>
	
</div>
