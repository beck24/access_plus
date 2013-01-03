<?php
// this is the first time, set the plugin setting so the code below won't be set ever again
add_subtype('object', 'access_plus');
  
// set default blacklist
// these should be common to all elgg installations
// they represent access selections that are buggy at this time
// may not work depending on other plugins, but it can't hurt
$blacklistexists = elgg_get_plugin_setting('blacklist', 'access_plus');

if (!$blacklistexists) {
  $blackarray = array();
  // group open/closed
  $blackarray[] = "1354927dabe566ba9b5e02082e3c260a";
  // site administration - default access setting
  $blackarray[] = "37f86bef6ad8f257cc6c4f498cd5e247";
  $blacklist = implode(",", $blackarray);
  elgg_set_plugin_setting('blacklist', $blacklist, 'access_plus');
}

// elgg batch called on activation
function access_plus_get_access_objects($result, $getter, $options){
  global $ACCESS_COLLECTION_IDS;
  if (!is_array($ACCESS_COLLECTION_IDS)) {
	$ACCESS_COLLECTION_IDS = array();
  }
  
  $ACCESS_COLLECTION_IDS[] = $result->access_plus_collection_id;
}

// get all access collections without a corresponding object
// and create an object for them
// using ElggBatch because there may be many, many collections in the installation
// try to avoid oom errors
$options = array(
	'types' => array('object'),
	'subtypes' => array('access_plus'),
	'limit' => 0
);

$batch = new ElggBatch('elgg_get_entities', $options, 'access_plus_get_access_objects', 25);

global $ACCESS_COLLECTION_IDS;

$dbprefix = elgg_get_config('dbprefix');

if (is_array($ACCESS_COLLECTION_IDS) && count($ACCESS_COLLECTION_IDS)) {
  // we have some entities, lets use them to limit our query
  
  $in = "(" . implode(',', $ACCESS_COLLECTION_IDS) . ")";
  $q = "SELECT id, owner_guid FROM {$dbprefix}access_collections WHERE id NOT IN{$in}";
  $data = get_data($q);
}
else {
  $data = get_data("SELECT id, owner_guid FROM {$dbprefix}access_collections");
}

// create our access entities
if ($data) {
  foreach ($data as $result) {
	
	$entity = new ElggObject();
	$entity->subtype = 'access_plus';
	$entity->owner_guid = $result->owner_guid;
	$entity->container_guid = $result->owner_guid;
	$entity->access_id = ACCESS_PUBLIC;
	$entity->save();
	
	// add our metadata for potentially easy retrieval
	$entity->access_plus_collection_id = $result->id;
  }
}

// so now all collections should be relatable via entities