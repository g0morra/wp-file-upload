<?php

//********************* String Functions *******************************************************************************************************

function wfu_upload_plugin_clean($label) {
	/**
	 * Regular expressions to change some characters.
	 */

	$search = array ('@[eeeeEE]@i','@[aaaAA]@i','@[iiII]@i','@[uuuUU]@i','@[ooOO]@i',
	'@[c]@i','@[^a-zA-Z0-9._]@');	 
	$replace = array ('e','a','i','u','o','c','-');
	$label =  preg_replace($search, $replace, $label);
	$label = strtolower($label); // Convert in lower case
	return $label;
}

function wfu_upload_plugin_wildcard_to_preg($pattern) {
	return '/^' . str_replace(array('\*', '\?', '\[', '\]'), array('.*', '.', '[', ']+'), preg_quote($pattern)) . '$/is';
}

function wfu_upload_plugin_wildcard_match($pattern, $str) {
	$pattern = wfu_upload_plugin_wildcard_to_preg($pattern);
	return preg_match($pattern, $str);
}

function wfu_plugin_encode_string($string) {
	$array = unpack('C*', $string);
	$new_string = "";	
	for ($i = 1; $i <= count($array); $i ++) {
		$new_string .= sprintf("%02X", $array[$i]);
	}
	return $new_string;
}

function wfu_plugin_decode_string($string) {
	$new_string = "";	
	for ($i = 0; $i < strlen($string); $i += 2 ) {
		$new_string .= sprintf("%c", hexdec(substr($string, $i ,2)));
	}
	return $new_string;
}

function wfu_create_random_string($len) {
	$base = 'ABCDEFGHKLMNOPQRSTWXYZabcdefghjkmnpqrstwxyz123456789';
	$max = strlen($base) - 1;
	$activatecode = '';
	mt_srand((double)microtime()*1000000);
	while (strlen($activatecode) < $len)
		$activatecode .= $base{mt_rand(0, $max)};
	return $activatecode;
}

function wfu_join_strings($delimeter) {
	$arr = func_get_args();
	unset($arr[0]);
	foreach ($arr as $key => $item)
		if ( $item == "" ) unset($arr[$key]);
	return join($delimeter, $arr);
}

//********************* Array Functions *****************************************************************************************************

function wfu_encode_array_to_string($arr) {
	$arr_str = json_encode($arr);
	$arr_str = wfu_plugin_encode_string($arr_str);
	return $arr_str;
}

function wfu_decode_array_from_string($arr_str) {
	$arr_str = wfu_plugin_decode_string($arr_str);
	$arr = json_decode($arr_str, true);
	return $arr;
}

function wfu_plugin_parse_array($source) {
	$keys = array_keys($source);
	$new_arr = array();
	for ($i = 0; $i < count($keys); $i ++) 
		$new_arr[$keys[$i]] = wp_specialchars_decode($source[$keys[$i]]);
	return $new_arr;
}

function wfu_array_remove_nulls(&$arr) {
	foreach ( $arr as $key => $arri )
		if ( $arri == null )
			array_splice($arr, $key, 1);
}

//********************* Directory Functions ************************************************************************************************

function wfu_upload_plugin_full_path( $params ) {
	$path = $params["uploadpath"];
	if ( $params["accessmethod"]=='ftp' && $params["ftpinfo"] != '' && $params["useftpdomain"] == "true" ) {
		$ftpdata_flat =  str_replace(array('\:', '\@'), array('\_', '\_'), $params["ftpinfo"]);
		$pos1 = strpos($ftpdata_flat, ":");
		$pos2 = strpos($ftpdata_flat, "@");
		if ( $pos1 && $pos2 && $pos2 > $pos1 ) {
			$ftp_username = substr($params["ftpinfo"], 0, $pos1);
			$ftp_password = substr($params["ftpinfo"], $pos1 + 1, $pos2 - $pos1 - 1);
			$ftp_host = substr($params["ftpinfo"], $pos2 + 1);
			$ftp_username = str_replace('@', '%40', $ftp_username);   //if username contains @ character then convert it to %40
			$ftp_password = str_replace('@', '%40', $ftp_password);   //if password contains @ character then convert it to %40
			$start_folder = 'ftp://'.$ftp_username.':'.$ftp_password."@".$ftp_host.'/';
		}
		else $start_folder = 'ftp://'.$params["ftpinfo"].'/';
	}
	else $start_folder = WP_CONTENT_DIR.'/';
	if ($path) {
		if ( $path == ".." || substr($path, 0, 3) == "../" ) {
			$start_folder = ABSPATH;
			$path = substr($path, 2, strlen($path) - 2);
		}
		if ( substr($path, 0, 1) == "/" ) $path = substr($path, 1, strlen($path) - 1);
		if ( substr($path, -1, 1) == "/" ) $path = substr($path, 0, strlen($path) - 1);
		$full_upload_path = $start_folder;
		if ( $path != "" ) $full_upload_path .= $path.'/';
	}
	else {
		$full_upload_path = $start_folder;
	}
	return $full_upload_path;
}

