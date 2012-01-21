<?php
/**
 *Access Plus
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Matt Beckett
 * @copyright Matt Beckett 2011
 */

/**
 *
 */
include_once 'lib/functions.php';

function access_plus_init() {

	// Load system configuration
	global $CONFIG;
	
	// Extend system CSS with our own styles
	elgg_extend_view('css','access_plus/css', 1000);

	// Load the language file
	register_translations($CONFIG->pluginspath . "access_plus/languages/");
	
	// override permissions for the access_plus_permissions context
	elgg_register_plugin_hook_handler('permissions_check', 'all', 'access_plus_permissions_check');
	
	// watch for changes in collection membership
	elgg_register_plugin_hook_handler('access:collections:add_user', 'collection', 'access_plus_add_user');
	elgg_register_plugin_hook_handler('access:collections:remove_user', 'collection', 'access_plus_remove_user');
	
	// set the sync function to run every hour for users that have been active within the last hour
	elgg_register_plugin_hook_handler('cron', 'hourly', 'access_plus_sync_metacollections');
}

// call function on object creation and update to set permissions
elgg_register_event_handler('create','object','access_plus_access_process', 0);
elgg_register_event_handler('update','object','access_plus_access_process', 0);

// call function on metadata creation and update to set permissions
elgg_register_event_handler('create','metadata','access_plus_access_process', 0);
elgg_register_event_handler('update','metadata','access_plus_access_process', 0);

// call function on annotation creation and update to set permissions
elgg_register_event_handler('create','annotation','access_plus_access_process', 0);
elgg_register_event_handler('update','annotation','access_plus_access_process', 0);

// call function on page load to update any permissions that are pending
elgg_register_event_handler('init', 'system', 'access_plus_pending_process');

//call function on user login and logout to synchronize the metacollections with current collections
elgg_register_event_handler('login', 'user', 'access_plus_add_to_sync_list');
elgg_register_event_handler('logout', 'user', 'access_plus_add_to_sync_list');

elgg_register_event_handler('init','system','access_plus_init');

//register action to toggle our access view
elgg_register_action("access_plus/toggle", elgg_get_plugins_path() . "access_plus/actions/access_plus/toggle.php", 'admin');
?>