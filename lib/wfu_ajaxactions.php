<?php


function wfu_ajax_action_send_email_notification() {
	$user = wp_get_current_user();
	if ( 0 == $user->ID ) $is_admin = false;
	else $is_admin = current_user_can('manage_options');

	$arr = wfu_get_params_fields_from_index($_POST['params_index']);
	//check referer using server sessions to avoid CSRF attacks
	if ( $_SESSION["wfu_token_".$arr['shortcode_id']] != $_POST['session_token'] ) die();
	if ( $user->user_login != $arr['user_login'] ) die();

	$params_str = get_option('wfu_params_'.$arr['unique_id']);
	$params = wfu_decode_array_from_string($params_str);

	/* initialize return array */
	$params_output_array["version"] = "full";
	$params_output_array["general"]['shortcode_id'] = $params["uploadid"];
	$params_output_array["general"]['unique_id'] = ( isset($_POST['unique_id']) ? $_POST['unique_id'] : "" );
	$params_output_array["general"]['state'] = 0;
	$params_output_array["general"]['files_count'] = 0;
	$params_output_array["general"]['update_wpfilebase'] = "";
	$params_output_array["general"]['redirect_link'] = "";
	$params_output_array["general"]['upload_finish_time'] = "";
	$params_output_array["general"]['message'] = "";
	$params_output_array["general"]['message_type'] = "";
	$params_output_array["general"]['admin_messages']['wpfilebase'] = "";
	$params_output_array["general"]['admin_messages']['notify'] = "";
	$params_output_array["general"]['admin_messages']['redirect'] = "";
	$params_output_array["general"]['admin_messages']['other'] = "";
	$params_output_array["general"]['errors']['wpfilebase'] = "";
	$params_output_array["general"]['errors']['notify'] = "";
	$params_output_array["general"]['errors']['redirect'] = "";
	$params_output_array["general"]['color'] = "black";
	$params_output_array["general"]['bgcolor'] = "#F5F5F5";
	$params_output_array["general"]['borcolor'] = "#D3D3D3";
	$params_output_array["general"]['notify_only_filename_list'] = "";
	$params_output_array["general"]['notify_target_path_list'] = "";
	$params_output_array["general"]['notify_attachment_list'] = "";
	$params_output_array["general"]['fail_message'] = WFU_ERROR_UNKNOWN;

	// prepare user data 
	$userdata_fields = $params["userdata_fields"]; 
	foreach ( $userdata_fields as $userdata_key => $userdata_field ) 
		$userdata_fields[$userdata_key]["value"] = ( isset($_POST['userdata_'.$userdata_key]) ? wfu_plugin_decode_string($_POST['userdata_'.$userdata_key]) : "" );

	$send_error = wfu_send_notification_email($user, $_POST['only_filename_list'], $_POST['target_path_list'], $_POST['attachment_list'], $userdata_fields, $params);

	/* suppress any errors if user is not admin */
	if ( !$is_admin ) $send_error = "";

	if ( $send_error != "" ) {
		$params_output_array["general"]['admin_messages']['notify'] = $send_error;
		$params_output_array["general"]['errors']['notify'] = "error";
	}

	/* construct safe output */
	$sout = "0;".WFU_DEFAULTMESSAGECOLORS.";0";

	die("wfu_fileupload_success:".$sout.":".wfu_encode_array_to_string($params_output_array)); 
}

