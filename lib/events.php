<?php

//
// adds the user to the list to sync their collections
// list is stored as a plugin setting, then processed on hourly cron
function access_plus_add_to_sync_list($event, $object_type, $object){
  $site = elgg_get_site_entity();
  add_entity_relationship($object->guid, ACCESS_PLUS_SYNC_RELATIONSHIP, $site->guid);
}