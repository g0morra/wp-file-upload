<?php
if( !session_id() ) { session_start(); }
/*Plugin Name: Wordpress File Upload
/*
Plugin URI: http://www.iptanus.com/support/wordpress-file-upload
Description: Simple interface to upload files from a page.
Version: 2.7.6
Author: Nickolas Bossinas
Author URI: http://www.iptanus.com
*/

/*
Wordpress File Upload (Wordpress Plugin)
Copyright (C) 2010-2015 Nickolas Bossinas
Contact me at http://www.iptanus.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

//set global db variables
$wfu_tb_log_version = "1.0";
$wfu_tb_userdata_version = "1.0";

/* do not load plugin if this is the login page */
$uri = $_SERVER['REQUEST_URI'];
if ( strpos($uri, 'wp-login.php') !== false ) return;

DEFINE("WPFILEUPLOAD_PLUGINFILE", __FILE__);
DEFINE("WPFILEUPLOAD_DIR", '/'.PLUGINDIR .'/'.dirname(plugin_basename (__FILE__)).'/');
DEFINE("ABSWPFILEUPLOAD_DIR", ABSPATH.WPFILEUPLOAD_DIR);
add_shortcode("wordpress_file_upload", "wordpress_file_upload_handler");
load_plugin_textdomain('wordpress-file-upload', false, dirname(plugin_basename (__FILE__)).'/languages');
/* load styles and scripts for front pages */
if ( !is_admin() ) {
	add_action( 'wp_enqueue_scripts', 'wfu_enqueue_frontpage_scripts' );
}
add_action('admin_init', 'wordpress_file_upload_admin_init');
add_action('admin_menu', 'wordpress_file_upload_add_admin_pages');
register_activation_hook(__FILE__,'wordpress_file_upload_install');
add_action('plugins_loaded', 'wordpress_file_upload_update_db_check');
//ajax actions
add_action('wp_ajax_wfu_ajax_action', 'wfu_ajax_action_callback');
add_action('wp_ajax_nopriv_wfu_ajax_action', 'wfu_ajax_action_callback');
add_action('wp_ajax_wfu_ajax_action_send_email_notification', 'wfu_ajax_action_send_email_notification');
add_action('wp_ajax_nopriv_wfu_ajax_action_send_email_notification', 'wfu_ajax_action_send_email_notification');
add_action('wp_ajax_wfu_ajax_action_notify_wpfilebase', 'wfu_ajax_action_notify_wpfilebase');
add_action('wp_ajax_nopriv_wfu_ajax_action_notify_wpfilebase', 'wfu_ajax_action_notify_wpfilebase');
add_action('wp_ajax_wfu_ajax_action_save_shortcode', 'wfu_ajax_action_save_shortcode');
add_action('wp_ajax_wfu_ajax_action_check_page_contents', 'wfu_ajax_action_check_page_contents');
add_action('wp_ajax_wfu_ajax_action_read_subfolders', 'wfu_ajax_action_read_subfolders');
add_action('wp_ajax_wfu_ajax_action_download_file_invoker', 'wfu_ajax_action_download_file_invoker');
add_action('wp_ajax_nopriv_wfu_ajax_action_download_file_invoker', 'wfu_ajax_action_download_file_invoker');
add_action('wp_ajax_wfu_ajax_action_download_file_monitor', 'wfu_ajax_action_download_file_monitor');
add_action('wp_ajax_nopriv_wfu_ajax_action_download_file_monitor', 'wfu_ajax_action_download_file_monitor');
add_action('wp_ajax_wfu_ajax_action_edit_shortcode', 'wfu_ajax_action_edit_shortcode');
wfu_include_lib();

function wfu_enqueue_frontpage_scripts() {
//	wp_enqueue_style('wordpress-file-upload-reset', WPFILEUPLOAD_DIR.'css/wordpress_file_upload_reset.css',false,'1.0','all');
	wp_enqueue_style('wordpress-file-upload-style', WPFILEUPLOAD_DIR.'css/wordpress_file_upload_style.css',false,'1.0','all');
	wp_enqueue_style('wordpress-file-upload-style-safe', WPFILEUPLOAD_DIR.'css/wordpress_file_upload_style_safe.css',false,'1.0','all');
	wp_enqueue_script('json_class', WPFILEUPLOAD_DIR.'js/json2.js');
	wp_enqueue_script('wordpress_file_upload_script', WPFILEUPLOAD_DIR.'js/wordpress_file_upload_functions.js');
}

