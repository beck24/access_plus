<?php
// this is the first time, set the plugin setting so the code below won't be set ever again
  
// set default blacklist
// these should be common to all elgg installations
// they represent access selections that are buggy at this time
// may not work depending on other plugins, but it can't hurt
$blacklistexists = elgg_get_plugin_setting('blacklist', 'access_plus');

if(!$blacklistexists){
  $blackarray = array();
  // group open/closed
  $blackarray[] = "1354927dabe566ba9b5e02082e3c260a";
  // site administration - default access setting
  $blackarray[] = "37f86bef6ad8f257cc6c4f498cd5e247";
  $blacklist = implode(",", $blackarray);
  elgg_set_plugin_setting('blacklist', $blacklist, 'access_plus');
}