<?php
	
	// Define the in_app var. If this is not defined on page request it means someone is trying to access a specific page without going through here first.
	defined('IN_APP') ? NULL : define('IN_APP', TRUE);

	// Run initialization class
	require_once('library/app.class.php');
	
	App::init();
	
?>