<?php
/**
 *Access Plus
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Matt Beckett
 * @copyright Matt Beckett 2012
 */

require_once('lib/functions.php');
require_once('lib/hooks.php');
require_once('lib/events.php');

define(ACCESS_PLUS_SYNC_RELATIONSHIP, 'access_plus_synclist');
define(ACCESS_PLUS_META_RELATIONSHIP, 'access_plus_meta_collection_of');

function access_plus_init() {
	
  // Extend system CSS with our own styles
  elgg_extend_view('css/elgg','access_plus/css');
	
  // override permissions for the access_plus_permissions context
  elgg_register_plugin_hook_handler('permissions_check', 'all', 'access_plus_permissions_check');
	
  // watch for changes in collection membership
  elgg_register_plugin_hook_handler('access:collections:add_user', 'collection', 'access_plus_add_user');
  elgg_register_plugin_hook_handler('access:collections:remove_user', 'collection', 'access_plus_remove_user');
  
  // delete the access entity with the collection
  elgg_register_plugin_hook_handler('access:collections:deletecollection', 'collection', 'access_plus_collection_delete');
  
  // watch for actions to handle the access conversion
  elgg_register_plugin_hook_handler('action', 'all', 'access_plus_access_process');
	
  // set the sync function to run every hour for users that have been active within the last hour
  elgg_register_plugin_hook_handler('cron', 'hourly', 'access_plus_sync_metacollections');
  
  //call function on user login and logout to synchronize the metacollections with current collections
  elgg_register_event_handler('login', 'user', 'access_plus_add_to_sync_list');
  elgg_register_event_handler('logout', 'user', 'access_plus_add_to_sync_list');
  elgg_register_event_handler('create', 'friend', 'access_plus_add_friend');
  elgg_register_event_handler('delete', 'friend', 'access_plus_remove_friend');
	
  //register action to toggle our access view
  elgg_register_action("access_plus/toggle", elgg_get_plugins_path() . "access_plus/actions/access_plus/toggle.php", 'admin');
}

elgg_register_event_handler('init','system','access_plus_init');
