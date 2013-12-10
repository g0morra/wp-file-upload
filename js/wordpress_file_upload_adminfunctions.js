var DraggedItem = null;

jQuery(document).ready(function($){
	$('.wfu_color_field').wpColorPicker({
		change: function(event, ui) {
			event.target.value = ui.color.toString();
			if (event.target.name == "wfu_text_elements") wfu_update_text_value(event);
			else if (event.target.name == "wfu_triplecolor_elements") wfu_update_triplecolor_value(event);
		}
	});
});

function wfu_admin_activate_tab(key) {
	var tabs = document.getElementById("wfu_tab_container");
	var tab, tabkey;
	for (var i = 0; i < tabs.childNodes.length; i++) {
		tab = tabs.childNodes[i];
		if (tab.nodeType === 1) {
			tabkey = tab.id.substr(8);
			if (tab.className.indexOf("nav-tab-active") > -1) {
				tab.className = "nav-tab";
				document.getElementById("wfu_container_" + tabkey).style.display = "none";
			}
		}
	}
	document.getElementById("wfu_tab_" + key).className = "nav-tab nav-tab-active";
	document.getElementById("wfu_container_" + key).style.display = "block";
}

function wfu_admin_onoff_clicked(key) {
	var onoff = document.getElementById("wfu_attribute_" + key);
	var container = document.getElementById("wfu_wrapper");
	var shadows = document.getElementsByClassName("wfu_shadow_" + key, "div", container);
	var shadows_inv = document.getElementsByClassName("wfu_shadow_" + key + "_inv", "div", container);
	var status = (onoff.className.substr(onoff.className.length - 2) == "on");
	status = !status;
	if (status) {
		document.getElementById("wfu_attribute_value_" + key).value = "true";
		onoff.className = "wfu_onoff_container_on";
		for (var i = 0; i < shadows.length; i++) shadows[i].style.display = "none";
		for (var i = 0; i < shadows_inv.length; i++) shadows_inv[i].style.display = "block";
	}
	else {
		document.getElementById("wfu_attribute_value_" + key).value = "false";
		onoff.className = "wfu_onoff_container_off";
		for (var i = 0; i < shadows.length; i++) shadows[i].style.display = "block";
		for (var i = 0; i < shadows_inv.length; i++) shadows_inv[i].style.display = "none";
	}
	wfu_generate_shortcode();
}

function wfu_admin_radio_clicked(key) {
	var radios = document.getElementsByName("wfu_radioattribute_" + key);
	var container = document.getElementById("wfu_wrapper");
	var shadows = document.getElementsByClassName("wfu_shadow_" + key, "div", container);
	var shadows_inv = document.getElementsByClassName("wfu_shadow_" + key + "_inv", "div", container);
	var val = "";
	for (i = 0; i < radios.length; i++)
		if (radios[i].checked) val = radios[i].value;
	var status = (val.substr(0, 1) == "*");
	if (status) {
		val = val.substr(1);
		for (var i = 0; i < shadows.length; i++) shadows[i].style.display = "none";
		for (var i = 0; i < shadows_inv.length; i++) shadows_inv[i].style.display = "block";
	}
	else {
		for (var i = 0; i < shadows.length; i++) shadows[i].style.display = "block";
		for (var i = 0; i < shadows_inv.length; i++) shadows_inv[i].style.display = "none";
	}
	document.getElementById("wfu_attribute_value_" + key).value = val;
	wfu_generate_shortcode();
}

function wfu_addEventHandler(obj, evt, handler) {
	if(obj.addEventListener) {
		// W3C method
		obj.addEventListener(evt, handler, false);
	}
	else if(obj.attachEvent) {
		// IE method.
		obj.attachEvent('on'+evt, handler);
	}
	else {
		// Old school method.
		obj['on'+evt] = handler;
	}
}

function wfu_attach_separator_dragdrop_events() {
	var container = document.getElementById('wfu_placements_container');
	var item;
	for (var i = 0; i < container.childNodes.length; i++) {
		item = container.childNodes[i];
		if (item.className == "wfu_component_separator_hor" || item.className == "wfu_component_separator_ver") {
			wfu_addEventHandler(item, 'dragenter', wfu_separator_dragenter);
			wfu_addEventHandler(item, 'dragover', wfu_default_dragover);
			wfu_addEventHandler(item, 'dragleave', wfu_separator_dragleave);
			wfu_addEventHandler(item, 'drop', wfu_separator_drop);
		}
	}
}