function wfu_include_lib() {
	if ( $handle = opendir(plugin_dir_path( __FILE__ )."lib/") ) {
		$blacklist = array('.', '..');
		while ( false !== ($file = readdir($handle)) )
			if ( !in_array($file, $blacklist) )
				include_once plugin_dir_path( __FILE__ )."lib/".$file;
		closedir($handle);
	}
	if ( $handle = opendir(plugin_dir_path( __FILE__ )) ) {
		closedir($handle);
	}
}

/* exit if we are in admin pages (in case of ajax call) */
if ( is_admin() ) return;

function wordpress_file_upload_handler($incomingfrompost) {
	//process incoming attributes assigning defaults if required
	$defs = wfu_attribute_definitions();
	$defs_indexed = array();
	foreach ( $defs as $def ) $defs_indexed[$def["attribute"]] = $def["value"];
	$incomingfrompost = shortcode_atts($defs_indexed, $incomingfrompost);
	//run function that actually does the work of the plugin
	$wordpress_file_upload_output = wordpress_file_upload_function($incomingfrompost);
	//send back text to replace shortcode in post
	return $wordpress_file_upload_output;
}

function wordpress_file_upload_function($incomingfromhandler) {
	global $post;
	global $blog_id;
	$params = wfu_plugin_parse_array($incomingfromhandler);
	$sid = $params["uploadid"];
	// store current page id in params array
	$params["pageid"] = $post->ID;

	if ( !isset($_SESSION['wfu_token_'.$sid]) || $_SESSION['wfu_token_'.$sid] == "" )
		$_SESSION['wfu_token_'.$sid] = uniqid(mt_rand(), TRUE);
	//store the server environment (32 or 64bit) for use when checking file size limits
	$params["php_env"] = wfu_get_server_environment();

	$user = wp_get_current_user();
	$widths = wfu_decode_dimensions($params["widths"]);
	$heights = wfu_decode_dimensions($params["heights"]);

	$uploadedfile = 'uploadedfile_'.$sid;
	$hiddeninput = 'hiddeninput_'.$sid;
	$adminerrorcodes = 'adminerrorcodes_'.$sid;
	$upload_clickaction = 'wfu_redirect_to_classic('.$sid.', \''.$_SESSION['wfu_token_'.$sid].'\' , 0, 0);';

	//check if user is allowed to view plugin, otherwise do not generate it
	$uploadroles = explode(",", $params["uploadrole"]);
	foreach ( $uploadroles as &$uploadrole ) {
		$uploadrole = strtolower(trim($uploadrole));
	}
	$plugin_upload_user_role = wfu_get_user_role($user, $uploadroles);		
	if ( !in_array($plugin_upload_user_role, $uploadroles) && $plugin_upload_user_role != 'administrator' && $params["uploadrole"] != 'all' ) return;

	//activate debug mode only for admins
	if ( $plugin_upload_user_role != 'administrator' ) $params["debugmode"] = "false";

	$params["adminmessages"] = ( $params["adminmessages"] == "true" && $plugin_upload_user_role == 'administrator' );
	// define variable to hold any additional admin errors coming before processing of files (e.g. due to redirection)
	$params["adminerrors"] = "";

	/* Define dynamic upload path from variables */
	$search = array ('/%userid%/', '/%username%/', '/%blogid%/', '/%pageid%/', '/%pagetitle%/');	
	if ( is_user_logged_in() ) $username = $user->user_login;
	else $username = "guests";
	$replace = array ($user->ID, $username, $blog_id, $post->ID, get_the_title($post->ID));
	$params["uploadpath"] = preg_replace($search, $replace, $params["uploadpath"]);

	/* Determine if userdata fields have been defined */
	$userdata_fields = array(); 
	if ( $params["userdata"] == "true" && $params["userdatalabel"] != "" ) {
		$userdata_rawfields = explode("/", $params["userdatalabel"]);
		foreach ($userdata_rawfields as $userdata_rawitem) {
			if ( $userdata_rawitem != "" ) {
				$is_required = ( $userdata_rawitem[0] == "*" ? "true" : "false" );
				if ( $is_required == "true" ) $userdata_rawitem = substr($userdata_rawitem, 1);
				if ( $userdata_rawitem != "" ) {
					array_push($userdata_fields, array( "label" => $userdata_rawitem, "required" => $is_required ));
				}
			}
		}
		
	}
	$params["userdata_fields"] = $userdata_fields; 
	
	/* If medialink or postlink is activated, then subfolders are deactivated */
	if ( $params["medialink"] == "true" || $params["postlink"] == "true" ) $params["askforsubfolders"] = "false";

	/* Prepare information about directory or selection of target subdirectory */
	$subfolders = wfu_prepare_subfolders_block($params, $widths, $heights);
	$subfolders_item = $subfolders['item'];
	$params['subfoldersarray'] = $subfolders['paths'];

//____________________________________________________________________________________________________________________________________________________________________________________

	if ( $params['forceclassic'] != "true" ) {	
//**************section to put additional options inside params array**************
		$params['subdir_selection_index'] = "-1";
//**************end of section of additional options inside params array**************


//	below this line no other changes to params array are allowed


//**************section to save params as Wordpress options**************
//		every params array is indexed (uniquely identified) by three fields:
//			- the page that contains the shortcode
//			- the id of the shortcode instance (because there may be more than one instances of the shortcode inside a page)
//			- the user that views the plugin (because some items of the params array are affected by the user name)
//		the wordpress option "wfu_params_index" holds an array of combinations of these three fields, together with a randomly generated string that corresponds to these fields.
//		the wordpress option "wfu_params_xxx", where xxx is the randomly generated string, holds the params array (encoded to string) that corresponds to this string.
//		the structure of the "wfu_params_index" option is as follows: "a1||b1||c1||d1&&a2||b2||c2||d2&&...", where
//			- a is the randomly generated string (16 characters)
//			- b is the page id
//			- c is the shortcode id
//			- d is the user name
		$params_index = wfu_generate_current_params_index($sid, $user->user_login);
		$params_str = wfu_encode_array_to_string($params);
		update_option('wfu_params_'.$params_index, $params_str);
		$ajax_params['shortcode_id'] = $sid;
		$ajax_params['params_index'] = $params_index;
		$ajax_params['debugmode'] = $params["debugmode"];
		$ajax_params['is_admin'] = ( $plugin_upload_user_role == 'administrator' ? "true" : "false" );
		$ajax_params["error_header"] = $params["errormessage"];
		$ajax_params["fail_colors"] = $params["failmessagecolors"];

		$ajax_params_str = wfu_encode_array_to_string($ajax_params);
		$upload_clickaction = 'wfu_HTML5UploadFile('.$sid.', \''.$ajax_params_str.'\', \''.$_SESSION['wfu_token_'.$sid].'\')';
	}
	$upload_onclick = ' onclick="'.$upload_clickaction.'"';

	/* Prepare the title */
	$title_item = wfu_prepare_title_block($params, $widths, $heights);
	/* Prepare the text box showing filename */
	$textbox_item = wfu_prepare_textbox_block($params, $widths, $heights);
	/* Prepare the upload form */
	$additional_params = array( );
	$uploadform_item = wfu_prepare_uploadform_block($params, $widths, $heights, $upload_clickaction, $additional_params);
	/* Prepare the submit button */
	$submit_item = wfu_prepare_submit_block($params, $widths, $heights, $upload_clickaction);
	/* Prepare the progress bar */
	$progressbar_item = wfu_prepare_progressbar_block($params, $widths, $heights);
	/* Prepare the message */
	$message_item = wfu_prepare_message_block($params, $widths, $heights);
	/* Prepare user data */
	$userdata_item = wfu_prepare_userdata_block($params, $widths, $heights);

	/* Compose the html code for the plugin */
	$wordpress_file_upload_output = "";
	$wordpress_file_upload_output .= '<div id="wordpress_file_upload_block_'.$sid.'" class="file_div_clean wfu_container">';
	//add visual editor overlay if the current user is administrator
	if ( current_user_can( 'manage_options' ) ) {
		$wordpress_file_upload_output .= "\n\t".'<div id="wordpress_file_upload_editor_'.$sid.'" class="wfu_overlay_editor">';
		$wordpress_file_upload_output .= "\n\t\t".'<button class="wfu_overlay_editor_button" title="'.WFU_PAGE_PLUGINEDITOR_BUTTONTITLE.'" onclick="wfu_invoke_shortcode_editor('.$sid.', '.$post->ID.', \''.hash('md5', $post->post_content).'\');"><img src="'.WFU_IMAGE_OVERLAY_EDITOR.'" width="20px" height="20px" /></button>';
		$wordpress_file_upload_output .= "\n\t".'</div>';
		$wordpress_file_upload_output .= "\n\t".'<div id="wordpress_file_upload_overlay_'.$sid.'" class="wfu_overlay_container">';
		$wordpress_file_upload_output .= "\n\t\t".'<table class="wfu_overlay_table"><tbody><tr><td><img src="'.WFU_IMAGE_OVERLAY_LOADING.'" /><label>'.WFU_PAGE_PLUGINEDITOR_LOADING.'</label></td></tr></tbody></table>';
		$wordpress_file_upload_output .= "\n\t\t".'<div class="wfu_overlay_container_inner"></div>';
		$wordpress_file_upload_output .= "\n\t".'</div>';
	}
	$itemplaces = explode("/", $params["placements"]);
	foreach ( $itemplaces as $section ) {
		$items_in_section = explode("+", trim($section));
		$section_array = array( );
		foreach ( $items_in_section as $item_in_section ) {
			$item_in_section = strtolower(trim($item_in_section));
			if ( $item_in_section == "title" ) array_push($section_array, $title_item);
			elseif ( $item_in_section == "filename" ) array_push($section_array, $textbox_item);
			elseif ( $item_in_section == "selectbutton" ) array_push($section_array, $uploadform_item);
			elseif ( $item_in_section == "confirmbox" && preg_match("/(^|,)\s*checkbox\s*(,|$)/", $params['security_active']) && $params["singlebutton"] != "true" ) array_push($section_array, $confirmbox_item);
			elseif ( $item_in_section == "uploadbutton" && $params["singlebutton"] != "true" ) array_push($section_array, $submit_item);
			elseif ( $item_in_section == "subfolders" ) array_push($section_array, $subfolders_item);
			elseif ( $item_in_section == "progressbar" ) array_push($section_array, $progressbar_item);
			elseif ( $item_in_section == "message" ) array_push($section_array, $message_item);
			elseif ( $item_in_section == "userdata" && $params["userdata"] == "true" ) array_push($section_array, $userdata_item);
		}
		$wordpress_file_upload_output .= call_user_func_array("wfu_add_div", $section_array);
	}
	/* Append mandatory blocks, if have not been included in placements attribute */
	if ( $params["userdata"] == "true" && strpos($params["placements"], "userdata") === false ) {
		$section_array = array( );
		array_push($section_array, $userdata_item);
		$wordpress_file_upload_output .= call_user_func_array("wfu_add_div", $section_array);
	}
	if ( strpos($params["placements"], "selectbutton") === false ) {
		$section_array = array( );
		array_push($section_array, $uploadform_item);
		$wordpress_file_upload_output .= call_user_func_array("wfu_add_div", $section_array);
	}

	/* Pass constants to javascript and run plugin post-load actions */
	$consts = wfu_set_javascript_constants();
	$handler = 'function() { wfu_Initialize_Consts("'.$consts.'"); wfu_plugin_load_action('.$sid.'); }';
	$wordpress_file_upload_output .= "\n\t".'<script type="text/javascript">if(window.addEventListener) { window.addEventListener("load", '.$handler.', false); } else if(window.attachEvent) { window.attachEvent("onload", '.$handler.'); } else { window["onload"] = '.$handler.'; }</script>';
	$wordpress_file_upload_output .= '</div>';
//	$wordpress_file_upload_output .= '<div>';
//	$wordpress_file_upload_output .= wfu_test_admin();
//	$wordpress_file_upload_output .= '</div>';

//	The plugin uses sessions in order to detect if the page was loaded due to file upload or
//	because the user pressed the Refresh button (or F5) of the page.
//	In the second case we do not want to perform any file upload, so we abort the rest of the script.
	if ( !isset($_SESSION['wfu_check_refresh_'.$sid]) || $_SESSION['wfu_check_refresh_'.$sid] != "form button pressed" ) {
		$_SESSION['wfu_check_refresh_'.$sid] = 'do not process';
		$wordpress_file_upload_output .= wfu_post_plugin_actions($params);
		return $wordpress_file_upload_output."\n";
	}
	$_SESSION['wfu_check_refresh_'.$sid] = 'do not process';
	$params["upload_start_time"] = $_SESSION['wfu_start_time_'.$sid];

//	The plugin uses two ways to upload the file:
//		- The first one uses classic functionality of an HTML form (highest compatibility with browsers but few capabilities).
//		- The second uses ajax (HTML5) functionality (medium compatibility with browsers but many capabilities, like no page refresh and progress bar).
//	The plugin loads using ajax functionality by default, however if it detects that ajax functionality is not supported, it will automatically switch to classic functionality. 
//	The next line checks to see if the form was submitted using ajax or classic functionality.
//	If the uploaded file variable stored in $_FILES ends with "_redirected", then it means that ajax functionality is not supported and the plugin must switch to classic functionality. 
	if ( isset($_FILES[$uploadedfile.'_redirected']) ) $params['forceclassic'] = "true";

	if ( $params['forceclassic'] != "true" ) {
		$wordpress_file_upload_output .= wfu_post_plugin_actions($params);
		return $wordpress_file_upload_output."\n";
	}

//	The section below is executed when using classic upload methods
	if ( isset( $_POST[$adminerrorcodes] ) ) {
		$code = $_POST[$adminerrorcodes];
		if ( $code == "" ) $params['adminerrors'] = "";
		elseif ( $code == "1" || $code == "2" || $code == "3" ) $params['adminerrors'] = constant('WFU_ERROR_REDIRECTION_ERRORCODE'.$code);
		else $params['adminerrors'] = WFU_ERROR_REDIRECTION_ERRORCODE0;
	}
	
	$unique_id = ( isset($_POST['uniqueuploadid_'.$sid]) ? sanitize_text_field($_POST['uniqueuploadid_'.$sid]) : "" );
	if ( strlen($unique_id) == 10 ) {

		$params['subdir_selection_index'] = -1;
		if ( isset( $_POST[$hiddeninput] ) ) $params['subdir_selection_index'] = sanitize_text_field($_POST[$hiddeninput]);

		$wfu_process_file_array = wfu_process_files($params, 'no_ajax');
		$safe_output = $wfu_process_file_array["general"]['safe_output'];
		unset($wfu_process_file_array["general"]['safe_output']);
		unset($wfu_process_file_array["general"]['js_script']);

		$wfu_process_file_array_str = wfu_encode_array_to_string($wfu_process_file_array);
		$ProcessUploadComplete_functiondef = 'function(){wfu_ProcessUploadComplete('.$sid.', 1, "'.$wfu_process_file_array_str.'", "no-ajax", "", "", "'.$safe_output.'", ["false", "", "false"]);}';
		$wordpress_file_upload_output .= '<script type="text/javascript">window.onload='.$ProcessUploadComplete_functiondef.'</script>';

	}
	
	$wordpress_file_upload_output .= wfu_post_plugin_actions($params);
	return $wordpress_file_upload_output."\n";
}

function wfu_post_plugin_actions($params) {
	$echo_str = '';

	return $echo_str;
}

?>
