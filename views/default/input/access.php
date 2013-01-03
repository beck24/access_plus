<?php

global $access_view_count;
// count the number of times the view has been called
// used to generate the token
if(!is_numeric($access_view_count)){
	$access_view_count = 0;
}
else{
	$access_view_count++;
}


// get our token and see if this view has been blacklisted
$token = access_plus_generate_token($vars['name']);
//var_dump($token);

// check to see if our token has been blacklisted
if(access_plus_is_blacklisted($token)){
	// it's been blacklisted, show the regular access control
	echo elgg_view('input/access_original', $vars);
	return;
}
else{
	// not blacklisted - show the cool controls
	
	$class = "input-access";
	if (isset($vars['class'])) {
		$class = $vars['class'];
	}

	$disabled = false;
	if (isset($vars['disabled'])) {
		$disabled = $vars['disabled'];
	}

	if (!array_key_exists('value', $vars) || $vars['value'] == ACCESS_DEFAULT) {
		$vars['value'] = get_default_access();
	}

	// check to see if the value is a metacollection
	$access_entity = elgg_get_entities_from_metadata(array(
			'types' => array('object'),
			'subtypes' => array('access_plus'),
			'metadata_names' => array('access_plus_collection_id'),
			'metadata_values' => array($vars['value'])
	));

	$collectionarray = array($vars['value']);
	if(!empty($access_entity)){
		$collectionarray = $access_entity[0]->access_plus_collections;
	}


	if ((!isset($vars['options'])) || (!is_array($vars['options']))) {
		$vars['options'] = array();
		$vars['options'] = get_write_access_array();
	}

	if (is_array($vars['options']) && sizeof($vars['options']) > 0) {

		echo "<br>";

		// sort $vars['options'], if there are elgg default site-wide options put them first
		// everything else do alphabetical afterwards
		$tmpoptions = array();

		if(array_key_exists(ACCESS_PRIVATE, $vars['options'])){
			$tmpoptions[][ACCESS_PRIVATE] = $vars['options'][ACCESS_PRIVATE];
			unset($vars['options'][ACCESS_PRIVATE]);
		}

		if(array_key_exists(ACCESS_FRIENDS, $vars['options'])){
			$tmpoptions[][ACCESS_FRIENDS] = $vars['options'][ACCESS_FRIENDS];
			unset($vars['options'][ACCESS_FRIENDS]);
		}

		if(array_key_exists(ACCESS_LOGGED_IN, $vars['options'])){
			$tmpoptions[][ACCESS_LOGGED_IN] = $vars['options'][ACCESS_LOGGED_IN];
			unset($vars['options'][ACCESS_LOGGED_IN]);
		}

		if(array_key_exists(ACCESS_PUBLIC, $vars['options'])){
			$tmpoptions[][ACCESS_PUBLIC] = $vars['options'][ACCESS_PUBLIC];
			unset($vars['options'][ACCESS_PUBLIC]);
		}

		foreach($vars['options'] as $key => $value){
			$tmpoptions[][$key] = $value;
		}

		$elgg_access = array(
		ACCESS_PRIVATE,
		ACCESS_FRIENDS,
		ACCESS_LOGGED_IN,
		ACCESS_PUBLIC,
		);
		?>
<div class="access_plus_wrapper">
<?php
/*
 * Set as private for now, the plugin will figure out the access on the create/update hook
 */

echo elgg_view('input/hidden', array(
	'name' => $vars['name'],
	'value' => ACCESS_PRIVATE
));
		
		// allow plugins to change the selected options
		$tmpoptions = elgg_trigger_plugin_hook('access_plus', 'available_options', array('vars' => $vars), $tmpoptions);
		$collectionarray = elgg_trigger_plugin_hook('access_plus', 'selected_options', array('vars' => $vars), $collectionarray);
		
		for ($i=0; $i<count($tmpoptions); $i++) {
			$keys = array_keys($tmpoptions[$i]);
			$key = $keys[0];
			// set up odd/even class name for zebra striping css
			$oddeven++;
			if ($oddeven % 2) { $zebra = "zebra_odd"; }else{ $zebra = "zebra_even"; }
			if (in_array($key, $elgg_access)) { $zebra = "site-wide-options"; }
			echo "<div class=\"access_plus_$zebra\">";
			if (in_array($key, $collectionarray)) {
				echo "<input name=\"access_plus_{$vars['name']}[]\" id=\"access_plus_{$access_view_count}_{$key}\" class=\"{$vars['class']}\" type=\"checkbox\" value=\"{$key}\" checked=\"checked\"><label for=\"access_plus_{$access_view_count}_{$key}\">". htmlentities($tmpoptions[$i][$key], ENT_QUOTES, 'UTF-8') ."</label>";
			} else {
				echo "<input name=\"access_plus_{$vars['name']}[]\" id=\"access_plus_{$access_view_count}_{$key}\" class=\"{$vars['class']}\" type=\"checkbox\" value=\"{$key}\"><label for=\"access_plus_{$access_view_count}_{$key}\">". htmlentities($tmpoptions[$i][$key], ENT_QUOTES, 'UTF-8') ."</label>";
			}

			echo "</div>"; // access_plus_$zebra
	}

	?>
</div>
<?php
	} //is_array
} // end of cool controls


//
// add the link to toggle the access view if admin
if (elgg_is_admin_logged_in()) {
	$url = elgg_get_site_url() . "action/access_plus/toggle?token=" . $token;
	$url = elgg_add_action_tokens_to_url($url);
	
	$linktext = elgg_echo('access_plus:toggle:off');
	if(access_plus_is_blacklisted($token)){
		$linktext = elgg_echo('access_plus:toggle:on');
	}
	
	echo "<div>";
	echo elgg_view('output/url', array(
		'href' => $url,
		'text' => $linktext,
		'is_action' => true
	));
	echo "</div>";
}