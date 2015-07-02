<?php
if( !session_id() ) { session_start(); }
include_once( dirname(__FILE__).'/lib/wfu_functions.php' );
wfu_download_file();

function wfu_download_file() {
	$file_code = (isset($_POST['file']) ? $_POST['file'] : (isset($_GET['file']) ? $_GET['file'] : ''));
	$ticket = (isset($_POST['ticket']) ? $_POST['ticket'] : (isset($_GET['ticket']) ? $_GET['ticket'] : ''));
	if ( $file_code == '' || $ticket == '' ) die();
	//if download ticket does not exist or is expired die
	if ( !isset($_SESSION['wfu_download_ticket_'.$ticket]) || time() > $_SESSION['wfu_download_ticket_'.$ticket] ) die();
	//destroy ticket so it cannot be used again
	unset($_SESSION['wfu_download_ticket_'.$ticket]);
	
//	$filepath = wfu_plugin_decode_string($file_code);
	$filepath = wfu_get_filepath_from_safe($file_code);
	if ( $filepath === false ) die();
	$filepath = $_SESSION['wfu_ABSPATH'].wfu_flatten_path($filepath);
	//reject download of php files for security reasons
	if ( preg_match("/\.php$/", $filepath) ) {
		$_SESSION['wfu_download_status_'.$ticket] = 'failed';
		die('<script language="javascript">alert("Error! File is forbidden for security reasons.");</script>');
	}
	//check that file exists
	if ( !file_exists($filepath) ) {
		$_SESSION['wfu_download_status_'.$ticket] = 'failed';
		die('<script language="javascript">alert("Error! File does not exist.");</script>');
	}

	set_time_limit(0); // disable the time limit for this script
	if ( $fd = fopen ($filepath, "r") ) {
		$fsize = filesize($filepath);
		$path_parts = pathinfo($filepath);
		$ext = strtolower($path_parts["extension"]);
		switch ($ext) {
			case "pdf":
			header("Content-type: application/pdf");
			header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\""); // use 'attachment' to force a file download
			break;
			// add more headers for other content types here
			default;
			header("Content-type: application/octet-stream");
			header("Content-Disposition: filename=\"".$path_parts["basename"]."\"");
			break;
		}
		header("Content-length: $fsize");
		header("Cache-control: private"); //use this to open files directly
		$failed = false;
		while( !feof($fd) ) {
			$buffer = fread($fd, 2048);
			echo $buffer;
			if ( connection_status() != 0 ) {
				$failed = true;
				break;
			}
		}
		fclose ($fd);
	}
	else $failed = true;
	
	if ( !$failed ) {
		$_SESSION['wfu_download_status_'.$ticket] = 'downloaded';
		die();
	}
	else {
		$_SESSION['wfu_download_status_'.$ticket] = 'failed';
		die('<script language="javascript">alert("Error! Could not download file.");</script>');
	}
}

?>