function wfu_Attach_Admin_DragDrop_Events() {
	if (window.FileReader) {
		var container = document.getElementById('wfu_placements_container');
		var available_container = document.getElementById('wfu_componentlist_container');
		var item;
		for (var i = 0; i < container.childNodes.length; i++) {
			item = container.childNodes[i];
			if (item.className == "wfu_component_box") {
				wfu_addEventHandler(item, 'dragstart', wfu_component_dragstart);
				wfu_addEventHandler(item, 'dragend', wfu_component_dragend);
			}
		}
		for (var i = 0; i < available_container.childNodes.length; i++) {
			item = available_container.childNodes[i];
			if (item.className == "wfu_component_box_container") {
				for (var ii = 0; ii < item.childNodes.length; ii++) {
					if (item.childNodes[ii].className == "wfu_component_box wfu_inbase") {
						wfu_addEventHandler(item.childNodes[ii], 'dragstart', wfu_component_dragstart);
						wfu_addEventHandler(item.childNodes[ii], 'dragend', wfu_component_dragend);
					}
				}
			}
		}
		item = document.getElementById('wfu_componentlist_dragdrop');
		wfu_addEventHandler(item, 'dragenter', wfu_componentlist_dragenter);
		wfu_addEventHandler(item, 'dragover', wfu_default_dragover);
		wfu_addEventHandler(item, 'dragleave', wfu_componentlist_dragleave);
		wfu_addEventHandler(item, 'drop', wfu_componentlist_drop);
		wfu_attach_separator_dragdrop_events();
	}	
}

function wfu_componentlist_dragenter(e) {
	e = e || window.event;
	if (e.preventDefault) { e.preventDefault(); }
	if (!DraggedItem) return false;
	var item = document.getElementById('wfu_componentlist_dragdrop');
	if (item.className.indexOf("wfu_componentlist_dragdrop_dragover") == -1)
		item.className += " wfu_componentlist_dragdrop_dragover";
	return false;
}

function wfu_componentlist_dragleave(e) {
	e = e || window.event;
	if (e.preventDefault) { e.preventDefault(); }
	if (!DraggedItem) return false;
	var item = document.getElementById('wfu_componentlist_dragdrop');
	item.className = item.className.replace(" wfu_componentlist_dragdrop_dragover", "");
	return false;
}

function wfu_componentlist_drop(e) {
	e = e || window.event;
	if (e.preventDefault) { e.preventDefault(); }
	var component = e.dataTransfer.getData("Component");
	if (!component) return false;
	//move dragged component to base
	var item = document.getElementById('wfu_component_box_' + component);
	item.className = "wfu_component_box wfu_inbase";
	item.style.display = "block";
	document.getElementById('wfu_component_box_container_' + component).appendChild(item);
	//recreate placements panel
	var placements = wfu_admin_recreate_placements_text(null, "");
	wfu_admin_recreate_placements_panel(placements);
	document.getElementById("wfu_attribute_value_placements").value = placements;
	wfu_generate_shortcode();
	return false;
}

function wfu_separator_dragenter(e) {
	e = e || window.event;
	if (e.preventDefault) { e.preventDefault(); }
	if (!DraggedItem) return false;
	if (e.target.className == "wfu_component_separator_hor") {
		var bar = document.getElementById('wfu_component_bar_hor');
		bar.style.top = e.target.offsetTop + "px";
		bar.style.display = "block";
	}
	else if (e.target.className == "wfu_component_separator_ver") {
		var bar = document.getElementById('wfu_component_bar_ver');
		bar.style.top = e.target.offsetTop + "px";
		bar.style.left = e.target.offsetLeft + "px";
		bar.style.display = "block";
	}
	return false;
}

function wfu_default_dragover(e) {
	e = e || window.event;
	if (e.preventDefault) { e.preventDefault(); }
	return false;
}

function wfu_separator_dragleave(e) {
	e = e || window.event;
	if (e.preventDefault) { e.preventDefault(); }
	if (!DraggedItem) return false;
	if (e.target.className == "wfu_component_separator_hor") {
		var bar = document.getElementById('wfu_component_bar_hor');
		bar.style.display = "none";
	}
	else if (e.target.className == "wfu_component_separator_ver") {
		var bar = document.getElementById('wfu_component_bar_ver');
		bar.style.display = "none";
	}
	return false;
}

