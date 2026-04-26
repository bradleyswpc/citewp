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

// Drop our custom crawler logs table.
$table = $wpdb->prefix . 'citewp_crawler_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall only; table name has no user input.

// Remove plugin options.
delete_option( 'citewp_settings' );
delete_option( 'citewp_llms_settings' );
delete_option( 'citewp_db_version' );

// Remove all CiteWP post meta.
$meta_keys = [
	'_citewp_geo_score',
	'_citewp_geo_score_total',
	'_citewp_geo_score_grade',
	'_citewp_geo_score_time',
	'_citewp_exclude_from_llms',
];
foreach ( $meta_keys as $key ) {
	$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => $key ], [ '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

// Remove cached llms.txt transients.
delete_transient( 'citewp_llms_short' );
delete_transient( 'citewp_llms_full' );

// Clear scheduled cleanup hook.
$next = wp_next_scheduled( 'citewp_daily_cleanup' );
if ( $next ) {
	wp_unschedule_event( $next, 'citewp_daily_cleanup' );
}
