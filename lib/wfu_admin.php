<?php

function wordpress_file_upload_add_admin_pages() {
	add_options_page('Wordpress File Upload', 'Wordpress File Upload', 10, 'wordpress_file_upload', 'wordpress_file_upload_manage_dashboard');
}

// This is the callback function that generates dashboard page content
function wordpress_file_upload_manage_dashboard() {
	global $wpdb;
	$_POST = stripslashes_deep($_POST);
	$_GET = stripslashes_deep($_GET);
	$action = (!empty($_POST['action']) ? $_POST['action'] : (!empty($_GET['action']) ? $_GET['action'] : ''));

	if ( $action == 'edit_settings' ) {
		wfu_update_settings();
		$echo_str = wfu_manage_settings();
	}
	elseif ( $action == 'shortcode_composer' ) {
		$echo_str = wfu_shortcode_composer();
	}
	else {
		$echo_str = wfu_manage_settings();		
	}

	echo $echo_str;
}

function wfu_manage_settings() {
	return wfu_shortcode_composer();

	global $wpdb;
//	$plugin_options = wfu_decode_plugin_options(get_option( "wordpress_file_upload_options" ));
	
	$echo_str = '<div class="wfu_wrap">';
	$echo_str .= "\n\t".'<h2>Wordpress File Upload Control Panel</h2>';
	$echo_str .= "\n\t".'<div style="margin-top:10px;">';
	$echo_str .= "\n\t\t".'<a href="/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=shortcode_composer" class="button" title="Shortcode composer">Shortcode Composer</a>';
	$echo_str .= "\n\t\t".'<h3 style="margin-bottom: 10px; margin-top: 40px;">Settings</h3>';
	$echo_str .= "\n\t\t".'<form enctype="multipart/form-data" name="editsettings" id="editsettings" method="post" action="/wp-admin/options-general.php?page=event_register&amp;action=edit_settings" class="validate">';
	$echo_str .= "\n\t\t\t".'<input type="hidden" name="action" value="edit_settings">';
	$echo_str .= "\n\t\t\t".'<table class="form-table">';
	$echo_str .= "\n\t\t\t\t".'<tbody>';
	$echo_str .= "\n\t\t\t\t\t".'<tr class="form-field">';
	$echo_str .= "\n\t\t\t\t\t\t".'<th scope="row">';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<label for="wfu_testprop">Test Property</label>';
	$echo_str .= "\n\t\t\t\t\t\t".'</th>';
	$echo_str .= "\n\t\t\t\t\t\t".'<td>';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<input name="wfu_testprop" id="wfu_testprop" type="text" value="Test Property" />';
	$echo_str .= "\n\t\t\t\t\t\t\t".'<p style="cursor: text; font-size:9px; padding: 0px; margin: 0px; width: 95%; color: #AAAAAA;">Current value: <strong>Test Property</strong></p>';
	$echo_str .= "\n\t\t\t\t\t\t".'</td>';
	$echo_str .= "\n\t\t\t\t\t".'</tr>';
	$echo_str .= "\n\t\t\t\t".'</tbody>';
	$echo_str .= "\n\t\t\t".'</table>';
	$echo_str .= "\n\t\t\t".'<p class="submit">';
	$echo_str .= "\n\t\t\t\t".'<input type="submit" class="button-primary" name="submit" value="Update" disabled="disabled" />';
	$echo_str .= "\n\t\t\t".'</p>';
	$echo_str .= "\n\t\t".'</form>';
	$echo_str .= "\n\t".'</div>';
	$echo_str .= "\n".'</div>';
	
	echo $echo_str;
}

