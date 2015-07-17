<?php
/*
 * Wordpress will run the code in this file when the user deletes the plugin
 * 
 */

if ( !defined('WP_UNINSTALL_PLUGIN')) 
	exit;

delete_option('adsnipp_db_version');
delete_option('adsnipp_registered');

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}adsnipp_ads" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}adsnipp_stats" );
?>