function wfu_ajax_action_callback() {
	$user = wp_get_current_user();
	$arr = wfu_get_params_fields_from_index($_POST['params_index']);
	//check referrer using server sessions to avoid CSRF attacks
	if ( $_SESSION["wfu_token_".$arr['shortcode_id']] != $_POST['session_token'] ) {
		echo "Session failed!<br/><br/>Session Data:<br/>";
		print_r(wfu_sanitize($_SESSION));
		echo "<br/><br/>Post Data:<br/>";
		print_r(wfu_sanitize($_POST));
		die('force_errorabort_code');
	}

	if ( $user->user_login != $arr['user_login'] ) {
		echo "User failed!<br/><br/>User Data:<br/>";
		print_r(wfu_sanitize($user));
		echo "<br/><br/>Post Data:<br/>";
		print_r(wfu_sanitize($_POST));
		echo "<br/><br/>Params Data:<br/>";
		print_r(wfu_sanitize($arr));
		die('force_errorabort_code');
	}

	//get the unique id of the upload
	$unique_id = ( isset($_POST['unique_id']) ? $_POST['unique_id'] : "" );
	
	//the first pass to this callback script is for closing the previous connection_aborted
	if ( isset($_POST["force_connection_close"]) && $_POST["force_connection_close"] === "1" ) {
		header("Connection: Close");
		die("success");
	}
	
	//if upload has finished then perform post upload actions
	if ( isset($_POST["upload_finished"]) && $_POST["upload_finished"] === "1" ) {
		die("success");
	}
	
	$params_str = get_option('wfu_params_'.$arr['unique_id']);
	$params = wfu_decode_array_from_string($params_str);

	$params['subdir_selection_index'] = $_POST['subdir_sel_index'];
	$_SESSION['wfu_check_refresh_'.$params["uploadid"]] = 'do not process';

	$wfu_process_file_array = wfu_process_files($params, 'ajax');
	// extract safe_output from wfu_process_file_array and pass it as separate part of the response text
	$safe_output = $wfu_process_file_array["general"]['safe_output'];
	unset($wfu_process_file_array["general"]['safe_output']);
	die("wfu_fileupload_success:".$safe_output.":".wfu_encode_array_to_string($wfu_process_file_array)); 
}

function wfu_ajax_action_save_shortcode() {
	if ( !current_user_can( 'manage_options' ) ) die();
	if ( !isset($_POST['shortcode']) || !isset($_POST['shortcode_original']) || !isset($_POST['post_id']) || !isset($_POST['post_hash']) || !isset($_POST['shortcode_position']) ) die();
	
	if ( $_POST['post_id'] == "" ) {
		$plugin_options = wfu_decode_plugin_options(get_option( "wordpress_file_upload_options" ));
		$new_plugin_options['version'] = '1.0';
		$new_plugin_options['shortcode'] = $plugin_options['shortcode'];
		$new_plugin_options['hashfiles'] = $plugin_options['hashfiles'];
		$new_plugin_options['basedir'] = $plugin_options['basedir'];
		$encoded_options = wfu_encode_plugin_options($new_plugin_options);
		update_option( "wordpress_file_upload_options", $encoded_options );

		die("wfu_save_shortcode:success:");
	}
	else {
		$data['post_id'] = $_POST['post_id'];
		$data['post_hash'] = $_POST['post_hash'];
		$data['shortcode'] = wfu_plugin_decode_string($_POST['shortcode_original']);
		$data['position'] = $_POST['shortcode_position'];
		if ( !wfu_check_edit_shortcode($data) ) die("wfu_save_shortcode:fail:post_modified");
		else {
			$new_shortcode = "[wordpress_file_upload ".wfu_plugin_decode_string($_POST['shortcode'])."]";
			if ( wfu_replace_shortcode($data, $new_shortcode) ) {
				$post = get_post($_POST['post_id']);
				$hash = hash('md5', $post->post_content);
				die("wfu_save_shortcode:success:".$hash);
			}
			else die("wfu_save_shortcode:fail:post_update_failed");
		}
	}
}

function wfu_ajax_action_check_page_contents() {
	if ( !current_user_can( 'manage_options' ) ) die();
	if ( !isset($_POST['post_id']) || !isset($_POST['post_hash']) ) die();
	if ( $_POST['post_id'] == "" ) die();

	$data['post_id'] = $_POST['post_id'];
	$data['post_hash'] = $_POST['post_hash'];
	if ( wfu_check_edit_shortcode($data) ) die("wfu_check_page_contents:current:");
	else die("wfu_check_page_contents:obsolete:");
}