function wfu_upload_plugin_directory( $path ) {
	$dirparts = explode("/", $path);
	return $dirparts[count($dirparts) - 1];
}

//********************* User Functions *********************************************************************************************************

function wfu_get_user_role($user, $param_roles) {
	if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
		/* Go through the array of the roles of the current user */
		foreach ( $user->roles as $user_role ) {
			$user_role = strtolower($user_role);
			/* If one role of the current user matches to the roles allowed to upload */
			if ( in_array($user_role, $param_roles) || $user_role == 'administrator' ) {
				/*  We affect this role to current user */
				$result_role = $user_role;
				break;
			}
			else {
				/* We affect the 'visitor' role to current user */
				$result_role = 'visitor';
			}
		}
	}
	else {
		$result_role = 'visitor';
	}
	return $result_role;		
}

//********************* Shortcode Options Functions ************************************************************************************************

function wfu_generate_current_params_index($shortcode_id, $user_login) {
	global $post;
	$cur_index_str = '||'.$post->ID.'||'.$shortcode_id.'||'.$user_login;
	$cur_index_str_search = '\|\|'.$post->ID.'\|\|'.$shortcode_id.'\|\|'.$user_login;
	$index_str = get_option('wfu_params_index');
	$index = explode("&&", $index_str);
	foreach ($index as $key => $value) if ($value == "") unset($index[$key]);
	$index_match = preg_grep("/".$cur_index_str_search."$/", $index);
	if ( count($index_match) == 1 && $index_match[0] == "" ) unset($index_match[0]);
	if ( count($index_match) <= 0 ) {
		$cur_index_rand = wfu_create_random_string(16);
		array_push($index, $cur_index_rand.$cur_index_str);
	}
	else {
		$cur_index_rand = substr(current($index_match), 0, 16);
		if ( count($index_match) > 1 ) {
			$index_match_keys = array_keys($index_match);
			for ($i = 1; $i < count($index_match); $i++) {
				$ii = $index_match_keys[$i];
				unset($index[array_search($index_match[$ii], $index, true)]);
			}
		}
	}
	if ( count($index_match) != 1 ) {
		$index_str = implode("&&", $index);
		update_option('wfu_params_index', $index_str);
	}
	return $cur_index_rand;
}

function wfu_get_params_fields_from_index($params_index) {
	$fields = array();
	$index_str = get_option('wfu_params_index');
	$index = explode("&&", $index_str);
	$index_match = preg_grep("/^".$params_index."/", $index);
	if ( count($index_match) == 1 && $index_match[0] == "" ) unset($index_match[0]);
	if ( count($index_match) > 0 )
		list($fields['unique_id'], $fields['page_id'], $fields['shortcode_id'], $fields['user_login']) = explode("||", current($index_match));
	return $fields; 
}

function wfu_decode_dimensions($dimensions_str) {
	$components = wfu_component_definitions();
	$dimensions = array();
	foreach ( $components as $comp ) {
		if ( $comp['dimensions'] == null ) $dimensions[$comp['id']] = "";
		else foreach ( $comp['dimensions'] as $dimraw ) {
			list($dim_id, $dim_name) = explode("/", $dimraw);
			$dimensions[$dim_id] = "";
		}
	}
	$dimensions_raw = explode(",", $dimensions_str);
	foreach ( $dimensions_raw as $dimension_str ) {
		$dimension_raw = explode(":", $dimension_str);
		$item = strtolower(trim($dimension_raw[0]));
		foreach ( array_keys($dimensions) as $key ) {
			if ( $item == $key ) $dimensions[$key] = trim($dimension_raw[1]);
		}
	}
	return $dimensions;
}

