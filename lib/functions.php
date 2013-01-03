<?php

function access_plus_blacklist($token){
	$blacklist = elgg_get_plugin_setting('blacklist', 'access_plus');
	$blackarray = explode(",", $blacklist);
	
	if(!in_array($token, $blackarray)){
		$blackarray[] = $token;
	}
	
	$blackarray = array_values($blackarray);
	
	$blacklist = implode(",", $blackarray);
	elgg_set_plugin_setting('blacklist', $blacklist, 'access_plus');
}

//
// creates a metacollection from an array of collection IDs
function access_plus_create_metacollection($access, $key) {
	if (!is_array($access)) {
		return false;
	}
	
	// set a custom context to overwrite permissions temporarily
	$context = elgg_get_context();
	elgg_set_context('access_plus_permission');

	// if guid is set to 0, then it defaults to logged in user
	// @TODO - this is a hack, find something better
	// use -9999 so that it defaults to 0, and the users collections aren't cluttered
	$id = create_access_collection($key, -9999);
	
	// create an entity for this collection, to use for relationships
	$entity = new ElggObject();
	$entity->subtype = 'access_plus';
	$entity->owner_guid = elgg_get_logged_in_user_guid();
	$entity->container_guid = elgg_get_logged_in_user_guid();
	$entity->access_id = ACCESS_PUBLIC;
	$entity->save();
	
	// add our metadata for potentially easy retrieval
	$entity->access_plus_collection_id = $id;
	$entity->access_plus_key = $key;
	$entity->access_plus_collections = $access; // array of all sub-accesses
	
	// for some reason this needs to be saved again for metadata to work
	// very odd...
	$entity->save();
	
	// for each access id, get that entity and form a relationship between the access entities
	$collections_entities = elgg_get_entities_from_metadata(array(
		'types' => array('object'),
		'subtypes' => array('access_plus'),
		'metadata_names' => array('access_plus_collection_id'),
		'metadata_values' => $access
	));
	
	if ($collections_entities) {
	  foreach ($collections_entities as $collection) {
		add_entity_relationship($entity->guid, ACCESS_PLUS_META_RELATIONSHIP, $collection->guid);
	  }
	}
	
	if (!$id) {
		elgg_set_context($context);
		return false;
	}
	else {
		// we've created the metacollection, populate it from the component collections
		$members = array();
		for($i=0; $i<count($access); $i++){
			
			if($access[$i] == ACCESS_FRIENDS){
				// we're adding every friend we have in this special case
				$user = elgg_get_logged_in_user_entity();
				$friends = $user->getFriends("", 0, 0);
				$tmp_members = array();
				foreach($friends as $friend){
					$tmp_members[] = $friend->guid;
				}
			}
			else{
				$tmp_members = get_members_of_access_collection($access[$i], true);
			}
			
			if(is_array($tmp_members) && count($tmp_members) > 0){
				$members = array_merge($members, $tmp_members);
			}
		}
		
		$members = array_unique($members);
		$members = array_values($members);
		
		// add each member to the metacollection
		foreach ($members as $member) {
			$done = add_user_to_access_collection($member, $id);
			if (!$done) {
				register_error(elgg_echo('access_plus:add_user_to_metacollection:error'));
			}
		}
		
		elgg_set_context($context);
		return $id;
	}
	
	// return context to it's previous setting so we're not allowing unwarranted access
	// to anything else
	elgg_set_context($context);
}

//
// this function generates a token that is unique to a specific instance of an access view
// tokens can be used to enable or disable multi-use on a per-instance basis
// uses the name of the input field, as well as context and view count
function access_plus_generate_token($name){
	global $access_view_count;

	$context = elgg_get_context();
	
	return md5($context . $access_view_count . $name);
}

//
// returns true if the access instance is blacklisted
function access_plus_is_blacklisted($token){
	// get our blacklist from settings
	$blacklist = elgg_get_plugin_setting('blacklist', 'access_plus');
	$blackarray = explode(",", $blacklist);

	if(!is_array($blackarray)){ $blackarray = array(); }
	
	if(in_array($token, $blackarray)){
		// it's been blacklisted
		return true;
	}
	
	return false;
}

//
// this function takes accesses and merges the collections
function access_plus_merge_collections($access){

	// now we should have an array of collections that should be merged if necessary
	// first lets check to see if the collection already exists
			
	// collection id is stored in plugin user settings (to prevent clashes)
	// stored with unique name in the form of <collection1>:<collection2>:...
				
	for ($i=0; $i<count($access); $i++) {
		if ($i == 0) {
			$key = $access[$i];
		}
		else {
			$key .= ":" . $access[$i];
		}
	}

	// get our saved access collection id, if it exists
	//$acl_id = elgg_get_plugin_user_setting($key, elgg_get_logged_in_user_guid(), 'access_plus');
	$access_entity = elgg_get_entities_from_metadata(array(
		'types' => array('object'),
		'subtypes' => array('access_plus'),
		'metadata_names' => array('access_plus_key'),
		'metadata_values' => $key
	));
				
	if(!$access_entity){
		//we don't have an existing collection for this combination
		//have to create a new one
		$new_acl_id = access_plus_create_metacollection($access, $key);
					
		if(is_numeric($new_acl_id)){
			$new_access_id = $new_acl_id;
		}
		else{
			//there was a problem, make it private instead and throw an error
			$new_access_id = ACCESS_PRIVATE;
			register_error(elgg_echo('access_plus:metacollection:creation:error'));
		}
	}
	else{
		//we have an existing collection for this so we'll use it
		$new_access_id = $access_entity[0]->access_plus_collection_id;
	}

	return $new_access_id;
}

