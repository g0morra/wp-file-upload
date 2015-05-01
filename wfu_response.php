<?php
/*
This script runs every time the user presses the upload button in order to inform the page that
it should process the file upload data (stored in $_FILES variable) when the page reloads 
*/
session_start();

if ( isset($_GET['shortcode_id']) && isset($_GET['session_token']) ) {
	//sanitize variables
	$sid = filter_var($_GET['shortcode_id'], FILTER_SANITIZE_STRING);
	$session_token = filter_var($_GET['session_token'], FILTER_SANITIZE_STRING);
	//check referer using server sessions to avoid CSRF attacks
	if ( $_SESSION["wfu_token_".$sid] != $session_token ) die();
	if ( isset($_GET['start_time']) ) {
		$_SESSION['wfu_check_refresh_'.$_GET['shortcode_id']] = 'form button pressed';
		$_SESSION['wfu_start_time_'.$_GET['shortcode_id']] = $_GET['start_time'];

		die("wfu_response_success:");
	}
}
die();
?>