function wfu_separator_drop(e) {
	e = e || window.event;
	if (e.preventDefault) { e.preventDefault(); }
	var component = e.dataTransfer.getData("Component");
	if (!component) return false;
	//first move dragged component to base otherwise we may lose it during recreation of placements panel
	var item = document.getElementById('wfu_component_box_' + component);
	item.style.display = "none";
	item.className = "wfu_component_box wfu_inbase";
	document.getElementById('wfu_component_box_container_' + component).appendChild(item);
	//recreate placements panel
	var placements = wfu_admin_recreate_placements_text(e.target, component);
	wfu_admin_recreate_placements_panel(placements);
	document.getElementById("wfu_attribute_value_placements").value = placements;
	wfu_generate_shortcode();
	return false;
}

function wfu_component_dragstart(e) {
	e = e || window.event;
	e.dataTransfer.setData("Component", e.target.id.replace("wfu_component_box_", ""));
	if (e.target.className.indexOf("wfu_component_box_dragged") == -1) {
		e.target.className += " wfu_component_box_dragged";
		DraggedItem = e.target;
	}
	e.target.style.zIndex = 3;
	var item = document.getElementById('wfu_componentlist_dragdrop');
	item.className = "wfu_componentlist_dragdrop wfu_componentlist_dragdrop_dragover";
	item.style.display = "block";
	return false;
}

function wfu_component_dragend(e) {
	e = e || window.event;
	DraggedItem = null;
	e.target.style.zIndex = 1;
	var item = document.getElementById('wfu_componentlist_dragdrop');
	item.style.display = "none";
	item.className = "wfu_componentlist_dragdrop";
	e.target.className = e.target.className.replace(" wfu_component_box_dragged", "");
	document.getElementById('wfu_component_bar_ver').style.display = "none";
	document.getElementById('wfu_component_bar_hor').style.display = "none";
	return false;
}

function wfu_admin_recreate_placements_text(place, new_component) {
	function add_item(component) {
		if (placements != "") placements += delim;
		placements += component;
		delim = "";
	}

	var container = document.getElementById('wfu_placements_container');
	var delim = "";
	var placements = "";
	var component = "";
	for (var i = 0; i < container.childNodes.length; i++) {
		item = container.childNodes[i];
		if (item.className == "wfu_component_separator_ver") {
			if (delim == "" ) delim = "+";
			if (item == place) { add_item(new_component); delim = "+"; }
		}
		else if (item.className == "wfu_component_separator_hor") {
			delim = "/";
			if (item == place) { add_item(new_component); delim = "/"; } 
		}
		else if (item.className == "wfu_component_box") add_item(item.id.replace("wfu_component_box_", ""));
	}
	return placements;
}

function wfu_admin_recreate_placements_panel(placements_text) {
	var container = document.getElementById('wfu_placements_container');
	var item, placements, sections;
	var itemname = "";
	for (var i = 0; i < container.childNodes.length; i++) {
		item = container.childNodes[i];
		if (item.className == "wfu_component_box") {
			itemname = item.id.replace("wfu_component_box_", "");
			item.style.display = "none";
			item.className = "wfu_component_box wfu_inbase";
			document.getElementById('wfu_component_box_container_' + itemname).appendChild(item);
		}
	}
	container.innerHTML = "";
	placements = placements_text.split("/");
	for (var i = 0; i < placements.length; i++) {
		item = document.createElement("DIV");
		item.className = "wfu_component_separator_hor";
		item.setAttribute("draggable", true);
		container.appendChild(item);
		item = document.createElement("DIV");
		item.className = "wfu_component_separator_ver";
		item.setAttribute("draggable", true);
		container.appendChild(item);
		sections = placements[i].split("+");
		for (var ii = 0; ii < sections.length; ii++) {
			item = document.getElementById('wfu_component_box_' + sections[ii]);
			if (item) {
				container.appendChild(item);
				item.className = "wfu_component_box";
				item.style.display = "inline-block";
				item = document.createElement("DIV");
				item.className = "wfu_component_separator_ver";
				item.setAttribute("draggable", true);
				container.appendChild(item);
			}
		}
	}
	item = document.createElement("DIV");
	item.className = "wfu_component_separator_hor";
	item.setAttribute("draggable", true);
	container.appendChild(item);
	item = document.createElement("DIV");
	item.id = "wfu_component_bar_hor";
	item.className = "wfu_component_bar_hor";
	container.appendChild(item);
	item = document.createElement("DIV");
	item.id = "wfu_component_bar_ver";
	item.className = "wfu_component_bar_ver";
	container.appendChild(item);
	wfu_attach_separator_dragdrop_events();
}