//
// this function takes an array of accesses, sorts out what the final value should be
function access_plus_parse_access($access){
	/*
	 *	if $access is not an array we should do nothing
	 *	if $access has only one item, we should set that as the object access_id
	 *
	 *	if $access has more than one item we need to do some processing then check for an existing
	 *	access collection.  If one exists for the combination given then we'll use it, otherwise
	 *	we'll create a new collection. 
	 *
	 *	There is a heirarchy that should be checked for:
	 *	eg. if public and something else is selected
	 *	then the something else is pointless, just set it as public.
	 *	If not public, but logged in users and something else, set as logged in
	 *	If not public or logged in, but friends, then set as friends
	 *	BUT we need to make sure there's no group collections because they might contain non-friends
	 *	in that case we make a metacollection for all friends plus that group...
	 *
	 *	If private and something else is set, then the private is pointless - strip it out
	 *	After these filters, we can merge collections if necessary
	 */
	
	if (count($access) == 1) {
		//only one option was selected, so set it and we're done
		return $access[0];
	}

	//there are multiple options selected
	sort($access);
	if (in_array(ACCESS_PUBLIC, $access)) {
		//public was one of the selections, nothing else matters
		return ACCESS_PUBLIC;
	}
	
	if (in_array(ACCESS_LOGGED_IN, $access)) {
		//logged in users was selected, trumps anything but public
		return ACCESS_LOGGED_IN;
	}
	
	if (in_array(ACCESS_FRIENDS, $access)) {
		//friends selected, trumps anything but public and logged in
		// except if there are group access collections
		
		//check if there are group collections
		$groups = array();
		foreach ($access as $access_id) {
			$grouptest = false;
			if ($access_id != ACCESS_FRIENDS) {
				$collection = get_access_collection($access_id);
				$grouptest = get_entity($collection->owner_guid);
			}
			
			if (elgg_instanceof($grouptest, 'group')) {
				$groups[] = $access_id;
			} 
		}
		
		if (count($groups)) {
			// there are groups, so we need to merge collections
			$groups[] = ACCESS_FRIENDS;
			sort($groups);
			$new_access_id = access_plus_merge_collections($groups);
		}
		else {
			// no groups, so we can just set it to friends
			return ACCESS_FRIENDS;
		}
	}
	else {
		//private was selected with something else, which makes private unneccesary
		//remove private from the access array
		if (in_array(ACCESS_PRIVATE, $access)) {
			$access = access_plus_remove_from_array(ACCESS_PRIVATE, $access);
		}
		
		$new_access_id = access_plus_merge_collections($access);
	}
	return $new_access_id;
}


function access_plus_permissions_check(){
	$context = elgg_get_context();
	if($context == "access_plus_permissions"){
		return true;
	}
	
	return NULL;
}


//	removes a single item from an array
//	resets keys
//
function access_plus_remove_from_array($value, $array){
	if(!is_array($array)){ return $array; }
	if(!in_array($value, $array)){ return $array; }
	
	for($i=0; $i<count($array); $i++){
		if($value == $array[$i]){
			unset($array[$i]);
			$array = array_values($array);
		}
	}
	
	return $array;
}


function access_plus_purge_synclist() {
  remove_entity_relationships(
	elgg_get_site_entity()->guid,
	ACCESS_PLUS_SYNC_RELATIONSHIP,
	true
  );
}


//
// removes a token from the blacklist
function access_plus_unblacklist($token){
	$blacklist = elgg_get_plugin_setting('blacklist', 'access_plus');
	$blackarray = explode(",", $blacklist);
	
	$blackarray = access_plus_remove_from_array($token, $blackarray);
	
	$blacklist = implode(",", $blackarray);
	
	elgg_set_plugin_setting('blacklist', $blacklist, 'access_plus');
}


/**
 * 
 * @param type $result - the access_plus object
 * @param type $getter
 * @param type $options
 */
function access_plus_update_metacollection_add($result, $getter, $options) {
  add_user_to_access_collection($options['access_plus_params']['user_guid'], $result->access_plus_collection_id);
}

/**
 * 
 * @param type $result
 * @param type $getter
 * @param type $options
 */
function access_plus_update_metacollection_remove($result, $getter, $options) {
  
  $access_array = $options['access_plus_params']['access_array'];
  
  $remove = true;
  foreach ($access_array as $id) {
	if (in_array($id, $result->access_plus_collections)
			&& $id != $options['access_plus_params']['collection_id']) {
	  $remove = false;
	  break;
	}
  }
  
  if ($remove) {
	// the user doesn't belong in any other component collections
	// so we'll remove them from the metacollection
	remove_user_from_access_collection($options['access_plus_params']['user_guid'], $result->access_plus_collection_id);
  }
}