function wfu_ajax_action_edit_shortcode() {
	if ( !current_user_can( 'manage_options' ) ) die();
	if ( !isset($_POST['upload_id']) || !isset($_POST['post_id']) || !isset($_POST['post_hash']) ) die();
	
	$data['post_id'] = $_POST['post_id'];
	$data['post_hash'] = $_POST['post_hash'];
	if ( wfu_check_edit_shortcode($data) ) {
		$post = get_post($data['post_id']);
		//get default value for uploadid
		$defs = wfu_attribute_definitions();
		$default = "";
		foreach ( $defs as $key => $def ) {
			if ( $def['attribute'] == 'uploadid' ) {
				$default = $def['value'];
				break;
			}
		}
		//get page shortcodes
		$wfu_shortcodes = wfu_get_content_shortcodes($post, 'wordpress_file_upload');
		//find the shortcodes' uploadid and the correct one
		$validkey = -1;
		foreach ( $wfu_shortcodes as $key => $data ) {
			$shortcode = trim(substr($data['shortcode'], strlen('[wordpress_file_upload'), -1));
			$shortcode_attrs = wfu_shortcode_string_to_array($shortcode);
			if ( array_key_exists('uploadid', $shortcode_attrs) ) $uploadid = $shortcode_attrs['uploadid'];
			else $uploadid = $default;
			if ( $uploadid == $_POST['upload_id'] ) {
				$validkey = $key;
				break;
			}
		}
		if ( $validkey == -1 ) die();
		$data_enc = wfu_encode_array_to_string($wfu_shortcodes[$validkey]);
		$url = site_url().'/wp-admin/options-general.php?page=wordpress_file_upload&action=edit_shortcode&data='.$data_enc;
		die("wfu_edit_shortcode:success:".wfu_plugin_encode_string($url));
	}
	else die("wfu_edit_shortcode:check_page_obsolete:".WFU_ERROR_PAGE_OBSOLETE);
}

function wfu_ajax_action_read_subfolders() {
	if ( !isset($_POST['folder1']) || !isset($_POST['folder2']) ) die();
	$temp_params = array( 'uploadpath' => wfu_plugin_decode_string($_POST['folder1']), 'accessmethod' => 'normal', 'ftpinfo' => '', 'useftpdomain' => 'false' );
	$path = wfu_upload_plugin_full_path($temp_params);

	if ( !is_dir($path) ) die("wfu_read_subfolders:error:Parent folder is not valid! Cannot retrieve subfolder list.");

	$path2 = wfu_plugin_decode_string($_POST['folder2']);
	$dirlist = "";
	if ( $handle = opendir($path) ) {
		$blacklist = array('.', '..');
		while ( false !== ($file = readdir($handle)) )
			if ( !in_array($file, $blacklist) ) {
				$filepath = $path.$file;
				if ( is_dir($filepath) ) {
					if ( $file == $path2 ) $file = '[['.$file.']]';
					$dirlist .= ( $dirlist == "" ? "" : "," ).$file;
				}
			}
		closedir($handle);
	}
	if ( $path2 != "" ) {
		$dirlist2 = $path2;
		$path .= $path2."/";
		if ( is_dir($path) ) {
			if ( $handle = opendir($path) ) {
				$blacklist = array('.', '..');
				while ( false !== ($file = readdir($handle)) )
					if ( !in_array($file, $blacklist) ) {
						$filepath = $path.$file;
						if ( is_dir($filepath) )
							$dirlist2 .= ",*".$file;
					}
				closedir($handle);
			}
		}
		$dirlist = str_replace('[['.$path2.']]', $dirlist2, $dirlist);
	}

	die("wfu_read_subfolders:success:".wfu_plugin_encode_string($dirlist));
}

