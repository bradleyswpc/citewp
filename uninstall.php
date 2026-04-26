<?php
/**
 * Fires only when the plugin is deleted via the WordPress admin.
 * Removes all CiteWP data. Deactivation does NOT trigger this.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop our custom table.
$table = $wpdb->prefix . 'citewp_crawler_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// Remove options.
delete_option( 'citewp_settings' );
delete_option( 'citewp_db_version' );

// Clear scheduled cleanup hook.
$next = wp_next_scheduled( 'citewp_daily_cleanup' );
if ( $next ) {
	wp_unschedule_event( $next, 'citewp_daily_cleanup' );
}