function wfu_shortcode_composer() {
	global $wpdb;
	global $wp_roles;
 
//	$plugin_options = wfu_decode_plugin_options(get_option( "wordpress_file_upload_options" ));
	$components = wfu_component_definitions();

	$cats = wfu_category_definitions();
	$defs = wfu_attribute_definitions();
	foreach ( $defs as $key => $def ) $defs[$key]['default'] = $def['value'];
	
	// index $components
	$components_indexed = array();
	foreach ( $components as $component ) $components_indexed[$component['id']] = $component;
	// index dependiencies
	$governors = array();

	$echo_str = '<div id="wfu_wrapper" class="wfu_wrap">';
	$echo_str .= "\n\t".'<h2>Wordpress File Upload Shortcode Composer</h2>';
//	$echo_str .= "\n\t\t".'<a href="/wp-admin/options-general.php?page=wordpress_file_upload&amp;action=manage_settings" class="button" title="Go back">Go Back</a>';
	$echo_str .= "\n\t".'<div style="margin-top:10px;">';
	$echo_str .= "\n\t\t".'<div class="wfu_shortcode_container">';
	$echo_str .= "\n\t\t\t".'<span><strong>Generated Shortcode</strong></span>';
	$echo_str .= "\n\t\t\t".'<textarea id="wfu_shortcode" class="wfu_shortcode" rows="5">[wordpress_file_upload]</textarea>';
	$echo_str .= "\n\t\t\t".'<div id="wfu_attribute_defaults" style="display:none;">';
	foreach ( $defs as $def )
		$echo_str .= "\n\t\t\t\t".'<input id="wfu_attribute_default_'.$def['attribute'].'" type="hidden" value="'.$def['default'].'" />';
	$echo_str .= "\n\t\t\t".'</div>';
	$echo_str .= "\n\t\t\t".'<div id="wfu_attribute_values" style="display:none;">';
	foreach ( $defs as $def )
		$echo_str .= "\n\t\t\t\t".'<input id="wfu_attribute_value_'.$def['attribute'].'" type="hidden" value="'.$def['value'].'" />';
	$echo_str .= "\n\t\t\t".'</div>';
	$echo_str .= "\n\t\t".'</div>';
	$echo_str .= "\n\t".'</div>';
	$echo_str .= "\n\t".'<h3 id="wfu_tab_container" class="nav-tab-wrapper">';
	$is_first = true;
	foreach ( $cats as $key => $cat ) {
		$echo_str .= "\n\t\t".'<a id="wfu_tab_'.$key.'" class="nav-tab'.( $is_first ? ' nav-tab-active' : '' ).'" href="javascript: wfu_admin_activate_tab(\''.$key.'\');">'.$cat.'</a>';
		$is_first = false;
	}
	$echo_str .= "\n\t".'</h3>';

	$prevcat = "";
	$prevsubcat = "";
	$is_first = true;
	$block_open = false;
	$subblock_open = false;
	foreach ( $defs as $def ) {
		$attr = $def['attribute'];
		$subblock_active = false;
		//detect if the dependencies of this attribute will be disabled or not
		if ( ( $def['type'] == "onoff" && $def['value'] == "true" ) ||
			( $def['type'] == "radio" && in_array("*".$def['value'], $def['listitems']) ) )
			$subblock_active = true;
		// assign dependencies if exist
		if ( $def['dependencies'] != null )
			foreach ( $def['dependencies'] as $dependency ) {
				if ( substr($dependency, 0, 1) == "!" ) //invert state for this dependency if an exclamation mark is defined
					$governors[substr($dependency, 1)] = array( 'attribute' => $attr, 'active' => !$subblock_active, 'inv' => '_inv' );
				else
					$governors[$dependency] = array( 'attribute' => $attr, 'active' => $subblock_active, 'inv' => '' );
			}
		//check if this attribute depends on other
		if ( $governors[$attr] != "" ) $governor = $governors[$attr];
		else $governor = array( 'attribute' => "independent", 'active' => true, 'inv' => '' );

		//close previous blocks
		if ( $def['parent'] == "" ) {
			if ( $subblock_open ) {
				$echo_str .= "\n\t\t\t\t\t\t\t".'</tbody>';
				$echo_str .= "\n\t\t\t\t\t\t".'</table>';
				$subblock_open = false;
			}
			if ( $block_open ) {
				$echo_str .= "\n\t\t\t\t\t".'</div></td>';
				$echo_str .= "\n\t\t\t\t".'</tr>';
				$block_open = false;
			}
		}
		//check if new category must be generated
		if ( $def['category'] != $prevcat ) {
			if ( $prevcat != "" ) {
				$echo_str .= "\n\t\t\t".'</tbody>';
				$echo_str .= "\n\t\t".'</table>';
				$echo_str .= "\n\t".'</div>';
			}
			$prevcat = $def['category'];
			$prevsubcat = "";
			$echo_str .= "\n\t".'<div id="wfu_container_'.$prevcat.'" class="wfu_container"'.( $is_first ? '' : ' style="display:none;"' ).'">';
			$echo_str .= "\n\t\t".'<table class="form-table wfu_main_table">';
			$echo_str .= "\n\t\t\t".'<thead><tr><th></th><td></td><td></td></tr></thead>';
			$echo_str .= "\n\t\t\t".'<tbody>';
			$is_first = false;
		}
		//check if new sub-category must be generated
		if ( $def['subcategory'] != $prevsubcat ) {
			$prevsubcat = $def['subcategory'];
			$echo_str .= "\n\t\t\t\t".'<tr class="form-field wfu_subcategory">';
			$echo_str .= "\n\t\t\t\t\t".'<th scope="row" colspan="3">';
			$echo_str .= "\n\t\t\t\t\t\t".'<h3 style="margin-bottom: 10px; margin-top: 10px;">'.$prevsubcat.'</h3>';
			$echo_str .= "\n\t\t\t\t\t".'</th>';
			$echo_str .= "\n\t\t\t\t".'</tr>';
		}
		//draw attribute element
		if ( $def['parent'] == "" ) {
			$dlp = "\n\t\t\t\t";
		}
		else {
			if ( !$subblock_open ) {
				$echo_str .= "\n\t\t\t\t\t\t".'<div class="wfu_shadow wfu_shadow_'.$def['parent'].$governor['inv'].'" style="display:'.( $governor['active'] ? 'none' : 'block' ).';"></div>';
				$echo_str .= "\n\t\t\t\t\t\t".'<table class="form-table wfu_inner_table" style="margin:0;">';
				$echo_str .= "\n\t\t\t\t\t\t\t".'<tbody>';
			}
			$dlp = "\n\t\t\t\t\t\t\t\t";
		}
		$echo_str .= $dlp.'<tr class="form-field">';
		$echo_str .= $dlp."\t".'<th scope="row"><div class="wfu_td_div">';
		if ( $def['parent'] == "" ) $echo_str .= $dlp."\t\t".'<div class="wfu_shadow wfu_shadow_'.$governor['attribute'].$governor['inv'].'" style="display:'.( $governor['active'] ? 'none' : 'block' ).';"></div>';
		$echo_str .= $dlp."\t\t".'<label for="wfu_attribute_'.$attr.'">'.$def['name'].'</label>';
		$echo_str .= $dlp."\t\t".'<div class="wfu_help_container" title="'.$def['help'].'"><img src="'.WFU_IMAGE_ADMIN_HELP.'" ></div>';
		$echo_str .= $dlp."\t".'</div></th>';
		$echo_str .= $dlp."\t".'<td style="vertical-align:top;"><div class="wfu_td_div">';
		if ( $def['parent'] == "" ) $echo_str .= $dlp."\t\t".'<div class="wfu_shadow wfu_shadow_'.$governor['attribute'].$governor['inv'].'" style="display:'.( $governor['active'] ? 'none' : 'block' ).';"></div>';
		if ( $def['type'] == "onoff" ) {
			$echo_str .= $dlp."\t\t".'<div id="wfu_attribute_'.$attr.'" class="wfu_onoff_container_'.( $def['value'] == "true" ? "on" : "off" ).'" onclick="wfu_admin_onoff_clicked(\''.$attr.'\');">';
			$echo_str .= $dlp."\t\t\t".'<div class="wfu_onoff_slider"></div>';
			$echo_str .= $dlp."\t\t\t".'<span class="wfu_onoff_text">ON</span>';
			$echo_str .= $dlp."\t\t\t".'<span class="wfu_onoff_text">OFF</span>';
			$echo_str .= $dlp."\t\t".'</div>';
		}
		elseif ( $def['type'] == "text" ) {
			$echo_str .= $dlp."\t\t".'<input id="wfu_attribute_'.$attr.'" type="text" name="wfu_text_elements" value="'.$def['value'].'" />';
			if ( $def['variables'] != null ) foreach ( $def['variables'] as $variable )
				$echo_str .= $dlp."\t\t".'<span class="wfu_variable wfu_variable_'.$attr.'" title="'.constant("WFU_VARIABLE_TITLE_".strtoupper(str_replace("%", "", $variable))).'" ondblclick="wfu_insert_variable(this);">'.$variable.'</span>';
		}
		elseif ( $def['type'] == "placements" ) {
			$components_used = array();
			foreach ( $components as $component ) $components_used[$component['id']] = false;
			$centered_content = '<div style="display:table; width:100%; height:100%;"><div style="display:table-cell; text-align:center; vertical-align:middle;">XXX</div></div>';
			$echo_str .= $dlp."\t\t".'<div class="wfu_placements_wrapper">';
			$echo_str .= $dlp."\t\t\t".'<div id="wfu_placements_container" class="wfu_placements_container">';
			$itemplaces = explode("/", $def['value']);
			foreach ( $itemplaces as $section ) {
				$echo_str .= $dlp."\t\t\t\t".'<div class="wfu_component_separator_hor"></div>';
				$echo_str .= $dlp."\t\t\t\t".'<div class="wfu_component_separator_ver"></div>';
				$items_in_section = explode("+", trim($section));
				$section_array = array( );
				foreach ( $items_in_section as $item_in_section ) {
					if ( key_exists($item_in_section, $components_indexed) ) {
						$components_used[$item_in_section] = true;
						$echo_str .= $dlp."\t\t\t\t".'<div id="wfu_component_box_'.$item_in_section.'" class="wfu_component_box" draggable="true">'.str_replace("XXX", $components_indexed[$item_in_section]['name'], $centered_content).'</div>';
						$echo_str .= $dlp."\t\t\t\t".'<div class="wfu_component_separator_ver"></div>';
					}
				}
			}
			$echo_str .= $dlp."\t\t\t\t".'<div class="wfu_component_separator_hor"></div>';
			$echo_str .= $dlp."\t\t\t\t".'<div id="wfu_component_bar_hor" class="wfu_component_bar_hor"></div>';
			$echo_str .= $dlp."\t\t\t\t".'<div id="wfu_component_bar_ver" class="wfu_component_bar_ver"></div>';
			$echo_str .= $dlp."\t\t\t".'</div>';
			$echo_str .= $dlp."\t\t\t".'<div id="wfu_componentlist_container" class="wfu_componentlist_container">';
			$echo_str .= $dlp."\t\t\t\t".'<div id="wfu_componentlist_dragdrop" class="wfu_componentlist_dragdrop" style="display:none;"></div>';
			$ii = 1;
			foreach ( $components as $component ) {
				$echo_str .= $dlp."\t\t\t\t".'<div id="wfu_component_box_container_'.$component['id'].'" class="wfu_component_box_container">';
				$echo_str .= $dlp."\t\t\t\t\t".'<div class="wfu_component_box_base">'.str_replace("XXX", $component['name'], $centered_content).'</div>';
				if ( !$components_used[$component['id']] )
					$echo_str .= $dlp."\t\t\t\t\t".'<div id="wfu_component_box_'.$component['id'].'" class="wfu_component_box wfu_inbase" draggable="true">'.str_replace("XXX", $component['name'], $centered_content).'</div>';
				$echo_str .= $dlp."\t\t\t\t".'</div>'.( ($ii++) % 3 == 0 ? '<br />' : '' );
			}
			$echo_str .= $dlp."\t\t\t".'</div>';
			$echo_str .= $dlp."\t\t".'</div>';
		}
		elseif ( $def['type'] == "ltext" ) {
			$echo_str .= $dlp."\t\t".'<input id="wfu_attribute_'.$attr.'" type="text" name="wfu_text_elements" class="wfu_long_text" value="'.$def['value'].'" />';
			if ( $def['variables'] != null ) foreach ( $def['variables'] as $variable )
				$echo_str .= $dlp."\t\t".'<span class="wfu_variable wfu_variable_'.$attr.'" title="'.constant("WFU_VARIABLE_TITLE_".strtoupper(str_replace("%", "", $variable))).'" ondblclick="wfu_insert_variable(this);">'.$variable.'</span>';
		}
		elseif ( $def['type'] == "integer" ) {
			$echo_str .= $dlp."\t\t".'<input id="wfu_attribute_'.$attr.'" type="number" name="wfu_text_elements" class="wfu_short_text" min="1" value="'.$def['value'].'" />';
		}
		elseif ( $def['type'] == "float" ) {
			$echo_str .= $dlp."\t\t".'<input id="wfu_attribute_'.$attr.'" type="number" name="wfu_text_elements" class="wfu_short_text" step="any" min="0" value="'.$def['value'].'" />';
		}
		elseif ( $def['type'] == "radio" ) {
			$echo_str .= $dlp."\t\t";
			$ii = 0;
			foreach ( $def['listitems'] as $item )
				$echo_str .= '<input name="wfu_radioattribute_'.$attr.'" type="radio" value="'.$item.'" '.( $item == $def['value'] || $item == "*".$def['value'] ? 'checked="checked" ' : '' ).'style="width:auto; margin:0px 2px 0px '.( ($ii++) == 0 ? '0px' : '8px' ).';" onchange="wfu_admin_radio_clicked(\''.$attr.'\');" />'.( $item[0] == "*" ? substr($item, 1) : $item );
//			$echo_str .= '<input type="button" class="button" value="empty" style="width:auto; margin:-2px 0px 0px 8px;" />';
		}
		elseif ( $def['type'] == "ptext" ) {
			$parts = explode("/", $def['value']);
			$singular = $parts[0];
			if ( count($parts) < 2 ) $plural = $singular;
			else $plural = $parts[1];
			$echo_str .= $dlp."\t\t".'<span class="wfu_ptext_span">Singular</span><input id="wfu_attribute_s_'.$attr.'" type="text" name="wfu_ptext_elements" value="'.$singular.'" />';
			if ( $def['variables'] != null ) if ( count($def['variables']) > 0 ) $echo_str .= $dlp."\t\t".'<br /><span class="wfu_ptext_span">&nbsp;</span>';
			if ( $def['variables'] != null ) foreach ( $def['variables'] as $variable )
				$echo_str .= $dlp."\t\t".'<span class="wfu_variable wfu_variable_s_'.$attr.'" title="'.constant("WFU_VARIABLE_TITLE_".strtoupper(str_replace("%", "", $variable))).'" ondblclick="wfu_insert_variable(this);">'.$variable.'</span>';
			$echo_str .= $dlp."\t\t".'<br /><span class="wfu_ptext_span">Plural</span><input id="wfu_attribute_p_'.$attr.'" type="text" name="wfu_ptext_elements" value="'.$plural.'" />';
			if ( $def['variables'] != null ) if ( count($def['variables']) > 0 ) $echo_str .= $dlp."\t\t".'<br /><span class="wfu_ptext_span">&nbsp;</span>';
			if ( $def['variables'] != null ) foreach ( $def['variables'] as $variable )
				$echo_str .= $dlp."\t\t".'<span class="wfu_variable wfu_variable_p_'.$attr.'" title'.constant("WFU_VARIABLE_TITLE_".strtoupper(str_replace("%", "", $variable))).'" ondblclick="wfu_insert_variable(this);">'.$variable.'</span>';
		}
		elseif ( $def['type'] == "mtext" ) {
			$echo_str .= $dlp."\t\t".'<textarea id="wfu_attribute_'.$attr.'" name="wfu_text_elements" rows="5">'.$def['value'].'</textarea>';
			if ( $def['variables'] != null ) foreach ( $def['variables'] as $variable )
				$echo_str .= $dlp."\t\t".'<span class="wfu_variable wfu_variable_'.$attr.'" title="'.constant("WFU_VARIABLE_TITLE_".strtoupper(str_replace("%", "", $variable))).'" ondblclick="wfu_insert_variable(this);">'.$variable.'</span>';
		}
		elseif ( $def['type'] == "rolelist" ) {
			$roles = $wp_roles->get_names();
			$selected = explode(",", $def['value']);
			foreach ( $selected as $key => $item ) $selected[$key] = trim($item);
			$echo_str .= $dlp."\t\t".'<select id="wfu_attribute_'.$attr.'" multiple="multiple" size="'.count($roles).'" onchange="wfu_update_rolelist_value(\''.$attr.'\');">';
			foreach ( $roles as $roleid => $rolename )
				$echo_str .= $dlp."\t\t\t".'<option value="'.$roleid.'"'.( in_array($roleid, $selected) ? ' selected="selected"' : '' ).'>'.$rolename.'</option>';
			$echo_str .= $dlp."\t\t".'</select>';
		}
		elseif ( $def['type'] == "dimensions" ) {
			$vals_arr = explode(",", $def['value']);
			$vals = array();
			foreach ( $vals_arr as $val_raw ) {
				list($val_id, $val) = explode(":", $val_raw);
				$vals[trim($val_id)] = trim($val);
			}
			$dims = array();
			foreach ( $components as $comp ) {
				if ( $comp['dimensions'] == null ) $dims[$comp['id']] = $comp['name'];
				else foreach ( $comp['dimensions'] as $dimraw ) {
					list($dim_id, $dim_name) = explode("/", $dimraw);
					$dims[$dim_id] = $dim_name;
				}
			}
			foreach ( $dims as $dim_id => $dim_name ) {
				$echo_str .= $dlp."\t\t".'<span style="display:inline-block; width:130px;">'.$dim_name.'</span><input id="wfu_attribute_'.$attr.'_'.$dim_id.'" type="text" name="wfu_dimension_elements_'.$attr.'" class="wfu_short_text" value="'.$vals[$dim_id].'" /><br />';
			}
		}
		elseif ( $def['type'] == "userfields" ) {
			$fields_arr = explode("/", $def['value']);
			$fields = array();
			foreach ( $fields_arr as $field_raw ) {
				$is_req = ( substr($field_raw, 0, 1) == "*" );
				if ( $is_req ) $field_raw = substr($field_raw, 1);
				if ( $field_raw != "" ) array_push($fields, array( "name" => $field_raw, "required" => $is_req ));
			}
			if ( count($fields) == 0 ) array_push($fields, array( "name" => "", "required" => false ));
			$echo_str .= $dlp."\t\t".'<div class="wfu_userdata_container">';
			foreach ( $fields as $field ) {
				$echo_str .= $dlp."\t\t\t".'<div class="wfu_userdata_line">';
				$echo_str .= $dlp."\t\t\t\t".'<input type="text" name="wfu_userfield_elements" value="'.$field['name'].'" />';
				$echo_str .= $dlp."\t\t\t\t".'<div class="wfu_userdata_action" onclick="wfu_userdata_add_field(this);"><img src="'.WFU_IMAGE_ADMIN_USERDATA_ADD.'" ></div>';
				$echo_str .= $dlp."\t\t\t\t".'<div class="wfu_userdata_action wfu_userdata_action_disabled" onclick="wfu_userdata_remove_field(this);"><img src="'.WFU_IMAGE_ADMIN_USERDATA_REMOVE.'" ></div>';
				$echo_str .= $dlp."\t\t\t\t".'<input type="checkbox"'.( $field['required'] ? 'checked="checked"' : '' ).' onchange="wfu_update_userfield_value({target:this});" />';
				$echo_str .= $dlp."\t\t\t\t".'<span>Required</span>';
				$echo_str .= $dlp."\t\t\t".'</div>';
			}
			$echo_str .= $dlp."\t\t".'</div>';
		}
		elseif ( $def['type'] == "color" ) {
			$echo_str .= $dlp."\t\t".'<input id="wfu_attribute_'.$attr.'" type="text" name="wfu_text_elements" class="wfu_color_field" value="'.$def['value'].'" />';
		}
		elseif ( $def['type'] == "color-triplet" ) {
			$triplet = explode(",", $def['value']);
			foreach ( $triplet as $key => $item ) $triplet[$key] = trim($item);
			$echo_str .= $dlp."\t\t".'<div class="wfu_color_container"><label style="display:inline-block; width:120px; margin-top:-16px;">Text Color</label><input id="wfu_attribute_'.$attr.'_color" type="text" class="wfu_color_field" name="wfu_triplecolor_elements" value="'.$triplet[0].'" /></div>';
			$echo_str .= $dlp."\t\t".'<div class="wfu_color_container"><label style="display:inline-block; width:120px; margin-top:-16px;">Background Color</label><input id="wfu_attribute_'.$attr.'_bgcolor" type="text" class="wfu_color_field" name="wfu_triplecolor_elements" value="'.$triplet[1].'" /></div>';
			$echo_str .= $dlp."\t\t".'<div class="wfu_color_container"><label style="display:inline-block; width:120px; margin-top:-16px;">Border Color</label><input id="wfu_attribute_'.$attr.'_borcolor" type="text" class="wfu_color_field" name="wfu_triplecolor_elements" value="'.$triplet[2].'" /></div>';
		}
		else {
			$echo_str .= $dlp."\t\t".'<input id="wfu_attribute_'.$attr.'" type="text" name="wfu_text_elements" value="'.$def['value'].'" />';
			if ( $def['variables'] != null ) foreach ( $def['variables'] as $variable )
				$echo_str .= $dlp."\t\t".'<span class="wfu_variable wfu_variable_'.$attr.'" title="'.constant("WFU_VARIABLE_TITLE_".strtoupper(str_replace("%", "", $variable))).'" ondblclick="wfu_insert_variable(this);">'.$variable.'</span>';
		}
		$echo_str .= $dlp."\t".'</div></td>';
		if ( $def['parent'] == "" ) {
			$echo_str .= $dlp."\t".'<td style="position:relative; vertical-align:top; padding:0;"><div class="wfu_td_div">';
			$block_open = false;
		}
		else {
			$echo_str .= $dlp.'</tr>';
			$subblock_open = true;						
		}
	}
	if ( $subblock_open ) {
		$echo_str .= "\n\t\t\t\t\t\t".'</div>';
	}
	if ( $block_open ) {
		$echo_str .= "\n\t\t\t\t\t".'</div></td>';
		$echo_str .= "\n\t\t\t\t".'</tr>';
	}
	$echo_str .= "\n\t\t\t".'</tbody>';
	$echo_str .= "\n\t\t".'</table>';
	$handler = 'function() { wfu_Attach_Admin_Events(); }';
	$echo_str .= "\n\t".'<script type="text/javascript">if(window.addEventListener) { window.addEventListener("load", '.$handler.', false); } else if(window.attachEvent) { window.attachEvent("onload", '.$handler.'); } else { window["onload"] = '.$handler.'; }</script>';
	$echo_str .= "\n".'</div>';
//	$echo_str .= "\n\t".'<div style="margin-top:10px;">';
//	$echo_str .= "\n\t\t".'<label>Final shortcode text</label>';
//	$echo_str .= "\n\t".'</div>';

	echo $echo_str;
}

function wfu_update_settings() {
}

?>