function wfu_ajax_action_download_file_invoker() {
	$file_enc = (isset($_POST['file']) ? $_POST['file'] : (isset($_GET['file']) ? $_GET['file'] : ''));
	$nonce = (isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_GET['nonce']) ? $_GET['nonce'] : ''));
	if ( $file_enc == '' || $nonce == '' ) die();
	
	//security check to avoid CSRF attacks
	if ( !wp_verify_nonce($nonce, 'wfu_download_file_invoker') ) die();
	
	$filepath = wfu_plugin_decode_string($file_enc);

	//check if user is allowed to perform this action
	$user_allowed = wfu_current_user_allowed_action('download', $filepath);
	if ( $user_allowed == null ) die();
	
	//generate download unique id to monitor this download
	$download_id = wfu_create_random_string(16);
	//store download status of this download
	$_SESSION['wfu_download_status_'.$download_id] = 'starting';
	//generate download ticket which expires in 30sec and store it in session
	//it will be used as security measure for the downloader script, which runs outside Wordpress environment
	$_SESSION['wfu_download_ticket_'.$download_id] = time() + 30;
	//generate download monitor ticket which expires in 30sec and store it in session
	//it will be used as security measure for the monitor script that will check download status
	$_SESSION['wfu_download_monitor_ticket_'.$download_id] = time() + 30;
	
	//this routine returns a dynamically created iframe element, that will call the actual download script;
	//the actual download script runs outside Wordpress environment in order to ensure that no php warnings
	//or echo from other plugins is generated, that could scramble the downloaded file;
	//a ticket, similar to nonces, is passed to the download script to check that it is not a CSRF attack; moreover,the ticket is destroyed
	//by the time it is consumed by the download script, so it cannot be used again
	$response = '<iframe src="'.WFU_DOWNLOADER_URL.'?file='.$file_enc.'&ticket='.$download_id.'" style="display: none;"></iframe>';

	die('wfu_ajax_action_download_file_invoker:wfu_download_id;'.$download_id.':'.$response);
}

function wfu_ajax_action_download_file_monitor() {
	$file_enc = (isset($_POST['file']) ? $_POST['file'] : (isset($_GET['file']) ? $_GET['file'] : ''));
	$id = (isset($_POST['id']) ? $_POST['id'] : (isset($_GET['id']) ? $_GET['id'] : ''));
	if ( $file_enc == '' || $id == '' ) die();
	
	//ensure that this is not a CSRF attack by checking validity of a security ticket
	if ( !isset($_SESSION['wfu_download_monitor_ticket_'.$id]) || time() > $_SESSION['wfu_download_monitor_ticket_'.$id] ) die('pass');
	//destroy monitor ticket so it cannot be used again
	unset($_SESSION['wfu_download_monitor_ticket_'.$id]);
	
	//initiate loop of 30secs to check the download status of the file;
	//the download status is controlled by the actual download script;
	//if the file finishes within the 30secs of the loop, then this routine logs the action and notifies
	//the client side about the download status of the file, otherwise an instruction
	//to the client side to repeat this routine and wait for another 30secs is dispatched
	$end_time = time() + 30;
	$upload_ended = false;
	while ( time() < $end_time ) {
		$upload_ended = ( isset($_SESSION['wfu_download_status_'.$id]) ? ( $_SESSION['wfu_download_status_'.$id] == 'downloaded' || $_SESSION['wfu_download_status_'.$id] == 'failed' ? true : false ) : false );
		if ( $upload_ended ) break;
		usleep(100);
	}
	
	if ( $upload_ended ) {
		$user = wp_get_current_user();
		$filepath = wfu_plugin_decode_string($file_enc);
		wfu_log_action('download', $filepath, $user->ID, '', 0, '', null);
		die('wfu_ajax_action_download_file_monitor:'.$_SESSION['wfu_download_status_'.$id].':');
	}
	else {
		//regenerate monitor ticket
		$_SESSION['wfu_download_monitor_ticket_'.$id] = time() + 30;
		die('wfu_ajax_action_download_file_monitor:repeat:'.$id);
	}
}

function wfu_ajax_action_notify_wpfilebase() {
	$params_index = (isset($_POST['params_index']) ? $_POST['params_index'] : (isset($_GET['params_index']) ? $_GET['params_index'] : ''));
	$session_token = (isset($_POST['session_token']) ? $_POST['session_token'] : (isset($_GET['session_token']) ? $_GET['session_token'] : ''));
	if ( $params_index == '' || $session_token == '' ) die();

	$arr = wfu_get_params_fields_from_index($params_index);
	//check referer using server sessions to avoid CSRF attacks
	if ( $_SESSION["wfu_token_".$arr['shortcode_id']] != $session_token ) die();

	do_action('wpfilebase_sync');

	die();
}

?>
