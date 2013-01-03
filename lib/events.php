<?php

function access_plus_add_friend($event, $object_type, $object) {
  
  //set a custom context to overwrite permissions temporarily
  $context = elgg_get_context();
  elgg_set_context('access_plus_permission');
	
  $entities = elgg_get_entities_from_metadata(array(
	  'types' => array('object'),
	  'subtypes' => array('access_plus'),
	  'owner_guids' => array($object->guid_one),
	  'metadata_names' => array('access_plus_collections'),
	  'metadata_values' => array(ACCESS_FRIENDS)
  ));
  
  if ($entities) {
	foreach ($entities as $entity) {
	  add_user_to_access_collection($object->guid_two, $entity->access_plus_collection_id);
	}
  }
  
  elgg_set_context($context);
}

//
// adds the user to the list to sync their collections
// list is stored as a plugin setting, then processed on hourly cron
function access_plus_add_to_sync_list($event, $object_type, $object){
  $site = elgg_get_site_entity();
  add_entity_relationship($object->guid, ACCESS_PLUS_SYNC_RELATIONSHIP, $site->guid);
}


function access_plus_remove_friend($event, $object_type, $object) {
    //set a custom context to overwrite permissions temporarily
  $context = elgg_get_context();
  elgg_set_context('access_plus_permission');
	
  $access_array = get_access_array($object->guid_two);
  $params['user_guid'] = $object->guid_two;
  $params['access_array'] = $access_array;
  $params['collection_id'] = ACCESS_FRIENDS;
	
  //get an array of all of the metacollections potentially affected by this change
  $options = array(
		'types' => array('object'),
		'subtypes' => array('access_plus'),
		'owner_guids' => array($object->guid_one),
		'metadata_name_value_pairs' => array('access_plus_collections' => ACCESS_FRIENDS),
		'limit' => 0,
		'access_plus_params' => $params
  );
	
  $batch = new ElggBatch('elgg_get_entities_from_metadata', $options, 'access_plus_update_metacollection_remove', 25);
  
  elgg_set_context($context);
}