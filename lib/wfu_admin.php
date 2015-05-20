<?php

function wordpress_file_upload_admin_init() {
	$uri = $_SERVER['REQUEST_URI'];
	wp_register_style( 'myPluginStylesheet', plugins_url('stylesheet.css', __FILE__) );
	if ( is_admin() && strpos($uri, "options-general.php") !== false ) {
		wp_register_style('wordpress-file-upload-admin-style', WPFILEUPLOAD_DIR.'css/wordpress_file_upload_adminstyle.css',false,'1.0','all');
		wp_register_script('wordpress_file_upload_admin_script', WPFILEUPLOAD_DIR.'js/wordpress_file_upload_adminfunctions.js', array( 'wp-color-picker' ), false, true);
		wp_register_script('wordpress_file_upload_classname_script', WPFILEUPLOAD_DIR.'js/getElementsByClassName-1.0.1.js');
	}
}

function wordpress_file_upload_add_admin_pages() {
	$page_hook_suffix = add_options_page('Wordpress File Upload', 'Wordpress File Upload', 'manage_options', 'wordpress_file_upload', 'wordpress_file_upload_manage_dashboard');
	add_action('admin_print_scripts-'.$page_hook_suffix, 'wfu_enqueue_admin_scripts');
}

function wfu_enqueue_admin_scripts() {
	$uri = $_SERVER['REQUEST_URI'];
	if ( is_admin() && strpos($uri, "options-general.php") !== false ) {
		wp_enqueue_style('wordpress-file-upload-admin-style');
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script('wordpress_file_upload_admin_script');
		wp_enqueue_script('wordpress_file_upload_classname_script');
		$AdminParams = array("wfu_ajax_url" => site_url()."/wp-admin/admin-ajax.php");
		wp_localize_script( 'wordpress_file_upload_admin_script', 'AdminParams', $AdminParams );
	}
}

function wordpress_file_upload_install() {
	global $wpdb;
	global $wfu_tb_log_version;
	global $wfu_tb_userdata_version;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$table_name1 = $wpdb->prefix . "wfu_log";
	$installed_ver = get_option( "wordpress_file_upload_table_log_version" );
	if( $installed_ver != $wfu_tb_log_version ) {
		$sql = "CREATE TABLE " . $table_name1 . " ( 
			idlog mediumint(9) NOT NULL AUTO_INCREMENT,
			userid mediumint(9) NOT NULL,
			uploaduserid mediumint(9) NOT NULL,
			filepath TEXT NOT NULL,
			filehash VARCHAR(100) NOT NULL,
			filesize bigint NOT NULL,
			uploadid VARCHAR(20) NOT NULL,
			pageid mediumint(9),
			sid VARCHAR(10),
			date_from DATETIME,
			date_to DATETIME,
			action VARCHAR(20) NOT NULL,
			linkedto mediumint(9),
			PRIMARY KEY  (idlog))
			DEFAULT CHARACTER SET = utf8
			DEFAULT COLLATE = utf8_general_ci;";
		dbDelta($sql);
		update_option("wordpress_file_upload_table_log_version", $wfu_tb_log_version);
	}

	$table_name2 = $wpdb->prefix . "wfu_userdata";
	$installed_ver = get_option( "wordpress_file_upload_table_userdata_version" );
	if( $installed_ver != $wfu_tb_userdata_version ) {
		$sql = "CREATE TABLE " . $table_name2 . " ( 
			iduserdata mediumint(9) NOT NULL AUTO_INCREMENT,
			uploadid VARCHAR(20) NOT NULL,
			property VARCHAR(100) NOT NULL,
			propkey mediumint(9) NOT NULL,
			propvalue TEXT,
			date_from DATETIME,
			date_to DATETIME,
			PRIMARY KEY  (iduserdata))
			DEFAULT CHARACTER SET = utf8
			DEFAULT COLLATE = utf8_general_ci;";
		dbDelta($sql);
		update_option("wordpress_file_upload_table_userdata_version", $wfu_tb_userdata_version);
	}
}

function wordpress_file_upload_update_db_check() {
	global $wfu_tb_log_version;
	global $wfu_tb_userdata_version;
//	update_option("wordpress_file_upload_table_log_version", "0");
//	update_option("wordpress_file_upload_table_userdata_version", "0");
	if ( get_option('wordpress_file_upload_table_log_version') != $wfu_tb_log_version || get_option('wordpress_file_upload_table_userdata_version') != $wfu_tb_userdata_version ) {
		wordpress_file_upload_install();
	}
}