function wfu_userdata_add_field(obj) {
	var line = obj.parentNode;
	var newline = line.cloneNode(true);
	var item;
	for (var i = 0; i < newline.childNodes.length; i ++) {
		item = newline.childNodes[i];
		if (item.tagName == "INPUT") {
			if (item.type == "text") {
				item.value = "";
				wfu_attach_element_handlers(item, wfu_update_userfield_value);
			}
			else if (item.type == "checkbox") {
				item.checked = false;
			}
		}
		else if (item.tagName == "DIV") item.className = "wfu_userdata_action";
	}
	line.parentNode.insertBefore(newline, line.nextSibling);
}

function wfu_userdata_remove_field(obj) {
	var line = obj.parentNode;
	var container = line.parentNode;
	var first = null;
	for (var i = 0; i < container.childNodes.length; i++)
		if (container.childNodes[i].nodeType === 1) {
			first = container.childNodes[i];
			break;
		}
	if (line != first) {
		line.parentNode.removeChild(line);
		for (var i = 0; i < first.childNodes.length; i++)
			if (first.childNodes[i].nodeType === 1) {
				wfu_update_userfield_value({target:first.childNodes[i]});
				break;
			}
	}
}

function wfu_generate_shortcode() {
	var defaults = document.getElementById("wfu_attribute_defaults");
	var values = document.getElementById("wfu_attribute_values");
	var item;
	var attribute = "";
	var value = "";
	var shortcode = "[wordpress_file_upload";
	for (var i = 0; i < defaults.childNodes.length; i++) {
		item = defaults.childNodes[i];
		if (item.nodeType === 1) {
			attribute = item.id.replace("wfu_attribute_default_", "");
			value = document.getElementById("wfu_attribute_value_" + attribute).value;
			if (item.value != value)
				shortcode += " " + attribute + "=\"" + value + "\"";
		}
	}
	shortcode += "]";

	document.getElementById("wfu_shortcode").value = shortcode;
}

function wfu_update_text_value(e) {
	e = e || window.event;
	var item = e.target;
	var attribute = item.id.replace("wfu_attribute_", "");
	var val = item.value;
	//if it is a multiline element, then replace line breaks with %n%
	if (item.tagName == "TEXTAREA") {
		val = val.replace(/(\r\n|\n|\r)/gm,"%n%");
	}
	if (val !== item.oldVal) {
		item.oldVal = val;
		document.getElementById("wfu_attribute_value_" + attribute).value = val;
		wfu_generate_shortcode();
	}
}

function wfu_update_triplecolor_value(e) {
	e = e || window.event;
	var item = e.target;
	var attribute = item.id.replace("wfu_attribute_", "");
	attribute = attribute.replace("_color", "");
	attribute = attribute.replace("_bgcolor", "");
	attribute = attribute.replace("_borcolor", "");	
	item = document.getElementById("wfu_attribute_" + attribute + "_color");
	var val = item.value + "," +
		document.getElementById("wfu_attribute_" + attribute + "_bgcolor").value + "," +
		document.getElementById("wfu_attribute_" + attribute + "_borcolor").value;
	if (val !== item.oldVal) {
		item.oldVal = val;
		document.getElementById("wfu_attribute_value_" + attribute).value = val;
		wfu_generate_shortcode();
	}
}

function wfu_update_dimension_value(e) {
	e = e || window.event;
	var item = e.target;
	var attribute = item.name.replace("wfu_dimension_elements_", "");
	var group = document.getElementsByName(item.name);
	item = group[0];
	var val = "";
	var dimname = "";
	for (var i = 0; i < group.length; i++) {
		dimname = group[i].id.replace("wfu_attribute_" + attribute + "_", "");
		if (val != "" && group[i].value != "") val += ", ";
		if (group[i].value != "") val += dimname + ":" + group[i].value;
	}
	if (val !== item.oldVal) {
		item.oldVal = val;
		document.getElementById("wfu_attribute_value_" + attribute).value = val;
		wfu_generate_shortcode();
	}
}

