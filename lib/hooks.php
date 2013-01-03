<?php

//
// this gets called before the action
// so we intercept the access_id and replace it with our own
function access_plus_access_process($hook, $type, $return, $params){
  // get all of post keys and see if any of them are access plus controlled items
  $keys = array_keys($_POST);
  if (!is_array($keys)) {
	return $return;
  }
  
  $access_keys = array();
  foreach ($keys as $key) {
	if (strpos($key, 'access_plus_') === 0) {
	  // this is our input
	  $access_keys[] = substr($key, 12);
	}
  }
  
  if (!count($access_keys)) {
	return $return;
  }

  // go through our inputs, and change them to our desired metacollections
  foreach ($access_keys as $access_key) {
	$access = get_input('access_plus_' . $access_key);

	if (is_array($access) && count($access)) {
	  $new_access_id = access_plus_parse_access($access);
	  set_input($access_key, $new_access_id);
	}
  }
}


// called by the hook when a user is added to an access collection
// checks to see if that collection is used in a metacollection
// if so, checks to see if the user is already there, if not adds them
// $params['collection_id'], $params['collection'], $params['user_guid']
function access_plus_add_user($hook, $type, $returnvalue, $params){
	// set a custom context to overwrite permissions temporarily
	$context = elgg_get_context();
	elgg_set_context('access_plus_permission');
	
	//get an array of all of the metacollections affected by this change
	$options = array(
		'types' => array('object'),
		'subtypes' => array('access_plus'),
		'metadata_names' => array('access_plus_collections'),
		'metadata_values' => array($params['collection_id']),
		'limit' => 0,
		'access_plus_params' => $params
	);
	$batch = new ElggBatch('elgg_get_entities_from_metadata', $options, 'access_plus_update_metacollection_add', 25);

	elgg_set_context($context);
}


function access_plus_collection_delete($hook, $type, $return, $params) {
  // get and delete the access entity for this collection
  $entities = elgg_get_entities_from_metadata(array(
	 'types' => array('object'),
	  'subtypes' => array('access_plus'),
	  'metadata_names' => array('access_plus_collection_id'),
	  'metadata_values' => array($params['collection_id'])
  ));
  
  if ($entities) {
	foreach ($entities as $entity) {
	  $entity->delete();
	}
  }
}


function access_plus_permissions_check($hook, $type, $return, $params){
	$context = elgg_get_context();
	if($context == "access_plus_permissions"){
		return true;
	}
	
	return NULL;
}

//
// called by the hook when a user is removed from an access collection
// checks to see if that collection is used in a metacollection
// if so, checks to see if the user needs to be removed, if so removes them
// $params['user_guid'], $params['collection_id']
function access_plus_remove_user($hook, $type, $returnvalue, $params){
		// set a custom context to overwrite permissions temporarily
	$context = elgg_get_context();
	elgg_set_context('access_plus_permission');
	
	$access_array = get_access_array($params['user_guid']);
	$params['access_array'] = $access_array;
	
	//get an array of all of the metacollections potentially affected by this change
	$options = array(
		'types' => array('object'),
		'subtypes' => array('access_plus'),
		'metadata_name_value_pairs' => array('access_plus_collections' => $params['collection_id']),
		'limit' => 0,
		'access_plus_params' => $params
	);
	
	$batch = new ElggBatch('elgg_get_entities_from_metadata', $options, 'access_plus_update_metacollection_remove', 25);
	
	elgg_set_context($context);
}

//
// function called on cron
// this will empty all of the metacollections and repopulate them properly
// this will restore proper permissions in case collections were edited while the
// plugin was disabled
function access_plus_sync_metacollections($hook, $entity_type, $returnvalue, $params) {
  $dbprefix = elgg_get_config('dbprefix');
  
  //set a custom context to overwrite permissions temporarily
  $context = elgg_get_context();
  elgg_set_context('access_plus_permission');
	
  $options = array(
	'types' => array('user'),
	'relationship' => 'access_plus_synclist',
	'relationship_guid' => elgg_get_site_entity()->guid,
	'inverse_relationship' => true,
	'limit' => 0
  );
  
  $batch = new ElggBatch('elgg_get_entities_from_relationship', $options, 'access_plus_user_sync', 25);


  access_plus_purge_synclist();
  elgg_set_context($context);
}