// This is the callback function that generates dashboard page content
function wordpress_file_upload_manage_dashboard() {
	$_POST = stripslashes_deep($_POST);
	$_GET = stripslashes_deep($_GET);
	$action = (!empty($_POST['action']) ? $_POST['action'] : (!empty($_GET['action']) ? $_GET['action'] : ''));
	$dir = (!empty($_POST['dir']) ? $_POST['dir'] : (!empty($_GET['dir']) ? $_GET['dir'] : ''));
	$file = (!empty($_POST['file']) ? $_POST['file'] : (!empty($_GET['file']) ? $_GET['file'] : ''));
	$data_enc = (!empty($_POST['data']) ? $_POST['data'] : (!empty($_GET['data']) ? $_GET['data'] : ''));
	$echo_str = "";

	if ( $action == 'edit_settings' ) {
		wfu_update_settings();
		$echo_str = wfu_manage_settings();
	}
	elseif ( $action == 'shortcode_composer' ) {
		$echo_str = wfu_shortcode_composer();
	}
	elseif ( $action == 'file_browser' ) {
		$echo_str = wfu_browse_files($dir);
	}
	elseif ( $action == 'view_log' ) {
		$echo_str = wfu_view_log();
	}
	elseif ( $action == 'rename_file' && $file != "" ) {
		$echo_str = wfu_rename_file_prompt($file, 'file', false);
	}
	elseif ( $action == 'rename_dir' && $file != "" ) {
		$echo_str = wfu_rename_file_prompt($file, 'dir', false);
	}
	elseif ( $action == 'renamefile' && $file != "" ) {
		if ( wfu_rename_file($file, 'file') ) $echo_str = wfu_browse_files($dir);
		else $echo_str = wfu_rename_file_prompt($file, 'file', true);
	}
	elseif ( $action == 'renamedir' && $file != "" ) {
		if ( wfu_rename_file($file, 'dir') ) $echo_str = wfu_browse_files($dir);
		else $echo_str = wfu_rename_file_prompt($file, 'dir', true);
	}
	elseif ( $action == 'delete_file' && $file != "" ) {
		$echo_str = wfu_delete_file_prompt($file, 'file');
	}
	elseif ( $action == 'delete_dir' && $file != "" ) {
		$echo_str = wfu_delete_file_prompt($file, 'dir');
	}
	elseif ( $action == 'deletefile' && $file != "" ) {
		wfu_delete_file($file, 'file');
		$echo_str = wfu_browse_files($dir);		
	}
	elseif ( $action == 'deletedir' && $file != "" ) {
		wfu_delete_file($file, 'dir');
		$echo_str = wfu_browse_files($dir);		
	}
	elseif ( $action == 'create_dir' ) {
		$echo_str = wfu_create_dir_prompt($dir, false);
	}
	elseif ( $action == 'createdir' ) {
		if ( wfu_create_dir($dir) ) $echo_str = wfu_browse_files($dir);
		else $echo_str = wfu_create_dir_prompt($dir, true);
	}
	elseif ( $action == 'file_details' && $file != "" ) {
		$echo_str = wfu_file_details($file, false);
	}
	elseif ( $action == 'edit_filedetails' && $file != "" ) {
		wfu_edit_filedetails($file);
		$echo_str = wfu_file_details($file, false);
	}
	elseif ( $action == 'sync_db' ) {
		$affected_items = wfu_sync_database();
		$echo_str = wfu_manage_mainmenu('Database updated. '.$affected_items.' items where affected.');
	}
	elseif ( $action == 'plugin_settings' ) {
		$echo_str = wfu_manage_settings();	
	}
	elseif ( $action == 'edit_shortcode' && $data_enc != "" ) {
		$data = wfu_decode_array_from_string($data_enc);
		if ( wfu_check_edit_shortcode($data) ) wfu_shortcode_composer($data);
		else $echo_str = wfu_manage_mainmenu(WFU_DASHBOARD_EDIT_SHORTCODE_REJECTED);
	}
	elseif ( $action == 'delete_shortcode' && $data_enc != "" ) {
		$data = wfu_decode_array_from_string($data_enc);
		if ( wfu_check_edit_shortcode($data) ) $echo_str = wfu_delete_shortcode_prompt($data);
		else $echo_str = wfu_manage_mainmenu(WFU_DASHBOARD_DELETE_SHORTCODE_REJECTED);
	}
	elseif ( $action == 'deleteshortcode' && $data_enc != "" ) {
		$data = wfu_decode_array_from_string($data_enc);
		if ( wfu_check_edit_shortcode($data) ) {
			wfu_delete_shortcode($data);
			$echo_str = wfu_manage_mainmenu();
		}
		else $echo_str = wfu_manage_mainmenu(WFU_DASHBOARD_DELETE_SHORTCODE_REJECTED);
	}
	else {
		$echo_str = wfu_manage_mainmenu();		
	}

	echo $echo_str;
}