//********************* Plugin Design Functions *********************************************************************************************************

function wfu_add_div() {
	$items_count = func_num_args();
	if ( $items_count == 0 ) return "";
	$items_raw = func_get_args();
	$items = array( );
	foreach ( $items_raw as $item_raw ) {
		if ( is_array($item_raw) ) array_push($items, $item_raw);
	}
	$items_count = count($items);
	if ( $items_count == 0 ) return "";
	$div = "";
	$div .= "\n\t".'<div class="file_div_clean">';  
	$div .= "\n\t\t".'<table class="file_table_clean">';
	$div .= "\n\t\t\t".'<tbody>';
	$div .= "\n\t\t\t\t".'<tr>';  
	for ( $i = 0; $i < $items_count; $i++ ) {
		$div .= "\n\t\t\t\t\t".'<td class="file_td_clean"';  
		if ( $i < $items_count - 1 ) $div .= ' style="padding: 0 4px 0 0"';
		$div .= '>';
		$div .= "\n\t\t\t\t\t\t".'<div id="'.$items[$i]["title"].'" class="file_div_clean"';  
		if ( $items[$i]["hidden"] ) $div .= ' style="display: none"';
		$div .= '>';
		$item_lines_count = count($items[$i]) - 2;
		for ( $k = 1; $k <= $item_lines_count; $k++ ) {
			if ( $items[$i]["line".$k] != "" ) $div .= "\n\t\t\t\t\t\t\t".$items[$i]["line".$k];
		}
		$div .= "\n\t\t\t\t\t\t\t".'<div class="file_space_clean" />';  
		$div .= "\n\t\t\t\t\t\t".'</div>';  
		$div .= "\n\t\t\t\t\t".'</td>';  
	}
	$div .= "\n\t\t\t\t".'</tr>';  
	$div .= "\n\t\t\t".'</tbody>';
	$div .= "\n\t\t".'</table>';
	$div .= "\n\t".'</div>';  
	return $div;
}

//********************* Email Functions **************************************************************************************************************

function wfu_send_notification_email($user, $only_filename_list, $target_path_list, $attachment_list, $userdata_fields, $params) {
	if ( 0 == $user->ID ) {
		$user_login = "guest";
		$user_email = "";
	}
	else {
		$user_login = $user->user_login;
		$user_email = $user->user_email;
	}
	$notifyrecipients =  trim(preg_replace('/%useremail%/', $user_email, $params["notifyrecipients"]));
	$search = array ('/%n%/');	 
	$replace = array ("\n");
	$notifyheaders =  preg_replace($search, $replace, $params["notifyheaders"]);
	$search = array ('/%username%/', '/%useremail%/', '/%filename%/', '/%filepath%/', '/%n%/');	 
	$replace = array ($user_login, ( $user_email == "" ? "no email" : $user_email ), $only_filename_list, $target_path_list, "\n");
	foreach ( $userdata_fields as $userdata_key => $userdata_field ) { 
		$ind = 1 + $userdata_key;
		array_push($search, '/%userdata'.$ind.'%/');  
		array_push($replace, $userdata_field["value"]);
	}   
	$notifysubject =  preg_replace($search, $replace, $params["notifysubject"]);
	$notifymessage =  preg_replace($search, $replace, $params["notifymessage"]);
	if ( $params["attachfile"] == "true" ) {
		$attachments = explode(",", $attachment_list);
		$notify_sent = wp_mail($notifyrecipients, $notifysubject, $notifymessage, $notifyheaders, $attachments); 
	}
	else {
		$notify_sent = wp_mail($notifyrecipients, $notifysubject, $notifymessage, $notifyheaders); 
	}
	return ( $notify_sent ? "" : WFU_WARNING_NOTIFY_NOTSENT_UNKNOWNERROR );
}

?>
