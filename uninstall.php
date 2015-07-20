<?php
/*
 * Wordpress will run the code in this file when the user deletes the plugin
 * 
 */

if ( !defined('WP_UNINSTALL_PLUGIN')) 
	exit;

delete_option('adsnipp_db_version');
delete_option('adsnipp_registered');
delete_option('adsnipp_app_id');
delete_option('adsnipp_key');
delete_option('adsnipp_secret');

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}adsnipp_ads" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}adsnipp_stats" );
?>