function wfu_manage_mainmenu($message = '') {
	if ( !current_user_can( 'manage_options' ) ) return;
	
	//get php version
	$php_version = preg_replace("/-.*/", "", phpversion());

	$siteurl = site_url();
	$plugin_options = wfu_decode_plugin_options(get_option( "wordpress_file_upload_options" ));
	
	$echo_str = '<div class="wrap">';
	$echo_str .= "\n\t".'<h2>Wordpress File Upload Control Panel</h2>';
	if ( $message != '' ) {
		$echo_str .= "\n\t".'<div class="updated">';
		$echo_str .= "\n\t\t".'<p>'.$message.'</p>';
		$echo_str .= "\n\t".'</div>';
	}
	$echo_str .= "\n\t".'<div style="margin-top:20px;">';
	if ( current_user_can( 'manage_options' ) ) $echo_str .= "\n\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=plugin_settings" class="button" title="Settings">Settings</a>';
	if ( current_user_can( 'manage_options' ) ) $echo_str .= "\n\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=file_browser" class="button" title="File browser">File Browser</a>';
	if ( current_user_can( 'manage_options' ) ) $echo_str .= "\n\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=shortcode_composer" class="button" title="Shortcode composer">Shortcode Composer</a>';
	if ( current_user_can( 'manage_options' ) ) $echo_str .= "\n\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=view_log" class="button" title="View log">View Log</a>';
	if ( current_user_can( 'manage_options' ) ) $echo_str .= "\n\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=sync_db" class="button" title="Update database to reflect current status of files">Sync Database</a>';
	$echo_str .= "\n\t\t".'<h3 style="margin-bottom: 10px; margin-top: 40px;">Status</h3>';
	$echo_str .= "\n\t\t".'<table class="form-table">';
	$echo_str .= "\n\t\t\t".'<tbody>';
	//plugin edition
	$echo_str .= "\n\t\t\t\t".'<tr class="form-field">';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="row">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label style="cursor:default;">Edition</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<td style="width:100px;">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label style="font-weight:bold; cursor:default;">Free</label>';
	$echo_str .= "\n\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t\t".'<td>';
	$echo_str .= "\n\t\t\t\t\t\t".'<div style="display:inline-block; background-color:bisque; padding:0 0 0 4px; border-left:3px solid lightcoral;">';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<label style="cursor:default;">Consider </label><a href="'.WFU_PRO_VERSION_URL.'">Upgrading</a><label style="cursor:default;"> to the Professional Version. </label>';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<button onclick="if (this.innerText == \'See why >>\') {this.innerText = \'<< Close\'; document.getElementById(\'wfu_version_comparison\').style.display = \'block\';} else {this.innerText = \'See why >>\'; document.getElementById(\'wfu_version_comparison\').style.display = \'none\';}">See why >></button>';
	$echo_str .= "\n\t\t\t\t\t\t".'</div>';
	$echo_str .= "\n\t\t\t\t\t\t".'<div id="wfu_version_comparison" style="display:none; background-color:lightyellow; border:1px solid yellow; margin:10px 0; padding:10px;">';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<img src="'.WFU_IMAGE_VERSION_COMPARISON.'" width="65%" style="display:block; margin-bottom:6px;" />';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<a class="button-primary" href="'.WFU_PRO_VERSION_URL.'">Go for the PRO version</a>';
	$echo_str .= "\n\t\t\t\t\t\t".'</div>';
	$echo_str .= "\n\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t".'</tr>';
	//plugin version
	$echo_str .= "\n\t\t\t\t".'<tr class="form-field">';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="row">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label style="cursor:default;">Version</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<td style="width:100px;">';
	$cur_version = wfu_get_plugin_version();
	$echo_str .= "\n\t\t\t\t\t\t".'<label style="font-weight:bold; cursor:default;">'.$cur_version.'</label>';
	$echo_str .= "\n\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t\t".'<td>';
	$lat_version = wfu_get_latest_version();
	$ret = wfu_compare_versions($cur_version, $lat_version);
	if ( $ret['status'] && $ret['result'] == 'lower' ) {
		$echo_str .= "\n\t\t\t\t\t\t".'<div style="display:inline-block; background-color:bisque; padding:0 0 0 4px; border-left:3px solid lightcoral;">';
		$echo_str .= "\n\t\t\t\t\t\t\t".'<label style="cursor:default;">Version <strong>'.$lat_version.'</strong> of the plugin is available. Go to Plugins page of your Dashboard to update to the latest version.</label>';
		if ( $ret['custom'] ) $echo_str .= '<label style="cursor:default; color: purple;"> <em>Please note that you are using a custom version of the plugin. If you upgrade to the newest version, custom changes will be lost.</em></label>';
		$echo_str .= "\n\t\t\t\t\t\t".'</div>';
	}
	elseif ( $ret['status'] && $ret['result'] == 'equal' ) {
		$echo_str .= "\n\t\t\t\t\t\t".'<div style="display:inline-block; background-color:rgb(220,255,220); padding:0 0 0 4px; border-left:3px solid limegreen;">';
		$echo_str .= "\n\t\t\t\t\t\t\t".'<label style="cursor:default;">You have the latest version.</label>';
		if ( $ret['custom'] ) $echo_str .= '<label style="cursor:default; color: purple;"> <em>(Please note that your version is custom)</em></label>';
		$echo_str .= "\n\t\t\t\t\t\t".'</div>';
	}
	$echo_str .= "\n\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t".'</tr>';
	//server environment
	$php_env = wfu_get_server_environment();
	$echo_str .= "\n\t\t\t\t".'<tr class="form-field">';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="row">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label style="cursor:default;">Server Environment</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<td style="width:100px;">';
	if ( $php_env == '64bit' ) $echo_str .= "\n\t\t\t\t\t\t".'<label style="font-weight:bold; cursor:default;">64bit</label></td><td><label style="font-weight:normal; font-style:italic; cursor:default;">(Your server supports files up to 1 Exabyte, practically unlimited)</label>';
	if ( $php_env == '32bit' ) $echo_str .= "\n\t\t\t\t\t\t".'<label style="font-weight:bold; cursor:default;">32bit</label></td><td><label style="font-weight:normal; font-style:italic; cursor:default;">(Your server does not support files larger than 2GB)</label>';
	if ( $php_env == '' ) $echo_str .= "\n\t\t\t\t\t\t".'<label style="font-weight:bold; cursor:default;">Unknown</label></td><td><label style="font-weight:normal; font-style:italic; cursor:default;">(The maximum file size supported by the server cannot be determined)</label>';
	$echo_str .= "\n\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t".'</tr>';
	$echo_str .= "\n\t\t\t\t".'<tr class="form-field">';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="row">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label style="cursor:default;">PHP Version</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<td style="width:100px;">';
	$cur_version = wfu_get_plugin_version();
	$echo_str .= "\n\t\t\t\t\t\t".'<label style="font-weight:bold; cursor:default;">'.$php_version.'</label>';
	$echo_str .= "\n\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t\t".'<td>';
	$echo_str .= "\n\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t".'</tr>';
	$echo_str .= "\n\t\t\t\t".'<tr class="form-field">';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="row">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label style="cursor:default;">Release Notes</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<td colspan="2" style="width:100px;">';
	$rel_path = ABSWPFILEUPLOAD_DIR.'release_notes.txt';
	$rel_notes = '';
	if ( file_exists($rel_path) ) $rel_notes = file_get_contents($rel_path);
	$echo_str .= "\n\t\t\t\t\t\t".'<div style="text-align:justify;">'.$rel_notes.'</div>';
	$echo_str .= "\n\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t".'</tr>';
	$echo_str .= "\n\t\t\t".'</tbody>';
	$echo_str .= "\n\t\t".'</table>';

	$echo_str .= wfu_manage_instances();

	$echo_str .= "\n\t".'</div>';
	$echo_str .= "\n".'</div>';
	
	echo $echo_str;
}