function wfu_update_ptext_value(attribute) {
	var singular = document.getElementById("wfu_attribute_s_" + attribute).value;
	var plural = document.getElementById("wfu_attribute_p_" + attribute).value;
	document.getElementById("wfu_attribute_value_" + attribute).value = singular + "/" + plural;
	wfu_generate_shortcode();
}

function wfu_update_rolelist_value(attribute) {
	var value = "";
	var rolelist = document.getElementById("wfu_attribute_" + attribute);
	var checkall = document.getElementById("wfu_attribute_" + attribute + "_all");
	if (checkall.checked) {
		rolelist.disabled = true;
		value = "all";
	}
	else {
		rolelist.disabled = false;
		var options = rolelist.options;
		for (var i = 0; i < options.length; i++)
			if (options[i].selected) {
				if (value != "") value += ",";
				value += options[i].value;
			}
	}
	document.getElementById("wfu_attribute_value_" + attribute).value = value;
	wfu_generate_shortcode();
}

function wfu_update_userfield_value(e) {
	e = e || window.event;
	var item = e.target;
	var line = item.parentNode;
	var container = line.parentNode;
	var fieldval = "";
	var fieldreq = false;
	var val = "";
	for (var i = 0; i < container.childNodes.length; i++) {
		line = container.childNodes[i];
		if (line.tagName === "DIV") {
			for (var j = 0; j < line.childNodes.length; j++)
				if (line.childNodes[j].tagName == "INPUT") {
					if (line.childNodes[j].type == "text") {
						fieldval = line.childNodes[j].value;
						if (i == 0) item = line.childNodes[j];
					}
					else if (line.childNodes[j].type == "checkbox")
						fieldreq = line.childNodes[j].checked;
				}
			if (val != "" && fieldval != "") val += "/";
			if (fieldval != "" && fieldreq) val += "*";
			if (fieldval != "") val += fieldval;
		}
	}
	if (val !== item.oldVal) {
		item.oldVal = val;
		document.getElementById("wfu_attribute_value_userdatalabel").value = val;
		wfu_generate_shortcode();
	}
}

function wfu_attach_element_handlers(item, handler) {
	var elem_events = ['DOMAttrModified', 'textInput', 'input', 'change', 'keypress', 'paste', 'focus', 'propertychange'];
	for (var i = 0; i < elem_events.length; i++)
		wfu_addEventHandler(item, elem_events[i], handler);
}

function wfu_Attach_Admin_Events() {
	wfu_Attach_Admin_DragDrop_Events();
	var text_elements = document.getElementsByName("wfu_text_elements");
	for (var i = 0; i < text_elements.length; i++) wfu_attach_element_handlers(text_elements[i], wfu_update_text_value);
	var triplecolor_elements = document.getElementsByName("wfu_triplecolor_elements");
	for (var i = 0; i < triplecolor_elements.length; i++) wfu_attach_element_handlers(triplecolor_elements[i], wfu_update_triplecolor_value);
	var dimension_elements = document.getElementsByName("wfu_dimension_elements_widths");
	for (var i = 0; i < dimension_elements.length; i++) wfu_attach_element_handlers(dimension_elements[i], wfu_update_dimension_value);
	dimension_elements = document.getElementsByName("wfu_dimension_elements_heights");
	for (var i = 0; i < dimension_elements.length; i++) wfu_attach_element_handlers(dimension_elements[i], wfu_update_dimension_value);
	var userfield_elements = document.getElementsByName("wfu_userfield_elements");
	for (var i = 0; i < userfield_elements.length; i++) wfu_attach_element_handlers(userfield_elements[i], wfu_update_userfield_value);
}

function wfu_insert_variable(obj) {
	var attr = obj.className.replace("wfu_variable wfu_variable_", "");
	var inp = document.getElementById("wfu_attribute_" + attr);
	var pos = inp.selectionStart;
	var prevval = inp.value;
	inp.value = prevval.substr(0, pos) + obj.innerHTML + prevval.substr(pos);
	wfu_update_text_value({target:inp});
}