function wfu_manage_instances() {
	$siteurl = site_url();
	$args = array( 'post_type' => array( "post", "page" ), 'post_status' => "publish,private,draft", 'posts_per_page' => -1 );
	$posts = get_posts($args);
	$wfu_shortcodes = array();
	foreach ( $posts as $post ) {
		$ret = wfu_get_content_shortcodes($post, 'wordpress_file_upload');
		if ( $ret !== false ) $wfu_shortcodes = array_merge($wfu_shortcodes, $ret);
	}

	$echo_str = "\n\t\t".'<h3 style="margin-bottom: 10px; margin-top: 40px;">Plugin Instances</h3>';
	$echo_str .= "\n\t\t".'<table class="widefat">';
	$echo_str .= "\n\t\t\t".'<thead>';
	$echo_str .= "\n\t\t\t\t".'<tr>';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="col" width="5%" style="text-align:center;">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label>#</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="col" width="10%" style="text-align:center;">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label>ID</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="col" width="10%" style="text-align:center;">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label>Type</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="col" width="30%" style="text-align:center;">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label>Title</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t".'<th scope="col" width="45%" style="text-align:center;">';
	$echo_str .= "\n\t\t\t\t\t\t".'<label>Shortcode</label>';
	$echo_str .= "\n\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t".'</tr>';
	$echo_str .= "\n\t\t\t".'</thead>';
	$echo_str .= "\n\t\t\t".'<tbody>';
	$i = 1;
	foreach ( $wfu_shortcodes as $key => $data ) {
		$id = $data['post_id'];
		$data_enc = wfu_encode_array_to_string($data);
		$echo_str .= "\n\t\t\t\t".'<tr onmouseover="for (i in document.getElementsByName(\'wfu_shortcode_actions\')){document.getElementsByName(\'wfu_shortcode_actions\').item(i).style.visibility=\'hidden\';} document.getElementById(\'wfu_shortcode_actions_'.$i.'\').style.visibility=\'visible\'" onmouseout="for (i in document.getElementsByName(\'wfu_shortcode_actions\')){document.getElementsByName(\'wfu_shortcode_actions\').item(i).style.visibility=\'hidden\';}">';
		$echo_str .= "\n\t\t\t\t\t".'<td style="padding: 5px 5px 5px 10px; text-align:center;">';
		$echo_str .= "\n\t\t\t\t\t\t".'<a class="row-title" href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&action=edit_shortcode&data='.$data_enc.'" title="Plugin #'.$i.'">Plugin '.$i.'</a>';
		$echo_str .= "\n\t\t\t\t\t\t".'<div id="wfu_shortcode_actions_'.$i.'" name="wfu_shortcode_actions" style="visibility:hidden;">';
		$echo_str .= "\n\t\t\t\t\t\t\t".'<span>';
		$echo_str .= "\n\t\t\t\t\t\t\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&action=edit_shortcode&data='.$data_enc.'" title="Edit this shortcode">Edit</a>';
		$echo_str .= "\n\t\t\t\t\t\t\t\t".' | ';
		$echo_str .= "\n\t\t\t\t\t\t\t".'</span>';
		$echo_str .= "\n\t\t\t\t\t\t\t".'<span>';
		$echo_str .= "\n\t\t\t\t\t\t\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&action=delete_shortcode&data='.$data_enc.'" title="Delete this shortcode">Delete</a>';
		$echo_str .= "\n\t\t\t\t\t\t\t".'</span>';
		$echo_str .= "\n\t\t\t\t\t\t".'</div>';
		$echo_str .= "\n\t\t\t\t\t".'</td>';
		$echo_str .= "\n\t\t\t\t\t".'<td style="padding: 5px 5px 5px 10px; text-align:center;">'.$id.'</td>';
		$echo_str .= "\n\t\t\t\t\t".'<td style="padding: 5px 5px 5px 10px; text-align:center;">'.get_post_type($id).'</td>';
		$echo_str .= "\n\t\t\t\t\t".'<td style="padding: 5px 5px 5px 10px; text-align:center;">'.get_the_title($id).'</td>';
		$echo_str .= "\n\t\t\t\t\t".'<td style="padding: 5px 5px 5px 10px; text-align:left;">';
		$echo_str .= "\n\t\t\t\t\t\t".'<textarea rows="3" disabled="disabled" style="width:100%;">'.trim($data['shortcode']).'</textarea>';
		$echo_str .= "\n\t\t\t\t\t".'</td>';
		$echo_str .= "\n\t\t\t\t".'</tr>';
		$i++;
	}
	$echo_str .= "\n\t\t\t".'</tbody>';
	$echo_str .= "\n\t\t".'</table>';
	
	return $echo_str;
}

function wfu_get_content_shortcodes($post, $tag) {
	$ret = array();
	$content = $post->post_content;
	$hash = '';
	if ( false === strpos( $content, '[' ) ) {
		return false;
	}

	if ( shortcode_exists( $tag ) ) {
		preg_match_all( '/' . get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		if ( empty( $matches ) )
			return false;

		foreach ( $matches as $shortcode ) {
			if ( $tag === $shortcode[2][0] ) {
				$data['post_id'] = $post->ID;
				if ( $hash == '' ) $hash = hash('md5', $content);
				$data['post_hash'] = $hash;
				$data['shortcode'] = $shortcode[0][0];
				$data['position'] = $shortcode[0][1];
				array_push($ret, $data);
			}
		}
	}
	if ( count($ret) == 0 ) return false;
	return $ret;
}

function wfu_check_edit_shortcode($data) {
	$post = get_post($data['post_id']);
	$content = $post->post_content;
	$hash = hash('md5', $content);
	
	return ( $hash == $data['post_hash'] );
}

function wfu_replace_shortcode($data, $new_shortcode) {
	$post = get_post($data['post_id']);
	$new_content = substr($post->post_content, 0, $data['position']).$new_shortcode.substr($post->post_content, (int)$data['position'] + strlen($data['shortcode']));
	$new_post = array( 'ID' => $data['post_id'], 'post_content' => $new_content );
	return ( wp_update_post( $new_post ) === 0 ? false : true );
}

function wfu_delete_shortcode_prompt($data) {
	$siteurl = site_url();
	$postid = $data['post_id'];
	$data_enc = wfu_encode_array_to_string($data);
	$echo_str = "\n".'<div class="wrap">';
	$echo_str .= "\n\t".'<h2>Wordpress File Upload Control Panel</h2>';
	$echo_str .= "\n\t".'<div style="margin-top:20px;">';
	$echo_str .= "\n\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=manage_mainmenu" class="button" title="go back">Go to Main Menu</a>';
	$echo_str .= "\n\t".'</div>';
	$echo_str .= "\n\t".'<h2 style="margin-bottom: 10px; margin-top: 20px;">Delete Shortcode</h2>';
	$echo_str .= "\n\t".'<form enctype="multipart/form-data" name="deletefile" id="deleteshortcode" method="post" action="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload" class="validate">';
	$echo_str .= "\n\t\t".'<input type="hidden" name="action" value="deleteshortcode">';
	$echo_str .= "\n\t\t".'<input type="hidden" name="data" value="'.$data_enc.'">';
	$echo_str .= "\n\t\t".'<label>Are you sure that you want to delete shortcode for <strong>'.get_post_type($postid).' "'.get_the_title($postid).'" ('.$postid.') Position '.$data['position'].'</strong> ?</label><br/>';
	$echo_str .= "\n\t\t".'<p class="submit">';
	$echo_str .= "\n\t\t\t".'<input type="submit" class="button-primary" name="submit" value="Delete">';
	$echo_str .= "\n\t\t\t".'<input type="submit" class="button-primary" name="submit" value="Cancel">';
	$echo_str .= "\n\t\t".'</p>';
	$echo_str .= "\n\t".'</form>';
	$echo_str .= "\n".'</div>';
	return $echo_str;
}

function wfu_delete_shortcode($data) {
	//check if user is allowed to perform this action
	if ( !current_user_can( 'manage_options' ) ) return false;

	$res = true;
	if ( isset($_POST['submit']) ) {
		if ( $_POST['submit'] == "Delete" ) {
			$res = wfu_replace_shortcode($data, '');
		}
	}
	return $res;
}

function wfu_manage_settings($message = '') {
	if ( !current_user_can( 'manage_options' ) ) return wfu_shortcode_composer();

	$siteurl = site_url();
	$plugin_options = wfu_decode_plugin_options(get_option( "wordpress_file_upload_options" ));
	
	$echo_str = '<div class="wrap">';
	$echo_str .= "\n\t".'<h2>Wordpress File Upload Control Panel</h2>';
	$echo_str .= "\n\t".'<div style="margin-top:20px;">';
	$echo_str .= "\n\t\t".'<a href="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=manage_mainmenu" class="button" title="go back">Go to Main Menu</a>';
	$echo_str .= "\n\t\t".'<h2 style="margin-bottom: 10px; margin-top: 20px;">Settings</h2>';
	$echo_str .= "\n\t\t".'<form enctype="multipart/form-data" name="editsettings" id="editsettings" method="post" action="'.$siteurl.'/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=edit_settings" class="validate">';
	$nonce = wp_nonce_field('wfu_edit_admin_settings', '_wpnonce', false, false);
	$nonce_ref = wp_referer_field(false);
	$echo_str .= "\n\t\t\t".$nonce;
	$echo_str .= "\n\t\t\t".$nonce_ref;
	$echo_str .= "\n\t\t\t".'<input type="hidden" name="action" value="edit_settings">';
	$echo_str .= "\n\t\t\t".'<table class="form-table">';
	$echo_str .= "\n\t\t\t\t".'<tbody>';
	$echo_str .= "\n\t\t\t\t\t".'<tr class="form-field">';
	$echo_str .= "\n\t\t\t\t\t\t".'<th scope="row">';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<label for="wfu_hashfiles">Hash Files</label>';
	$echo_str .= "\n\t\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t\t".'<td>';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<input name="wfu_hashfiles" id="wfu_hashfiles" type="checkbox"'.($plugin_options['hashfiles'] == '1' ? ' checked="checked"' : '' ).' style="width:auto;" /> Enables better control of uploaded files, but slows down performance when uploaded files are larger than 100MBytes';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<p style="cursor: text; font-size:9px; padding: 0px; margin: 0px; width: 95%; color: #AAAAAA;">Current value: <strong>'.($plugin_options['hashfiles'] == '1' ? 'Yes' : 'No' ).'</strong></p>';
	$echo_str .= "\n\t\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t\t".'</tr>';
	$echo_str .= "\n\t\t\t\t\t".'<tr class="form-field">';
	$echo_str .= "\n\t\t\t\t\t\t".'<th scope="row">';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<label for="wfu_basedir">Base Directory</label>';
	$echo_str .= "\n\t\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t\t".'<td>';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<input name="wfu_basedir" id="wfu_basedir" type="text" value="'.$plugin_options['basedir'].'" />';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<p style="cursor: text; font-size:9px; padding: 0px; margin: 0px; width: 95%; color: #AAAAAA;">Current value: <strong>'.$plugin_options['basedir'].'</strong></p>';
	$echo_str .= "\n\t\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t\t".'</tr>';
	$echo_str .= "\n\t\t\t\t".'</tbody>';
	$echo_str .= "\n\t\t\t".'</table>';
	$echo_str .= "\n\t\t\t".'<p class="submit">';
	$echo_str .= "\n\t\t\t\t".'<input type="submit" class="button-primary" name="submit" value="Update" />';
	$echo_str .= "\n\t\t\t".'</p>';
	$echo_str .= "\n\t\t".'</form>';
	$echo_str .= "\n\t".'</div>';
	$echo_str .= "\n".'</div>';
	
	echo $echo_str;
}

function wfu_update_settings() {
	if ( !current_user_can( 'manage_options' ) ) return;
	if ( !check_admin_referer('wfu_edit_admin_settings') ) return;
	$plugin_options = wfu_decode_plugin_options(get_option( "wordpress_file_upload_options" ));
	$new_plugin_options = array();

//	$enabled = ( isset($_POST['wfu_enabled']) ? ( $_POST['wfu_enabled'] == "on" ? 1 : 0 ) : 0 ); 
	$hashfiles = ( isset($_POST['wfu_hashfiles']) ? ( $_POST['wfu_hashfiles'] == "on" ? 1 : 0 ) : 0 ); 
	if ( isset($_POST['wfu_basedir']) && isset($_POST['wfu_captcha_sitekey']) && isset($_POST['wfu_captcha_secretkey']) && isset($_POST['submit']) ) {
		if ( $_POST['submit'] == "Update" ) {
			$new_plugin_options['version'] = '1.0';
			$new_plugin_options['shortcode'] = $plugin_options['shortcode'];
			$new_plugin_options['hashfiles'] = $hashfiles;
			$new_plugin_options['basedir'] = $_POST['wfu_basedir'];
			$encoded_options = wfu_encode_plugin_options($new_plugin_options);
			update_option( "wordpress_file_upload_options", $encoded_options );
			if ( $new_plugin_options['hashfiles'] == '1' && $plugin_options['hashfiles'] != '1' )
				wfu_reassign_hashes();
		}
	}

	return true;
}

?>
